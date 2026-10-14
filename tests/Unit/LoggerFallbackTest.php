<?php namespace Tests\Unit;

use Hampel\SparkPost\SparkPost;
use Hampel\SparkPostMail\Job\FetchMessageEventsJob;
use Hampel\SparkPostMail\Job\ProcessMessageEventsJob;
use Hampel\SparkPostMail\Listener;
use Hampel\SparkPostMail\Service\MessageEventService;
use Psr\Log\NullLogger;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Hampel/Monolog is a soft dependency - it is not in addon.json require. Everything that logs
 * passes the sparkpostmail.log container value straight into setLogger(LoggerInterface), which
 * is not nullable, so the container must never hand back null.
 */
class LoggerFallbackTest extends TestCase
{
	/**
	 * Select SparkPost with a dummy key, so nothing in this class depends on how the install
	 * running the suite happens to be configured.
	 *
	 * Without it the class inherits the real `emailTransport` option. `Config::forRegion()`
	 * types its first argument `string`, so on a board set to SMTP `getApiKey()` returns null
	 * and `test_the_sparkpost_client_constructs_without_monolog` errors with a TypeError that
	 * has nothing to do with Monolog. It passed from 2026-09-07 to 2026-10-03 only because the
	 * dev board was selecting SparkPost over that period.
	 */
	public function setUp(): void
	{
		parent::setUp();

		$this->setOptions(['emailTransport' => [
			'emailTransport' => 'sparkpost',
			'apiKey' => 'test-key',
			'testMode' => false,
			'clickTracking' => false,
			'openTracking' => false,
		]]);
	}

	/** Rebind sparkpostmail.log as it would resolve on an install with no Monolog add-on. */
	protected function withoutMonolog(): void
	{
		$container = $this->app()->container();
		$container->offsetUnset('monolog');
		$container->offsetUnset('sparkpostmail.log');
		$container->offsetUnset('sparkpostmail.api');

		Listener::appSetup($this->app());
	}

	/**
	 * The other side of the fallback, which lost its coverage when the suite moved to test
	 * framework 5.x: $addonsToLoad now filters code event listeners, so Hampel/Monolog's
	 * app_setup never runs and the monolog container key is never registered. Every test is
	 * therefore in the add-on-absent state by default, and nothing exercised the present one.
	 *
	 * Adding Hampel/Monolog to $addonsToLoad would cover it and is not safe here: that add-on
	 * vendors its own PHPUnit, and booting it registers that PHPUnit onto XF's class loader.
	 * A fake container key is the better unit test anyway - what belongs to this add-on is the
	 * decision to ask for a channel when the key is there, not Monolog's behaviour.
	 *
	 * The channel name is asserted rather than assumed, because it is load-bearing beyond this
	 * binding: Hampel/Monolog 5.x takes a per-channel minimum log level keyed on exactly this
	 * string, so renaming it would silently detach any such override.
	 */
	public function test_the_channel_comes_from_monolog_4x_through_newChannel()
	{
		$channel = new NullLogger();

		$monolog = new class($channel) {
			public array $asked = [];

			public function __construct(private $channel) {}

			public function newChannel($name)
			{
				$this->asked[] = $name;

				return $this->channel;
			}
		};

		$container = $this->app()->container();
		$container->offsetUnset('sparkpostmail.log');
		$container['monolog'] = function() use ($monolog) { return $monolog; };

		Listener::appSetup($this->app());

		$this->assertSame(
			$channel,
			$this->app()->container('sparkpostmail.log'),
			'the channel Monolog handed back must be what consumers are given'
		);
		$this->assertSame(['sparkpost'], $monolog->asked);
	}

	/**
	 * The 5.x interface, which is what every forum here now runs. channel() arrived in
	 * Hampel/Monolog 5.0 and newChannel() is kept as a deprecated alias, so a fake offering both
	 * must be asked through channel() - otherwise the add-on would be riding a deprecation on
	 * every install and nothing would say so.
	 */
	public function test_the_channel_comes_from_monolog_5x_through_channel()
	{
		$channel = new NullLogger();

		$monolog = new class($channel) {
			public array $asked = [];

			public function __construct(private $channel) {}

			public function channel($name)
			{
				$this->asked[] = "channel:{$name}";

				return $this->channel;
			}

			public function newChannel($name)
			{
				$this->asked[] = "newChannel:{$name}";

				return $this->channel;
			}
		};

		$container = $this->app()->container();
		$container->offsetUnset('sparkpostmail.log');
		$container['monolog'] = function() use ($monolog) { return $monolog; };

		Listener::appSetup($this->app());

		$this->assertSame($channel, $this->app()->container('sparkpostmail.log'));
		$this->assertSame(
			['channel:sparkpost'],
			$monolog->asked,
			'with both available the 5.x name must win, and the channel name must not drift'
		);
	}

	public function test_the_log_channel_falls_back_to_a_null_logger()
	{
		$this->withoutMonolog();

		$this->assertInstanceOf(NullLogger::class, $this->app()->container('sparkpostmail.log'));
	}

	public function test_the_bounce_processing_service_constructs_without_monolog()
	{
		$this->withoutMonolog();

		$this->assertInstanceOf(
			MessageEventService::class,
			$this->app()->service(MessageEventService::class)
		);
	}

	public function test_the_message_event_jobs_construct_without_monolog()
	{
		$this->withoutMonolog();

		$this->assertInstanceOf(FetchMessageEventsJob::class, $this->app()->job(FetchMessageEventsJob::class, 1));
		$this->assertInstanceOf(ProcessMessageEventsJob::class, $this->app()->job(ProcessMessageEventsJob::class, 2));
	}

	public function test_the_sparkpost_client_constructs_without_monolog()
	{
		$this->withoutMonolog();

		$this->assertInstanceOf(SparkPost::class, $this->app()->container('sparkpostmail.api')->sparkpost());
	}

	/**
	 * Constructing is not enough - these consumers call the PSR-3 level methods on every code
	 * path, so those have to survive the fallback too. Hampel\SparkPost\SparkPost is not in this
	 * list because it takes a logger rather than being one; that it constructs at all is the
	 * check, and its constructor types the parameter, so a null from the container still fatals.
	 */
	#[DataProvider('loggingConsumers')]
	public function test_every_log_level_works_without_monolog(string $consumer)
	{
		$this->withoutMonolog();

		$subject = $this->{$consumer}();

		foreach (['debug', 'info', 'notice', 'warning', 'error'] as $level)
		{
			$subject->$level("probe {$level}", ['context' => 'value']);
		}

		$this->assertInstanceOf(NullLogger::class, $this->app()->container('sparkpostmail.log'));
	}

	public static function loggingConsumers(): array
	{
		return [
			'service' => ['makeService'],
			'fetch job' => ['makeFetchJob'],
			'process job' => ['makeProcessJob'],
		];
	}

	protected function makeService() { return $this->app()->service(MessageEventService::class); }
	protected function makeFetchJob() { return $this->app()->job(FetchMessageEventsJob::class, 901); }
	protected function makeProcessJob() { return $this->app()->job(ProcessMessageEventsJob::class, 902); }
}
