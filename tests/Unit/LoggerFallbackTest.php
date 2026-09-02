<?php namespace Tests\Unit;

use Hampel\SparkPostMail\Api\SparkPostApi;
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

	public function test_the_api_wrapper_constructs_without_monolog()
	{
		$this->withoutMonolog();

		$this->assertInstanceOf(SparkPostApi::class, $this->app()->container('sparkpostmail.api')->api());
	}
}
