<?php namespace Tests\Unit;

use Carbon\Carbon;
use Hampel\SparkPostMail\Api\SparkPostApi;
use Hampel\SparkPostMail\Repository\MessageEventRepository;
use Hampel\SparkPostMail\SubContainer\SparkPost;
use Tests\TestCase;

/**
 * The from/to window SubContainer\SparkPost::getMessageEvents() hands to the API. Both the
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

	/**
	 * @param int|null $lastRun what the cache reports for the previous run
	 * @param int      $expectedFrom the 'from' timestamp the API should be asked for
	 */
	protected function assertWindowFrom(?int $lastRun, int $expectedFrom): void
	{
		// the watermark genuinely lives in the simpleCache, so seed that rather than mocking the
		// repository - this exercises MessageEventRepository::getLastRun() as well
		if ($lastRun !== null)
		{
			$this->app()->repository(MessageEventRepository::class)
				->setMessageEventCache($lastRun, 1, 0.5);
		}

		$types = $this->sp->getBounceMessageEventTypes();

		$this->mock([$this->sp, 'api'], SparkPostApi::class, function ($mock) use ($types, $expectedFrom) {
			$mock->expects()->getMessageEvents(1, 500, $types, $expectedFrom, $this->now);
		});

		$this->sp->getMessageEvents();
	}

	public function test_first_ever_run_reaches_back_eleven_days()
	{
		$this->assertWindowFrom(null, Carbon::createFromTimestamp($this->now)->subDays(11)->timestamp);
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
}
