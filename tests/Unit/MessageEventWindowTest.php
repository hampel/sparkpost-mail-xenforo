<?php namespace Tests\Unit;

use Hampel\SparkPostMail\Repository\MessageEventRepository;
use Hampel\SparkPostMail\SubContainer\SparkPost;
use Tests\TestCase;

/**
 * The from/to window SubContainer\SparkPost::buildEventQuery() asks the API for. Both the
 * cold-start reach-back and the minimum window width have been the subject of bugfixes.
 */
class MessageEventWindowTest extends TestCase
{
	protected int $now = 1756800000;

	protected SparkPost $sp;

	public function setUp(): void
	{
		parent::setUp();

		$this->setTestTime($this->now);
		$this->setOptions(['sparkpostmailMessageEventsBatchSize' => 500]);
		$this->fakesSimpleCache();

		$this->sp = $this->app()->container('sparkpostmail.api');
	}

	protected function queryFrom(?int $lastRun): array
	{
		// the watermark genuinely lives in the simpleCache, so seed that rather than mocking the
		// repository - this exercises MessageEventRepository::getLastRun() as well
		if ($lastRun !== null)
		{
			$this->app()->repository(MessageEventRepository::class)->setMessageEventCache($lastRun, 1, 0.5);
		}

		return $this->sp->buildEventQuery()->toArray();
	}

	protected function assertWindowFrom(?int $lastRun, int $expectedFrom): void
	{
		$expected = (new \DateTimeImmutable())->setTimestamp($expectedFrom)->format('Y-m-d\TH:i');

		$this->assertEquals($expected, $this->queryFrom($lastRun)['from']);
	}

	public function test_first_ever_run_reaches_back_eleven_days()
	{
		$this->assertWindowFrom(null, $this->now - (86400 * 11));
	}

	public function test_subsequent_run_starts_from_the_last_run()
	{
		$this->assertWindowFrom($this->now - 1800, $this->now - 1800);
	}

	/**
	 * SparkPost rejects a window narrower than a minute. Regression for the 2.1.4 bugfix.
	 */
	public function test_a_last_run_inside_the_minimum_window_is_widened_to_sixty_seconds()
	{
		$this->assertWindowFrom($this->now - 10, $this->now - 60);
	}

	public function test_a_last_run_in_the_future_is_widened_to_sixty_seconds()
	{
		$this->assertWindowFrom($this->now + 3600, $this->now - 60);
	}

	public function test_a_last_run_exactly_sixty_seconds_ago_is_left_alone()
	{
		$this->assertWindowFrom($this->now - 60, $this->now - 60);
	}

	public function test_the_window_ends_now()
	{
		$expected = (new \DateTimeImmutable())->setTimestamp($this->now)->format('Y-m-d\TH:i');

		$this->assertEquals($expected, $this->queryFrom(null)['to']);
	}

	public function test_the_batch_size_option_sets_per_page()
	{
		$this->assertEquals(500, $this->queryFrom(null)['per_page']);
	}

	public function test_only_the_bounce_event_types_are_requested()
	{
		$expected = implode(',', array_map(fn($t) => $t->value, $this->sp->getBounceMessageEventTypes()));

		$this->assertEquals($expected, $this->queryFrom(null)['events']);
		$this->assertStringContainsString('bounce', $this->queryFrom(null)['events']);
		$this->assertStringNotContainsString('delivery', $this->queryFrom(null)['events']);
	}
}
