<?php namespace Tests\Unit;

use Hampel\SparkPost\MessageEvent\BounceClass;
use Hampel\SparkPost\MessageEvent\BounceClassification;
use Hampel\SparkPostMail\EmailBounce\ParsedMessage;
use Hampel\SparkPostMail\Service\MessageEventService;
use Mockery as m;
use Tests\TestCase;
use XF\EmailBounce\Processor;
use XF\SubContainer\Bounce;
use PHPUnit\Framework\Attributes\DataProvider;

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
	 */
	#[DataProvider('bounceClasses')]
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
	 * Every code the package knows must come back with something, which is what rules out the
	 * 0.4.0 failure: adding Informational moved AutoReply (60) out of Soft and the match had no
	 * arm for it, so the first out-of-office reply would have thrown UnhandledMatchError in the
	 * middle of ProcessMessageEventsJob. A default arm now absorbs that instead, so this
	 * walk no longer detects a NEW classification on its own - see the coverage test below.
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

	/**
	 * The signal that replaces the UnhandledMatchError.
	 *
	 * The default arm in processBounce() stops a new classification fatalling inside the job,
	 * which necessarily means it also absorbs one quietly. hampel/sparkpost states that it adds
	 * enum cases in a MINOR version, so one can arrive through an ordinary composer update. This
	 * fails at CI, on the bump, naming the decision that has to be made - which is the only place
	 * the question can be answered, because production's answer has to be "do nothing".
	 */
	public function test_every_bounce_classification_is_explicitly_mapped()
	{
		$this->assertSame(
			['hard', 'soft', 'block', 'admin', 'informational', 'undetermined'],
			array_map(fn (BounceClassification $case) => $case->value, BounceClassification::cases()),
			"hampel/sparkpost has changed BounceClassification. processBounce() matches over it and "
			. "falls through to its default arm for any case not listed here, taking no action against "
			. "the user. Decide what the new classification should do, add an arm for it, and update "
			. "this list."
		);
	}

	/**
	 * What the default arm does when it is reached. The classification cannot be faked - it is an
	 * enum case the package owns - so this calls the handler directly rather than pretending to
	 * have a case that does not exist.
	 */
	public function test_an_unmapped_classification_takes_no_action_and_warns()
	{
		$logger = new class extends \Psr\Log\AbstractLogger {
			public array $records = [];

			public function log($level, $message, array $context = []): void
			{
				$this->records[] = [$level, $message, $context];
			}
		};

		$this->swap('sparkpostmail.log', $logger);

		$service = $this->app()->service(MessageEventService::class);

		$method = new \ReflectionMethod($service, 'unmappedClassification');
		$method->setAccessible(true);

		$this->assertNull(
			$method->invoke($service, BounceClass::cases()[0]),
			'an unmapped classification must take no action against the user'
		);

		$this->assertCount(1, $logger->records, 'an unmapped classification must not be silent');
		$this->assertSame('warning', $logger->records[0][0]);
		$this->assertSame(
			'Unmapped SparkPost bounce classification - taking no action',
			$logger->records[0][1]
		);
		$this->assertArrayHasKey('classification', $logger->records[0][2]);
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
