<?php namespace Hampel\SparkPostMail\Cli\Command;

use Hampel\SparkPostMail\Cli\RendersReport;
use Hampel\SparkPostMail\Option\EmailTransport;
use Hampel\SparkPostMail\Option\MessageEventsBatchSize;
use Hampel\SparkPostMail\Repository\MessageEventRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Print what this forum is configured to do, and where each value came from.
 *
 * The question this exists to answer is "why is it not doing what the options page says", which
 * is sharper here than for most add-ons: there is no sparkpostmailApiKey option. The key, the
 * tracking flags and test mode are extra members inside XenForo's own emailTransport option, the
 * fetch watermark lives in the simpleCache rather than the database, and the API url can be
 * overridden from config.php. Four places, none of them on one screen.
 *
 * This command changes nothing and sends nothing.
 */
class Config extends Command
{
	use RendersReport;

	protected function configure()
	{
		$this
			->setName('sparkpost:config')
			->setDescription('Report what this forum is configured to do with SparkPost mail');
	}

	protected function errorLogPrefix(): string
	{
		return 'SparkPostMail: ';
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->report = $output;

		$app = \XF::app();
		$transport = EmailTransport::get();
		$selected = $transport['emailTransport'] ?? null;

		$this->heading('Mail transport');
		$this->detail('Selected transport', $selected ?: 'not set');
		$this->detail('SparkPost active', EmailTransport::isSparkPostEnabled() ? 'yes' : 'no');

		// Never the key itself: this output is pasted into tickets. Where it points is the useful
		// half, and it is the half that is wrong when a key is from the wrong account.
		$this->detail('API key', $this->describeKey());

		$this->heading('Sending options');
		$this->detail('Test mode', $this->yesNo(EmailTransport::isTestModeEnabled()));
		$this->detail('Click tracking', $this->yesNo(EmailTransport::isClickTrackingEnabled()));
		$this->detail('Open tracking', $this->yesNo(EmailTransport::isOpenTrackingEnabled()));

		$this->heading('Message events');
		$this->detail('API page size', (string) MessageEventsBatchSize::get() . ' (options)');
		$this->detail('Last fetch watermark', $this->describeWatermark($app));

		$this->heading('config.php');
		$custom = $app->config('sparkPostApi');
		$this->detail(
			'sparkPostApi',
			$custom ? "set, to {$this->hostOf($custom)} (config.php)" : 'not set'
		);

		$this->heading('Logging');
		$this->detail(
			'Monolog available',
			$app->container()->offsetExists('monolog') ? 'yes' : 'no - logging is a no-op'
		);

		$this->report->writeln('');

		return 0;
	}

	protected function yesNo(bool $value): string
	{
		return ($value ? 'yes' : 'no') . ' (options)';
	}

	protected function describeKey(): string
	{
		$key = EmailTransport::getApiKey();

		if (!$key)
		{
			return 'not set';
		}

		// The region decides the host, so saying where the key points is also saying which
		// SparkPost account family it belongs to.
		$custom = \XF::app()->config('sparkPostApi');

		return 'set, to ' . ($custom ? $this->hostOf($custom) : 'api.sparkpost.com') . ' (options)';
	}

	protected function describeWatermark(\XF\App $app): string
	{
		/** @var MessageEventRepository $repo */
		$repo = $app->repository(MessageEventRepository::class);
		$last = $repo->getLastRun();

		if (!$last)
		{
			return 'not set - the next fetch reaches back 11 days (simpleCache)';
		}

		$age = \XF::$time - $last;

		return gmdate('Y-m-d H:i:s', $last) . "Z, {$age}s ago (simpleCache)";
	}

	protected function hostOf(string $url): string
	{
		return parse_url($url, PHP_URL_HOST) ?: $url;
	}
}
