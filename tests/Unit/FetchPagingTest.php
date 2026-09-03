<?php namespace Tests\Unit;

use Hampel\SparkPostMail\Job\FetchMessageEventsJob;
use Hampel\SparkPostMail\SubContainer\SparkPost;
use Mockery as m;
use Tests\Fixture\SequenceClient;
use Tests\TestCase;

/**
 * The fetch job's paging, driven from real captured SparkPost payloads. Before 5.0.0 the job
 * carried links.next in data['uri'] and had to strip the /api/v1 prefix off it by hand; it now
 * carries an opaque cursor and the package resolves the link.
 */
class FetchPagingTest extends TestCase
{
	protected SequenceClient $client;

	public function setUp(): void
	{
		parent::setUp();

		$this->setOptions([
			'emailTransport' => ['emailTransport' => 'sparkpost', 'apiKey' => 'test-key'],
			'sparkpostmailMessageEventsBatchSize' => 5,
		]);
		$this->fakesSimpleCache();

		$this->client = new SequenceClient([
			$this->getMockData('message-events-initial.json'),
			$this->getMockData('message-events-page2.json'),
		]);

		// replace the PSR-18 client inside the sub-container, so the package is exercised for real
		$this->swap([$this->app()->container('sparkpostmail.api'), 'http'], $this->client);
	}

	protected function runJob(array $data = []): \XF\Job\JobResult
	{
		$job = $this->app()->job(FetchMessageEventsJob::class, 1, $data);

		return $job->run(10);
	}

	public function test_the_first_page_stores_its_events_and_resumes_with_a_cursor()
	{
		// note the SHORT name: getRepository() normalises the identifier, so a class name here
		// registers a mock nothing ever looks up
		$this->mockRepository('Hampel\SparkPostMail:MessageEvent', function ($mock)
		{
			$mock->allows()->getLastRun()->andReturns(null);
			$mock->expects()->storeMessageEvent(m::any())->times(5);
		});

		$result = $this->runJob();

		$this->assertFalse($result->completed);
		$this->assertNotEmpty($result->data['cursor']);
		$this->assertEquals(7, $result->data['total_events']);
		$this->assertEquals(5, $result->data['events_stored']);
	}

	public function test_the_cursor_is_followed_and_the_job_completes_on_the_last_page()
	{
		$this->mockRepository('Hampel\SparkPostMail:MessageEvent', function ($mock)
		{
			$mock->allows()->getLastRun()->andReturns(null);
			$mock->allows()->storeMessageEvent(m::any());
			$mock->allows()->setMessageEventCache(m::any(), m::any(), m::any());
		});

		$first = $this->runJob();
		$second = $this->runJob($first->data);

		$this->assertTrue($second->completed);
		// complete() deliberately returns no data; the count is in the status message
		$this->assertStringContainsString('7 events stored', $second->statusMessage);
	}

	/**
	 * links.next comes back as "/api/v1/events/message?cursor=..." - the prefix that used to
	 * need stripping by hand. Config::resolve() handles it, and the proof is that the second
	 * request is a valid absolute url with exactly one /api/v1.
	 */
	public function test_the_next_link_is_resolved_without_doubling_the_api_prefix()
	{
		$this->mockRepository('Hampel\SparkPostMail:MessageEvent', function ($mock)
		{
			$mock->allows()->getLastRun()->andReturns(null);
			$mock->allows()->storeMessageEvent(m::any());
			$mock->allows()->setMessageEventCache(m::any(), m::any(), m::any());
		});

		$this->runJob($this->runJob()->data);

		$this->assertCount(2, $this->client->requestedUris);

		$second = $this->client->requestedUris[1];
		$this->assertStringStartsWith('https://api.sparkpost.com/api/v1/events/message', $second);
		$this->assertEquals(1, substr_count($second, '/api/v1'));
		$this->assertStringContainsString('cursor=foo', $second);
	}
}
