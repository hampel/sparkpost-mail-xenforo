<?php namespace Hampel\SparkPostMail\Cli\Command;

use Hampel\SparkPostMail\Cli\RendersReport;
use Hampel\SparkPostMail\Option\EmailTransport;
use Hampel\SparkPostMail\Repository\MessageEventRepository;
use Hampel\SparkPostMail\SubContainer\SparkPost;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prove this forum can do what sparkpost:config says it is configured to do.
 *
 * The checks that are facts about the configuration run first, so they still report under
 * --unattended; the one call that leaves the machine runs last and is what --unattended skips.
 *
 * This add-on earns the pair on all three of the questions in xenforo-addon-validate: the API key
 * is opaque and lives inside somebody else's option, the whole message-event pipeline runs
 * unattended under cron, and no screen in the ACP shows the fetch watermark at all.
 */
class Validate extends Command
{
	use RendersReport;

	protected function configure()
	{
		$this
			->setName('sparkpost:validate')
			->setDescription('Check that SparkPost mail and message-event processing can work')
			->addOption('unattended', null, InputOption::VALUE_NONE,
				'Skip the live API call, for a gate nobody is watching')
			->addOption('strict', null, InputOption::VALUE_NONE,
				'Exit 2 when there are warnings but no failures');
	}

	protected function errorLogPrefix(): string
	{
		return 'SparkPostMail: ';
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->report = $output;

		$app = \XF::app();
		$enabled = EmailTransport::isSparkPostEnabled();

		$this->checkSection('Transport selection');

		if ($enabled)
		{
			$this->checkOk('SparkPost selected', 'and an API key is set');
		}
		else
		{
			// Not a skip and not a failure. Nothing is broken - every extension guards on this
			// static and the add-on is deliberately inert - but no mail will go through SparkPost
			// and no bounce will come back, which is worth one line rather than silence.
			$this->checkWarn(
				'SparkPost not active',
				EmailTransport::getApiKey()
					? 'another transport is selected'
					: 'not selected, or no API key set'
			);
		}

		$this->checkSection('Wiring');

		$this->probe('Logger resolves', function () use ($app)
		{
			$logger = $app->container('sparkpostmail.log');

			if (!$logger instanceof LoggerInterface)
			{
				$this->checkFail('Logger resolves', 'sparkpostmail.log is not a LoggerInterface');
				return;
			}

			$this->checkOk('Logger resolves', get_class($logger));
		});

		$this->probe('API client builds', function () use ($app, $enabled)
		{
			if (!$enabled)
			{
				$this->checkSkip('API client builds', 'SparkPost not active');
				return;
			}

			/** @var SparkPost $api */
			$api = $app->container('sparkpostmail.api');
			$api->sparkpost();

			$this->checkOk('API client builds');
		});

		$this->probe('Transport builds', function () use ($app, $enabled)
		{
			if (!$enabled)
			{
				$this->checkSkip('Transport builds', 'SparkPost not active');
				return;
			}

			/** @var SparkPost $api */
			$api = $app->container('sparkpostmail.api');

			$this->checkOk('Transport builds', get_class($api->transport()));
		});

		$this->checkSection('Message events');

		$this->probe('Staging table', function () use ($app)
		{
			// The schema manager rather than SHOW TABLES: a prepared statement cannot bind a
			// parameter there, which is a syntax error rather than an empty result.
			$exists = $app->db()->getSchemaManager()->tableExists('xf_sparkpost_mail_message_event');

			$exists
				? $this->checkOk('Staging table', 'xf_sparkpost_mail_message_event')
				: $this->checkFail('Staging table', 'xf_sparkpost_mail_message_event is missing');
		});

		$this->probe('Cron entries', function () use ($app)
		{
			$rows = $app->db()->fetchAll(
				"SELECT entry_id, active FROM xf_cron_entry WHERE addon_id = ?",
				'Hampel/SparkPostMail'
			);

			$off = array_values(array_map(
				fn (array $r) => $r['entry_id'],
				array_filter($rows, fn (array $r) => !$r['active'])
			));

			if (!$rows)
			{
				$this->checkFail('Cron entries', 'none registered - nothing will fetch or process');
			}
			elseif ($off)
			{
				$this->checkWarn('Cron entries', count($off) . ' of ' . count($rows)
					. ' disabled: ' . implode(', ', $off));
			}
			else
			{
				$this->checkOk('Cron entries', count($rows) . ' active');
			}
		});

		$this->probe('Fetch watermark', function () use ($app)
		{
			/** @var MessageEventRepository $repo */
			$repo = $app->repository(MessageEventRepository::class);
			$last = $repo->getLastRun();

			if (!$last)
			{
				// The first run then reaches back 11 days, which is correct on a new install and
				// surprising on an old one, so it is worth saying rather than passing silently.
				$this->checkWarn('Fetch watermark', 'not set - the next fetch reaches back 11 days');
				return;
			}

			$age = \XF::$time - $last;

			$age > 86400
				? $this->checkWarn('Fetch watermark', 'last fetch ' . intdiv($age, 86400) . ' day(s) ago')
				: $this->checkOk('Fetch watermark', "last fetch {$age}s ago");
		});

		$this->checkSection('SparkPost API');

		if ($input->getOption('unattended'))
		{
			$this->checkSkip('Credentials accepted', '--unattended');
		}
		elseif (!$enabled)
		{
			$this->checkSkip('Credentials accepted', 'SparkPost not active');
		}
		else
		{
			$this->probe('Credentials accepted', function () use ($app)
			{
				/** @var SparkPost $api */
				$api = $app->container('sparkpostmail.api');

				// The cheapest authenticated read there is: it proves the key, the region and the
				// outbound path through XenForo's own HTTP stack, and sends no mail.
				$domains = $api->sparkpost()->sendingDomains()->all();

				$this->checkOk('Credentials accepted', count($domains) . ' sending domain(s)');
			});
		}

		$this->report->writeln('');

		return $this->checkExitCode((bool) $input->getOption('strict'));
	}
}
