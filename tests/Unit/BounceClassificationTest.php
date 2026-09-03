<?php namespace Tests\Unit;

use Hampel\SparkPostMail\EmailBounce\ParsedMessage;
use Hampel\SparkPostMail\Service\MessageEventService;
use Mockery as m;
use Tests\TestCase;
use XF\EmailBounce\Processor;
use XF\SubContainer\Bounce;

/**
 * Which SparkPost bounce class results in which action against the user. SparkPost owns the
 * class-to-classification table (it comes from the package now); this add-on owns the decision
 * about what to do with each classification, and that is what these pin.
 */
class BounceClassificationTest extends TestCase
{
	/**
	 * @param int         $bounceClass  the code SparkPost sent
	 * @param string|null $expectedType the bounce type XF should be told about, or null for no action
	 * @param string      $expectedResult what processBounce reports for the bounce log
	 *
	 * @dataProvider bounceClasses
	 */
	public function test_the_action_taken_for_each_bounce_class(int $bounceClass, ?string $expectedType, string $expectedResult)
	{
		$processor = m::mock(Processor::class);

		if ($expectedType === null)
		{
			$processor->shouldNotReceive('takeBounceAction');
		}
		else
		{
			$processor->expects()->takeBounceAction(m::any(), $expectedType, m::any())->andReturns($expectedType);
		}

		$this->mock('bounce', Bounce::class, function ($mock) use ($processor)
		{
			$mock->allows()->processor()->andReturns($processor);
		});

		$result = $this->app()->service(MessageEventService::class)->processBounce($this->event($bounceClass));

		$this->assertEquals($expectedResult, $result);
	}

	public static function bounceClasses(): array
	{
		return [
			// hard - the address is not deliverable
			'10 invalid recipient'       => [10, 'hard', 'hard'],
			'30 generic bounce no rcpt'  => [30, 'hard', 'hard'],
			'90 unsubscribe'             => [90, 'hard', 'hard'],

			// admin, but treated as hard
			'25 admin failure'           => [25, 'hard', 'hard'],
			'26 smart send suppression'  => [26, 'hard', 'hard'],

			// soft - retryable
			'20 soft bounce'             => [20, 'soft', 'soft'],
			'21 dns failure'             => [21, 'soft', 'soft'],
			'22 mailbox full'            => [22, 'soft', 'soft'],
			'23 too large'               => [23, 'soft', 'soft'],
			'24 timeout'                 => [24, 'soft', 'soft'],
			'40 generic bounce'          => [40, 'soft', 'soft'],
			'60 auto reply'              => [60, 'soft', 'soft'],
			'70 transient failure'       => [70, 'soft', 'soft'],
			'100 challenge response'     => [100, 'soft', 'soft'],

			// blocked - recorded, but nothing is done to the user
			'50 mail block'              => [50, null, 'block'],
			'51 spam block'              => [51, null, 'block'],
			'52 spam content'            => [52, null, 'block'],
			'53 prohibited attachment'   => [53, null, 'block'],
			'54 relaying denied'         => [54, null, 'block'],

			// no action
			'1 undetermined'             => [1, null, 'unknown'],
			'999 a code we do not know'  => [999, null, 'unknown'],
		];
	}

	/**
	 * Subscribe is classified 'admin' by SparkPost exactly as admin_failure and
	 * smart_send_suppression are, but it is not a delivery failure. Mapping the classification
	 * straight through would stop email for a user who had just subscribed.
	 */
	public function test_subscribe_is_admin_class_but_takes_no_action()
	{
		$processor = m::mock(Processor::class);
		$processor->shouldNotReceive('takeBounceAction');

		$this->mock('bounce', Bounce::class, function ($mock) use ($processor)
		{
			$mock->allows()->processor()->andReturns($processor);
		});

		$this->assertEquals('unknown', $this->app()->service(MessageEventService::class)->processBounce($this->event(80)));
	}

	protected function event(int $bounceClass): ParsedMessage
	{
		$user = $this->app()->em()->create(\XF\Entity\User::class);
		$user->setTrusted('user_id', 1);
		$user->username = 'probe';

		$event = new ParsedMessage();
		$event->user = $user;
		$event->date = \XF::$time;
		$event->bounceClass = $bounceClass;
		$event->messageType = 'bounce';

		return $event;
	}
}
