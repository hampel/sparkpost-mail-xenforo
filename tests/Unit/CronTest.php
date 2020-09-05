<?php namespace Tests\Unit;

use Hampel\SparkPostMail\Cron\MessageEvents;
use Tests\TestCase;

class CronTest extends TestCase
{
	public function setUp(): void
	{
		parent::setUp();

		$this->fakesJobs();
	}

    public function test_fetchMessageEvents_does_not_queue_job_when_sparkpost_not_configured()
    {
		$this->setOptions([
			'emailTransport' => [
				'emailTransport' => 'foo',
			],
		]);

    	MessageEvents::fetchMessageEvents();

    	$this->assertJobNotQueued('Hampel\SparkPostMail:MessageEvent', function ($job) {
    		return $job['unique_key'] == 'SparkPostMailMessageEvents';
	    });
    }

    public function test_fetchMessageEvents_queues_job()
    {
		$this->setOptions([
			'emailTransport' => [
				'emailTransport' => 'sparkpost',
				'apiKey' => 'foo'
			],
		]);

    	MessageEvents::fetchMessageEvents();

    	$this->assertJobQueued('Hampel\SparkPostMail:MessageEvent', function ($job) {
    		return $job['unique_key'] == 'SparkPostMailMessageEvents';
	    });
    }

    public function test_processMessageEvents_does_not_queue_job_when_sparkpost_not_configured()
    {
		$this->setOptions([
			'emailTransport' => [
				'emailTransport' => 'foo',
			],
		]);

    	MessageEvents::processMessageEvents();

    	$this->assertJobNotQueued('Hampel\SparkPostMail:EmailBounce', function ($job) {
    		return $job['unique_key'] == 'SparkPostMailEmailBounce';
	    });
    }

    public function test_processMessageEvents_queues_job()
    {
		$this->setOptions([
			'emailTransport' => [
				'emailTransport' => 'sparkpost',
				'apiKey' => 'foo'
			],
		]);

    	MessageEvents::processMessageEvents();

    	$this->assertJobQueued('Hampel\SparkPostMail:EmailBounce', function ($job) {
    		return $job['unique_key'] == 'SparkPostMailEmailBounce';
	    });
    }

    public function test_dailyCleanup()
    {
    	$this->mockRepository('Hampel\SparkPostMail:MessageEvent', function ($mock) {
    		$mock->expects()->pruneMessageEvents();
	    });

    	MessageEvents::dailyCleanup();
    }
}
