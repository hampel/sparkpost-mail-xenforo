<?php namespace Tests\Unit;

use Hampel\SparkPost\MessageEvent\BounceClass;
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
			'70 transient failure'       => [70, 'soft', 'soft'],
			'100 challenge response'     => [100, 'soft', 'soft'],

			// blocked - recorded, but nothing is done to the user
			'50 mail block'              => [50, null, 'block'],
			'51 spam block'              => [51, null, 'block'],
			'52 spam content'            => [52, null, 'block'],
			'53 prohibited attachment'   => [53, null, 'block'],
			'54 relaying denied'         => [54, null, 'block'],

			// informational - the message was delivered and the recipient's system answered
			'60 auto reply'              => [60, null, 'unknown'],
			'80 subscribe'               => [80, null, 'unknown'],

			// no action
			'1 undetermined'             => [1, null, 'unknown'],
			'999 a code we do not know'  => [999, null, 'unknown'],
		];
	}

	/**
	 * processBounce() matches exhaustively over BounceClassification, so a classification the
	 * package adds later is an UnhandledMatchError in the middle of ProcessMessageEventsJob
	 * rather than a compile-time complaint. That is not hypothetical: adding Informational in
	 * hampel/sparkpost 0.4.0 moved AutoReply (60) out of Soft and would have thrown on the
	 * first out-of-office reply. This walks the whole enum so the next one is caught here.
	 */
	public function test_every_bounce_class_the_package_knows_can_be_processed()
	{
		$processor = m::mock(Processor::class);
		$processor->allows()->takeBounceAction(m::any(), m::any(), m::any())->andReturns('hard');

		$this->mock('bounce', Bounce::class, function ($mock) use ($processor)
		{
			$mock->allows()->processor()->andReturns($processor);
		});

		$service = $this->app()->service(MessageEventService::class);

		foreach (BounceClass::cases() as $case)
		{
			$this->assertIsString(
				$service->processBounce($this->event($case->value)),
				"bounce class {$case->value} ({$case->name}) could not be processed"
			);
		}
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
