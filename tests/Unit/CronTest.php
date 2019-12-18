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

    public function test_fetchMessageEvents_queues_job()
    {
    	MessageEvents::fetchMessageEvents();

    	$this->assertJobQueued('Hampel\SparkPostMail:MessageEvent', function ($job) {
    		return $job['unique_key'] == 'SparkPostMailMessageEvents';
	    });
    }

    public function test_processMessageEvents_queues_job()
    {
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
