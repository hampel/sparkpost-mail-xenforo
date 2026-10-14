<?php namespace Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Hampel\SparkPostMail\Cli\Command\Config;
use Hampel\SparkPostMail\Cli\Command\Validate;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

/**
 * sparkpost:config and sparkpost:validate. The exit contract is the product here, so it is
 * asserted in every combination the add-on's README promises: a warning is 0 without --strict and
 * 2 with it, and a failure is 1 either way.
 */
class CommandsTest extends TestCase
{
	protected function selectSparkPost(bool $on, string $key = 'test-key'): void
	{
		$this->setOptions(['emailTransport' => [
			'emailTransport' => $on ? 'sparkpost' : 'smtp',
			'apiKey' => $on ? $key : '',
			'testMode' => false,
			'clickTracking' => false,
			'openTracking' => false,
			'smtpHost' => 'localhost',
			'smtpPort' => 1025,
			'smtpAuth' => '',
			'smtpLoginUsername' => '',
			'smtpLoginPassword' => '',
			'smtpSsl' => false,
		]]);
	}

	/**
	 * XenForo loads every add-on's command classes to list them, so one that cannot load takes
	 * down every command in cmd.php rather than only its own. The list is asserted whole, so a
	 * command added later with no test of its own fails here too.
	 */
	public function test_every_command_class_is_one_xenforo_can_load()
	{
		$commands = $this->assertConsoleCommandsLoad('Hampel/SparkPostMail');
		sort($commands);

		$this->assertSame([
			\Hampel\SparkPostMail\Cli\Command\Config::class,
			\Hampel\SparkPostMail\Cli\Command\FetchMessageEvents::class,
			\Hampel\SparkPostMail\Cli\Command\ProcessMessageEvents::class,
			\Hampel\SparkPostMail\Cli\Command\PruneMessageEvents::class,
			\Hampel\SparkPostMail\Cli\Command\ResetMessageEventsCache::class,
			\Hampel\SparkPostMail\Cli\Command\Validate::class,
		], $commands);
	}

	/**
	 * The two policy checks the framework's helper deliberately leaves to the add-on, both of
	 * which are fatal for every command in cmd.php rather than just this one: XF\Cli\Command\
	 * AbstractCommand does not exist on XenForo 2.2, and a helper named run() collides with
	 * Command::run().
	 */
	public function test_no_command_breaks_the_cli_for_every_add_on()
	{
		$problems = [];

		foreach (glob(dirname(__DIR__, 2) . '/Cli/Command/*.php') ?: [] AS $file)
		{
			$class = 'Hampel\\SparkPostMail\\Cli\\Command\\' . basename($file, '.php');

			if (!class_exists($class) || (new \ReflectionClass($class))->isAbstract())
			{
				continue;
			}

			$reflection = new \ReflectionClass($class);

			if (!$reflection->isSubclassOf(Command::class))
			{
				$problems[] = "{$class}: not a Symfony Command";
			}

			if ($reflection->hasMethod('run')
				&& $reflection->getMethod('run')->getDeclaringClass()->getName() === $class)
			{
				$problems[] = "{$class}: redefines run(), which collides with Command::run()";
			}
		}

		$this->assertSame([], $problems);
	}

	public function test_config_reports_each_setting_with_where_it_came_from()
	{
		$this->selectSparkPost(false);

		$tester = $this->runConsoleCommand(Config::class);

		$this->assertSame(0, $tester->getStatusCode());

		$output = $tester->getDisplay();

		$this->assertMatchesRegularExpression('/Selected transport \.+ smtp/', $output);
		$this->assertMatchesRegularExpression('/Test mode \.+ no \(options\)/', $output);
		$this->assertMatchesRegularExpression('/API page size \.+ 1000 \(options\)/', $output);
		$this->assertMatchesRegularExpression('/sparkPostApi \.+ not set/', $output);
	}

	/**
	 * The output of this is pasted into tickets, so the key must never be in it. What is useful
	 * and safe is that it is set and where it points.
	 */
	public function test_config_says_a_key_is_set_and_never_prints_it()
	{
		$this->selectSparkPost(true, 'made-up-not-a-real-key');

		$output = $this->runConsoleCommand(Config::class)->getDisplay();

		$this->assertStringNotContainsString('made-up-not-a-real-key', $output);
		$this->assertMatchesRegularExpression('/API key \.+ set, to api\.sparkpost\.com/', $output);
	}

	public function test_config_sends_nothing()
	{
		$this->fakesMail();

		$this->runConsoleCommand(Config::class);

		$this->assertNoMailSent();
	}

	public function test_an_inactive_transport_warns_rather_than_failing_or_passing()
	{
		$this->selectSparkPost(false);

		$tester = $this->runConsoleCommand(Validate::class, [], ['interactive' => false]);

		$this->assertSame(0, $tester->getStatusCode(), 'a warning is 0 without --strict');
		$this->assertStringContainsString('[warn] SparkPost not active', $tester->getDisplay());
	}

	public function test_the_same_run_exits_2_under_strict()
	{
		$this->selectSparkPost(false);

		$tester = $this->runConsoleCommand(Validate::class, ['--strict' => true]);

		$this->assertSame(2, $tester->getStatusCode());
	}

	public function test_unattended_skips_the_live_call_and_sends_nothing()
	{
		$this->selectSparkPost(true);
		$this->fakesMail();

		$tester = $this->runConsoleCommand(Validate::class, ['--unattended' => true]);

		$this->assertStringContainsString('[    ] Credentials accepted', $tester->getDisplay());
		$this->assertStringContainsString('--unattended', $tester->getDisplay());
		$this->assertNoMailSent();
	}

	/**
	 * A key SparkPost rejects is the failure this command exists for, and it is reachable offline:
	 * the add-on's HTTP goes through XF\Http\Reader on the untrusted path, which the framework can
	 * fake. Without a check like this the only proof of the [fail] row is a real bad credential.
	 */
	public function test_a_rejected_key_fails_the_run()
	{
		$this->selectSparkPost(true);
		$this->fakesErrors();
		$this->fakesHttp([new Response(401, [], json_encode([
			'errors' => [['message' => 'Unauthorized.', 'code' => '401']],
		]))], true);

		$tester = $this->runConsoleCommand(Validate::class);

		$this->assertSame(1, $tester->getStatusCode(), 'a failure is 1 with or without --strict');
		$this->assertStringContainsString('[fail] Credentials accepted', $tester->getDisplay());
	}
}
