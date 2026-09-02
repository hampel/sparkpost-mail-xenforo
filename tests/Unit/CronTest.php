<?php namespace Tests\Unit;

use Hampel\SparkPostMail\Cron\MessageEvents;
use Hampel\SparkPostMail\Job\FetchMessageEventsJob;
use Hampel\SparkPostMail\Job\ProcessMessageEventsJob;
use Tests\TestCase;

class CronTest extends TestCase
{
	public function setUp(): void
	{
		parent::setUp();

		$this->fakesJobs();
	}

	protected function enableSparkPost(): void
	{
		$this->setOptions(['emailTransport' => ['emailTransport' => 'sparkpost', 'apiKey' => 'foo']]);
	}

	public function test_fetch_queues_the_job_when_sparkpost_is_configured()
	{
		$this->enableSparkPost();

		MessageEvents::fetchMessageEvents();

		$this->assertJobQueued(FetchMessageEventsJob::class, function ($job) {
			return $job['unique_key'] == 'SparkPostMailFetchMessageEvents';
		});
	}

	public function test_process_queues_the_job_when_sparkpost_is_configured()
	{
		$this->enableSparkPost();

		MessageEvents::processMessageEvents();

		$this->assertJobQueued(ProcessMessageEventsJob::class, function ($job) {
			return $job['unique_key'] == 'SparkPostMailProcessMessageEvents';
		});
	}

	/**
	 * EmailTransport::isSparkPostEnabled() is two conditions joined by &&. Each gate gets its own
	 * test, so neither can be satisfied only because the other short-circuited first.
	 */
	public function test_nothing_is_queued_when_another_transport_is_selected()
	{
		$this->setOptions(['emailTransport' => ['emailTransport' => 'smtp', 'apiKey' => 'foo']]);

		MessageEvents::fetchMessageEvents();
		MessageEvents::processMessageEvents();

		$this->assertNoJobsQueued();
	}

	public function test_nothing_is_queued_when_the_api_key_is_missing()
	{
		$this->setOptions(['emailTransport' => ['emailTransport' => 'sparkpost', 'apiKey' => '']]);

		MessageEvents::fetchMessageEvents();
		MessageEvents::processMessageEvents();

		$this->assertNoJobsQueued();
	}
}
