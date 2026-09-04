<?php namespace Tests\Unit;

use Hampel\SparkPostMail\Job\ProcessMessageEventsJob;
use Hampel\SparkPostMail\Service\MessageEventService;
use Mockery as m;
use Tests\TestCase;
use XF\Job\JobResult;
use XF\Mvc\Entity\ArrayCollection;

/**
 * How ProcessMessageEventsJob sizes the next run.
 *
 * The arithmetic itself is XenForo's — `AbstractJob::calculateOptimalBatch()` — and is
 * deliberately NOT asserted here, because pinning core's formula would make this suite fail on
 * an XF upgrade for something that is not this add-on's behaviour. What is the add-on's, and
 * what these cover, is everything around the call: which batch size is asked for, the 1000 cap
 * passed as the ceiling, that the result is carried forward in the job data, and that an empty
 * batch completes instead of resuming.
 */
class ProcessBatchSizingTest extends TestCase
{
	/**
	 * The first run has no stored batch, so $defaultData supplies it. Getting this wrong is
	 * invisible in a job result - it only shows up as the size of the first query.
	 */
	public function test_the_first_run_asks_for_the_default_batch_of_100()
	{
		$this->expectUnprocessed(100, 3);
		$this->expectProcessed(3);

		$this->runJob();
	}

	public function test_a_stored_batch_size_is_what_the_next_run_asks_for()
	{
		$this->expectUnprocessed(250, 3);
		$this->expectProcessed(3);

		$this->runJob(['batch' => 250]);
	}

	/**
	 * A run that finishes well inside its time budget would otherwise scale the batch up without
	 * limit — calculateOptimalBatch divides the work done by the fraction of time spent, and that
	 * fraction is tiny here. The 1000 passed at the call site is what stops it, and it is the
	 * add-on's number rather than a XenForo default.
	 */
	public function test_a_fast_run_is_capped_at_the_thousand_event_ceiling()
	{
		$this->expectUnprocessed(100, 3);
		$this->expectProcessed(3);

		$result = $this->runJob();

		$this->assertEquals(1000, $result->data['batch'], 'the batch ceiling passed at the call site');
	}

	/**
	 * The counters are what getStatusMessage() reports and what the next run's sizing reads, so
	 * events_processed is per-run and total_events accumulates across runs.
	 */
	public function test_the_run_and_total_counters_accumulate_separately()
	{
		$this->expectUnprocessed(100, 4);
		$this->expectProcessed(4);

		$result = $this->runJob(['total_events' => 10, 'run_count' => 2]);

		$this->assertEquals(4, $result->data['events_processed'], 'events_processed is this run only');
		$this->assertEquals(14, $result->data['total_events'], 'total_events accumulates');
		$this->assertEquals(3, $result->data['run_count'], 'run_count incremented');
	}

	/**
	 * Nothing left to process is how the job ends. It must not resume, or the cron entry queues a
	 * job that can never finish.
	 */
	public function test_an_empty_batch_completes_rather_than_resuming()
	{
		$this->expectUnprocessed(100, 0);

		// the service is never resolved for an empty batch - mocking the factory would replace
		// the job's own dependencies too, so this simply asserts nothing was processed
		$result = $this->runJob(['total_events' => 7]);

		$this->assertTrue($result->completed, 'an empty batch must not resume');
		$this->assertStringContainsString('7 events processed', $result->statusMessage);
	}

	public function test_a_non_empty_batch_resumes()
	{
		$this->expectUnprocessed(100, 1);
		$this->expectProcessed(1);

		$this->assertFalse($this->runJob()->completed, 'there may be more events waiting');
	}

	protected function runJob(array $data = []): JobResult
	{
		return $this->app()->job(ProcessMessageEventsJob::class, 1, $data)->run(10);
	}

	/**
	 * @param int $expectedBatch the batch size the job should ask the repository for
	 * @param int $returnCount   how many events the repository should hand back
	 */
	protected function expectUnprocessed(int $expectedBatch, int $returnCount): void
	{
		$events = new ArrayCollection(array_fill(0, $returnCount, m::mock()));

		$this->mockRepository('Hampel\SparkPostMail:MessageEvent', function ($mock) use ($expectedBatch, $events)
		{
			$mock->expects()->getUnprocessedMessageEvents($expectedBatch)->andReturns($events);
		});
	}

	protected function expectProcessed(int $count): void
	{
		// the FULL class name after the colon - 'Hampel\SparkPostMail:MessageEvent' resolves to
		// Service\MessageEvent, which does not exist, and Mockery will happily mock a class that
		// is not there rather than complain
		$this->mockService('Hampel\SparkPostMail:MessageEventService', function ($mock) use ($count)
		{
			$mock->allows('setEvents');
			$mock->expects()->setTimeLimit(10);
			$mock->expects()->processEvents()->andReturns($count);
		});
	}
}
