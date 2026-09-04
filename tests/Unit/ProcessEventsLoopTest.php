<?php namespace Tests\Unit;

use Hampel\SparkPostMail\Entity\MessageEvent;
use Hampel\SparkPostMail\Service\MessageEventService;
use Mockery as m;
use Tests\TestCase;
use XF\Mvc\Entity\ArrayCollection;

/**
 * The batch loop in processEvents(): what gets marked processed, what is returned, and when it
 * stops.
 *
 * The stopping rule is the part with teeth. The time limit is compared AFTER each event, so the
 * loop always processes at least one, and the default limit of 0 means it processes exactly one -
 * ProcessMessageEventsJob is what makes it useful by passing the job runner's $maxRunTime. A
 * caller that forgets setTimeLimit() gets a working but one-event-at-a-time pipeline, which looks
 * like slowness rather than a bug.
 */
class ProcessEventsLoopTest extends TestCase
{
	public function test_every_event_is_marked_processed_and_saved_within_the_time_limit()
	{
		// mockDatabase() rebuilds the entity manager, which discards any repository mock
		// registered before it - mock the database FIRST or expectAnyLogging() silently does
		// nothing and the real repository runs. fetchAll must return an array rather than null,
		// because rebuilding the EM re-runs the $addonsToLoad listener query through this mock.
		$this->mockDatabase(function ($mock)
		{
			$mock->allows()->fetchAll(m::any())->andReturns([]);
			$mock->allows()->fetchAll(m::any(), m::any())->andReturns([]);
			$mock->shouldIgnoreMissing();
		});
		$this->expectAnyLogging();

		$events = $this->events(3);

		$service = $this->app()->service(MessageEventService::class);
		$service->setEvents(new ArrayCollection($events));
		$service->setTimeLimit(60);

		$this->assertEquals(3, $service->processEvents());
		$this->assertProcessed($events, 3);
	}

	/**
	 * The default time limit stops the loop after the first event, because the check is >= 0 and
	 * any elapsed time satisfies it.
	 */
	public function test_the_default_time_limit_processes_exactly_one_event()
	{
		// mockDatabase() rebuilds the entity manager, which discards any repository mock
		// registered before it - mock the database FIRST or expectAnyLogging() silently does
		// nothing and the real repository runs. fetchAll must return an array rather than null,
		// because rebuilding the EM re-runs the $addonsToLoad listener query through this mock.
		$this->mockDatabase(function ($mock)
		{
			$mock->allows()->fetchAll(m::any())->andReturns([]);
			$mock->allows()->fetchAll(m::any(), m::any())->andReturns([]);
			$mock->shouldIgnoreMissing();
		});
		$this->expectAnyLogging();

		$events = $this->events(3);

		$service = $this->app()->service(MessageEventService::class);
		$service->setEvents(new ArrayCollection($events));

		$this->assertEquals(1, $service->processEvents());
		$this->assertProcessed($events, 1);
	}

	/**
	 * An empty batch is not an error - the job relies on a zero count to decide it is finished.
	 */
	public function test_an_empty_batch_processes_nothing_and_returns_zero()
	{
		$service = $this->app()->service(MessageEventService::class);
		$service->setEvents(new ArrayCollection([]));
		$service->setTimeLimit(60);

		$this->assertEquals(0, $service->processEvents());
	}

	/**
	 * Entity::save() is final, so a Mockery double cannot intercept it - these are real entities
	 * with the database adapter mocked out instead. The insert is what proves the loop saved.
	 *
	 * @param int $count how many events to build
	 */
	protected function events(int $count): array
	{
		$events = [];

		for ($i = 0; $i < $count; $i++)
		{
			// setTrusted, not assignment - setting the primary key normally sends the entity to
			// the finder to check uniqueness, which needs a database this test does not have
			$event = $this->app()->em()->create(MessageEvent::class);
			$event->setTrusted('event_id', str_pad((string) $i, 32, '0', STR_PAD_LEFT));
			$event->setTrusted('type', 'bounce');
			$event->setTrusted('recipient', 'probe@example.com');
			$event->setTrusted('timestamp', \XF::$time);
			// no rcpt_to, so parseEvent finds no user and the event is logged without reaching a
			// handler - this test is about the loop, not about what each event resolves to
			$event->setTrusted('payload', ['bounce_class' => 0]);

			$events[] = $event;
		}

		return $events;
	}

	protected function assertProcessed(array $events, int $expected): void
	{
		$processed = array_filter($events, fn (MessageEvent $e) => $e->processed);

		$this->assertCount($expected, $processed, 'wrong number of events marked processed');
	}

	protected function expectAnyLogging(): void
	{
		$this->mockRepository('Hampel\SparkPostMail:MessageEvent', function ($mock)
		{
			$mock->allows('logBounceMessage');
		});
	}
}
