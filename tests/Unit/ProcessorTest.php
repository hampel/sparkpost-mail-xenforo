<?php namespace Tests\Unit;

use Hampel\SparkPostMail\SubContainer\SparkPost;
use Mockery as m;
use Tests\TestCase;
use XF\EmailBounce\Processor;

class ProcessorTest extends TestCase
{
	/** @var SparkPost */
	protected $sp;

	public function setUp(): void
	{
		parent::setUp();

		$this->sp = $this->app->get('sparkpostmail');
	}

    public function test_parseEvent_no_recipient_logs_bounce_message()
    {
    	$event = $this->getMessageEvent('bounce', null, null, false);

		$this->mockRepository('Hampel\SparkPostMail:EmailBounce', function ($mock)
		{
			$mock->expects()->logBounceMessage(0, 'bounce', '', null, null, m::any(), 0, '');
		});

		$this->sp->get('bounce')->parseEvent($event);
    }

    public function test_parseEvent_unknown_message_type()
    {
    	$event = $this->getMessageEvent();

	    $this->getUser();

	    $this->mockRepository('Hampel\SparkPostMail:EmailBounce', function ($mock)
		{
			$mock->expects()->logBounceMessage(0, 'bounce', 'unknown', null, 'foo@example.com', m::any(), 0, '');
		});

		$this->sp->get('bounce')->parseEvent($event);
    }

    public function test_parseEvent_bounce_hard()
    {
    	$event = $this->getMessageEvent('bounce', 10);

		$this->mock([$this->app['bounce'], 'processor'], Processor::class, function ($mock) {
			$mock->expects()->takeBounceAction($this->getUser(), 'hard', m::any())->once()->andReturns('hard');
		});

		$this->mockRepository('Hampel\SparkPostMail:EmailBounce', function ($mock)
		{
			$mock->expects()->logBounceMessage(0, 'bounce', 'hard', null, 'foo@example.com', m::any(), 10, '');
		});

		$this->sp->get('bounce')->parseEvent($event);
    }

    public function test_parseEvent_bounce_block()
    {
    	$event = $this->getMessageEvent('bounce', 50);

		$this->getUser();

		$this->mock([$this->app['bounce'], 'processor'], Processor::class, function ($mock) {
			$mock->expects()->takeBounceAction()->never();
		});

		$this->mockRepository('Hampel\SparkPostMail:EmailBounce', function ($mock)
		{
			$mock->expects()->logBounceMessage(0, 'bounce', 'block', null, 'foo@example.com', m::any(), 50, '');
		});

		$this->sp->get('bounce')->parseEvent($event);
    }

    public function test_parseEvent_bounce_soft()
    {
    	$event = $this->getMessageEvent('bounce', 20);

		$this->mock([$this->app['bounce'], 'processor'], Processor::class, function ($mock) {
			$mock->expects()->takeBounceAction($this->getUser(), 'soft', m::any())->once()->andReturns('soft');
		});

		$this->mockRepository('Hampel\SparkPostMail:EmailBounce', function ($mock)
		{
			$mock->expects()->logBounceMessage(0, 'bounce', 'soft', null, 'foo@example.com', m::any(), 20, '');
		});

		$this->sp->get('bounce')->parseEvent($event);
    }

    public function test_parseEvent_bounce_soft_hard()
    {
    	$event = $this->getMessageEvent('bounce', 22);

		$this->mock([$this->app['bounce'], 'processor'], Processor::class, function ($mock) {
			$mock->expects()->takeBounceAction($this->getUser(), 'soft', m::any())->once()->andReturns('soft_hard');
		});

		$this->mockRepository('Hampel\SparkPostMail:EmailBounce', function ($mock)
		{
			$mock->expects()->logBounceMessage(0, 'bounce', 'soft_hard', null, 'foo@example.com', m::any(), 22, '');
		});

		$this->sp->get('bounce')->parseEvent($event);
    }

    public function test_parseEvent_unsubscribe_transactional_no_campaign()
    {
    	$event = $this->getMessageEvent('list_unsubscribe', null, null, true, true);

	    $this->getUser();

		$this->mockService('XF:User\EmailStop', function ($mock) {
			$mock->expects()->stopAll()->once();
		});

		$this->mockRepository('Hampel\SparkPostMail:EmailBounce', function ($mock)
		{
			$mock->expects()->logBounceMessage(0, 'list_unsubscribe', 'unsubscribe', null, 'foo@example.com', m::any(), 0, '');
		});

		$this->sp->get('bounce')->parseEvent($event);
    }

    public function test_parseEvent_unsubscribe_transactional_with_campaign()
    {
    	$event = $this->getMessageEvent('list_unsubscribe', null, 'watched_thread', true, true);

	    $this->getUser();

		$this->mockService('XF:User\EmailStop', function ($mock) {
			$mock->expects()->stop('thread')->once();
		});

		$this->mockRepository('Hampel\SparkPostMail:EmailBounce', function ($mock)
		{
			$mock->expects()->logBounceMessage(0, 'list_unsubscribe', 'unsubscribe', null, 'foo@example.com', m::any(), 0, '');
		});

		$this->sp->get('bounce')->parseEvent($event);
    }

    public function test_parseEvent_unsubscribe_nontransactional_no_campaign()
    {
    	$event = $this->getMessageEvent('list_unsubscribe', null, null);

	    $this->getUser();

		$this->mockService('XF:User\EmailStop', function ($mock) {
			$mock->expects()->stopMailingList()->once();
		});

		$this->mockRepository('Hampel\SparkPostMail:EmailBounce', function ($mock)
		{
			$mock->expects()->logBounceMessage(0, 'list_unsubscribe', 'unsubscribe', null, 'foo@example.com', m::any(), 0, '');
		});

		$this->sp->get('bounce')->parseEvent($event);
    }

    public function test_parseEvent_unsubscribe_nontransactional_with_campaign()
    {
    	$event = $this->getMessageEvent('list_unsubscribe', null, 'prepared_email');

	    $this->getUser();

		$this->mockService('XF:User\EmailStop', function ($mock) {
			$mock->expects()->stop('list')->once();
		});

		$this->mockRepository('Hampel\SparkPostMail:EmailBounce', function ($mock)
		{
			$mock->expects()->logBounceMessage(0, 'list_unsubscribe', 'unsubscribe', null, 'foo@example.com', m::any(), 0, '');
		});

		$this->sp->get('bounce')->parseEvent($event);
    }

    // -----------------------------------------------------------

	protected function getMessageEvent($type = 'bounce', $bounce_class = null, $campaign_id = null, $includeReceipent = true, $transactional = false)
	{
    	$event = $this->app->em()->create('Hampel\SparkPostMail:MessageEvent');
    	$event->event_id = 'foo';
		$event->type = $type;
		$event->recipient = 'foo@example.com';
		$event->timestamp = \XF::$time;
		$event->payload = [
			'event_id' => 'foo',
			'type' => $type,
			'bounce_class' => $bounce_class,
			'recipient' => 'foo@example.com',
			'campaign_id' => $campaign_id,
			'timestamp' => \XF::$time,
			'rcpt_to' => $includeReceipent ? 'foo@example.com' : null,
			'transactional' => $transactional,
		];

		$event->setReadOnly(true);

		return $event;
	}

	protected function getUser()
	{
		$user = $this->app->repository('XF:User')->setupBaseUser();
		$user->setReadOnly(true);

		$this->mockFinder('XF:User', function ($mock) use ($user) {
			$mock->expects()->where(['email' => 'foo@example.com']);
			$mock->expects()->fetchOne()->andReturns($user);
		});

		return $user;
	}
}
