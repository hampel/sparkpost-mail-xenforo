<?php namespace Tests\Unit;

use Hampel\SparkPost\SparkPost;
use Hampel\SparkPostMail\Job\FetchMessageEventsJob;
use Hampel\SparkPostMail\Job\ProcessMessageEventsJob;
use Hampel\SparkPostMail\Listener;
use Hampel\SparkPostMail\Service\MessageEventService;
use Psr\Log\NullLogger;
use Tests\TestCase;

/**
 * Hampel/Monolog is a soft dependency - it is not in addon.json require. Everything that logs
 * passes the sparkpostmail.log container value straight into setLogger(LoggerInterface), which
 * is not nullable, so the container must never hand back null.
 */
class LoggerFallbackTest extends TestCase
{
	/** Rebind sparkpostmail.log as it would resolve on an install with no Monolog add-on. */
	protected function withoutMonolog(): void
	{
		$container = $this->app()->container();
		$container->offsetUnset('monolog');
		$container->offsetUnset('sparkpostmail.log');
		$container->offsetUnset('sparkpostmail.api');

		Listener::appSetup($this->app());
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
	 *
	 * @dataProvider loggingConsumers
	 */
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
