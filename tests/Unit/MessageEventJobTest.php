<?php namespace Tests\Unit;

use Carbon\Carbon;
use Hampel\SparkPostMail\SubContainer\SparkPost;
use Mockery as m;
use SparkPost\SparkPostException;
use Tests\TestCase;
use XF\Job\JobResult;

class MessageEventJobTest extends TestCase
{
	public function setUp(): void
	{
		parent::setUp();

		$this->setOptions([
			'sparkpostmailMessageEventsBatchSize' => 5,
		]);
	}

	public function test_job_first_call_empty_body()
	{
		$this->mockRepository('Hampel\SparkPostMail:MessageEvent', function ($mock) {
			$mock->expects()->getLastRun()->andReturns(null);
			$mock->expects()->setMessageEventCache(m::any(), m::any(), m::any());
		});

		$this->mock('sparkpostmail', SparkPost::class, function ($mock) {
			$mock->expects()->logJobProgress(m::any(), 'Retrieving initial batch of message events', m::any());
			$mock->expects()->getBounceMessageEventTypes()->andReturns(['foo', 'bar']);

			$daysAgo = Carbon::createFromTimestamp(\XF::$time)->subDays(11)->timestamp;

			$mock->expects()->getMessageEvents(1, 5, ['foo', 'bar'], $daysAgo, \XF::$time);
			$mock->expects()->logJobProgress(m::any(), 'No data returned from query', []);
			$mock->expects()->logJobProgress(m::any(), 'Job complete', m::any());
		});

		$job = $this->app()->job('Hampel\SparkPostMail:MessageEvent', 101);

		$result = $job->run(8);

		$this->assertEquals(JobResult::class, get_class($result));
		$this->assertTrue($result->completed);
	}

	public function test_job_first_call_body_returned()
	{
		$this->mockRepository('Hampel\SparkPostMail:MessageEvent', function ($mock) {
			$mock->expects()->getLastRun()->andReturns(null);
			$mock->expects()->storeMessageEvent(m::any())->times(7);
			$mock->expects()->setMessageEventCache(m::any(), m::any(), m::any());
		});

		$this->mock('sparkpostmail', SparkPost::class, function ($mock) {
			$mock->expects()->logJobProgress(m::any(), 'Retrieving initial batch of message events', m::any());
			$mock->expects()->getBounceMessageEventTypes()->andReturns(['foo', 'bar']);

			$daysAgo = Carbon::createFromTimestamp(\XF::$time)->subDays(11)->timestamp;

			$responseData = json_decode($this->getMockData('message-events.json'), true);

			$mock->expects()
			     ->getMessageEvents(1, 5, ['foo', 'bar'], $daysAgo, \XF::$time)
			     ->andReturns($responseData);
			$mock->expects()->logJobProgress(m::any(), 'Message events found', ['count' => 7]);
			$mock->expects()->logJobProgress(m::any(), 'Message events stored in database for processing', m::any());
			$mock->expects()->logJobProgress(m::any(), 'No further events to process - we\'re done', m::any());
			$mock->expects()->logJobProgress(m::any(), 'Job complete', m::any());
		});

		$job = $this->app()->job('Hampel\SparkPostMail:MessageEvent', 102);

		$result = $job->run(8);

		$this->assertEquals(JobResult::class, get_class($result));
		$this->assertTrue($result->completed);
	}

	public function test_job_paging_initial()
	{
		$this->mockRepository('Hampel\SparkPostMail:MessageEvent', function ($mock) {
			$mock->expects()->getLastRun()->andReturns(null);
			$mock->expects()->storeMessageEvent(m::any())->times(5);
		});

		$this->mock('sparkpostmail', SparkPost::class, function ($mock) {
			$mock->expects()->logJobProgress(m::any(), 'Retrieving initial batch of message events', m::any());
			$mock->expects()->getBounceMessageEventTypes()->andReturns(['foo', 'bar']);

			$daysAgo = Carbon::createFromTimestamp(\XF::$time)->subDays(11)->timestamp;

			$responseData = json_decode($this->getMockData('message-events-initial.json'), true);

			$mock->expects()
			     ->getMessageEvents(1, 5, ['foo', 'bar'], $daysAgo, \XF::$time)
			     ->andReturns($responseData);
			$mock->expects()->logJobProgress(m::any(), 'Message events found', ['count' => 7]);
			$mock->expects()->logJobProgress(m::any(), 'Message events stored in database for processing', m::any());
			$mock->expects()->logJobProgress(m::any(), 'Additional message events found', ['uri' => '/api/v1/events/message?cursor=foo&per_page=5']);
		});

		$job = $this->app()->job('Hampel\SparkPostMail:MessageEvent', 103);

		$result = $job->run(8);

		$this->assertEquals(JobResult::class, get_class($result));
		$this->assertFalse($result->completed);
	}

	public function test_job_paging_page2()
	{
		$this->mockRepository('Hampel\SparkPostMail:MessageEvent', function ($mock) {
			$mock->expects()->storeMessageEvent(m::any())->times(2);
			$mock->expects()->setMessageEventCache(m::any(), m::any(), m::any());
		});

		$this->mock('sparkpostmail', SparkPost::class, function ($mock) {
			$mock->expects()->logJobProgress(m::any(), 'Retrieving additional message events', ['uri' => '/api/v1/events/message?cursor=foo&per_page=5']);

			$responseData = json_decode($this->getMockData('message-events-page2.json'), true);

			$mock->expects()->getUri('/api/v1/events/message?cursor=foo&per_page=5')->andReturns($responseData);
			$mock->expects()->logJobProgress(m::any(), 'Message events stored in database for processing', m::any());
			$mock->expects()->logJobProgress(m::any(), "No further events to process - we're done", []);
			$mock->expects()->logJobProgress(m::any(), 'Job complete', m::any());
		});

		$job = $this->app()->job('Hampel\SparkPostMail:MessageEvent', 104, ['uri' => '/api/v1/events/message?cursor=foo&per_page=5']);

		$result = $job->run(8);

		$this->assertEquals(JobResult::class, get_class($result));
		$this->assertTrue($result->completed);
	}

	public function test_job_rate_limited()
	{
		$this->mockRepository('Hampel\SparkPostMail:MessageEvent', function ($mock) {
			$mock->expects()->getLastRun()->andReturns(null);
		});

		$this->mock('sparkpostmail', SparkPost::class, function ($mock) {
			$mock->expects()->logJobProgress(m::any(), 'Retrieving initial batch of message events', m::any());
			$mock->expects()->getBounceMessageEventTypes()->andReturns(['foo', 'bar']);

			$mock->expects()
			     ->getMessageEvents(1, 5, ['foo', 'bar'], m::any(), \XF::$time)
			     ->andThrow(new SparkPostException(new \Exception('rate limited', 429)));
			$mock->expects()->logJobProgress(m::any(), 'API rate limited - sleeping', m::any());
		});

		$job = $this->app()->job('Hampel\SparkPostMail:MessageEvent', 105);

		$result = $job->run(8);

		$this->assertEquals(JobResult::class, get_class($result));
		$this->assertFalse($result->completed);
	}

	public function test_job_exception()
	{
		$this->expectException(SparkPostException::class);

		$this->mockRepository('Hampel\SparkPostMail:MessageEvent', function ($mock) {
			$mock->expects()->getLastRun()->andReturns(null);
		});

		$this->mock('sparkpostmail', SparkPost::class, function ($mock) {
			$mock->expects()->logJobProgress(m::any(), 'Retrieving initial batch of message events', m::any());
			$mock->expects()->getBounceMessageEventTypes()->andReturns(['foo', 'bar']);

			$mock->expects()
			     ->getMessageEvents(1, 5, ['foo', 'bar'], m::any(), \XF::$time)
			     ->andThrow(new SparkPostException(new \Exception('foo', 400)));
		});

		$job = $this->app()->job('Hampel\SparkPostMail:MessageEvent', 106);

		$job->run(8);
	}
}
