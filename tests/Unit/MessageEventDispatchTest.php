<?php namespace Tests\Unit;

use Hampel\SparkPostMail\EmailBounce\ParsedMessage;
use Hampel\SparkPostMail\Service\MessageEventService;
use Mockery as m;
use Tests\TestCase;
use XF\EmailBounce\Processor;
use XF\SubContainer\Bounce;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Which SparkPost message type reaches which handler, and what gets written to the bounce log.
 *
 * processEvent() returns whatever logBounceMessage() returns, so the action string is only
 * observable through the repository call - these assert on that argument rather than on a
 * return value.
 */
class MessageEventDispatchTest extends TestCase
{
	#[DataProvider('ignoredTypes')]
	public function test_delivery_side_events_are_ignored_without_reaching_a_handler(string $type)
	{
		$this->expectLoggedAction('ignore');

		// no bounce processor and no email stopper are mocked - if dispatch reached either
		// handler for these types the test would error rather than fail
		$this->app()->service(MessageEventService::class)->processEvent($this->event($type));
	}

	public static function ignoredTypes(): array
	{
		return [
			'delivery'            => ['delivery'],
			'injection'           => ['injection'],
			'delay'               => ['delay'],
			'open'                => ['open'],
			'initial_open'        => ['initial_open'],
			'click'               => ['click'],
			'generation_failure'  => ['generation_failure'],
		];
	}

	/**
	 * A type SparkPost adds later is logged rather than silently dropped, which is the only way
	 * anyone would find out it needs handling.
	 */
	public function test_an_unrecognised_message_type_is_logged_as_unknown()
	{
		$this->expectLoggedAction('unknown');

		$this->app()->service(MessageEventService::class)->processEvent($this->event('some_new_event_type'));
	}

	#[DataProvider('bounceTypes')]
	public function test_the_bounce_family_reaches_the_bounce_processor(string $type)
	{
		$processor = m::mock(Processor::class);
		$processor->expects()->takeBounceAction(m::any(), 'hard', m::any())->andReturns('hard');

		$this->mock('bounce', Bounce::class, function ($mock) use ($processor)
		{
			$mock->allows()->processor()->andReturns($processor);
		});

		$this->expectLoggedAction('hard');

		$event = $this->event($type);
		$event->bounceClass = 10; // invalid recipient - a hard bounce

		$this->app()->service(MessageEventService::class)->processEvent($event);
	}

	public static function bounceTypes(): array
	{
		return [
			'bounce'               => ['bounce'],
			'policy_rejection'     => ['policy_rejection'],
			'out_of_band'          => ['out_of_band'],
			'generation_rejection' => ['generation_rejection'],
			'spam_complaint'       => ['spam_complaint'],
		];
	}

	#[DataProvider('unsubscribeTypes')]
	public function test_both_unsubscribe_types_reach_the_email_stopper(string $type)
	{
		$service = $this->app()->service(MessageEventService::class);

		$this->mockService('XF:User\EmailStopService', function ($mock)
		{
			$mock->expects()->stop('thread');
		});

		$this->expectLoggedAction('unsubscribe');

		$event = $this->event($type);
		$event->transactional = true;
		$event->campaign = 'watched_thread_reply';

		$service->processEvent($event);
	}

	public static function unsubscribeTypes(): array
	{
		return [
			'list_unsubscribe' => ['list_unsubscribe'],
			'link_unsubscribe' => ['link_unsubscribe'],
		];
	}

	/**
	 * An event for an address that is not a member is still logged - the bounce log is the record
	 * of what SparkPost said, not only of what was done about it - but with a null user and an
	 * empty action.
	 */
	public function test_an_event_for_an_unknown_recipient_is_logged_with_no_user_and_no_action()
	{
		$this->mockRepository('Hampel\SparkPostMail:MessageEvent', function ($mock)
		{
			$mock->expects()->logBounceMessage(
				m::any(), 'bounce', '', null, 'stranger@example.com', m::any(), m::any(), m::any()
			);
		});

		$event = $this->event('bounce');
		$event->user = null;
		$event->recipient = 'stranger@example.com';

		$this->app()->service(MessageEventService::class)->processEvent($event);
	}

	protected function expectLoggedAction(string $action): void
	{
		$this->mockRepository('Hampel\SparkPostMail:MessageEvent', function ($mock) use ($action)
		{
			$mock->expects()->logBounceMessage(
				m::any(), m::any(), $action, m::any(), m::any(), m::any(), m::any(), m::any()
			);
		});
	}

	protected function event(string $type): ParsedMessage
	{
		$user = $this->app()->em()->create(\XF\Entity\User::class);
		$user->setTrusted('user_id', 1);
		$user->username = 'probe';

		$event = new ParsedMessage();
		$event->user = $user;
		$event->date = \XF::$time;
		$event->messageDate = \XF::$time;
		$event->messageType = $type;
		$event->recipient = 'probe@example.com';
		$event->bounceClass = 0;
		$event->reason = '';
		$event->rawMessage = '{}';
		$event->transactional = false;
		$event->campaign = null;

		return $event;
	}
}
