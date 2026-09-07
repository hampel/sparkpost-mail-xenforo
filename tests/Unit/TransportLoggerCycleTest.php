<?php namespace Tests\Unit;

use Hampel\SparkPostMail\Log\LazyLogger;
use Hampel\SparkPostMail\SubContainer\SparkPost;
use Psr\Log\NullLogger;
use Tests\TestCase;

/**
 * Building the mail transport must not resolve sparkpostmail.log.
 *
 * Hampel/Monolog's email handler calls XF\App::mailer() while it builds a channel, and the
 * mailer builds its transport by firing mailer_transport_setup - which is what asked for the
 * logger. XF\Container caches only after a closure returns, so the second pass through rebuilds
 * rather than reusing, and nothing terminates: production hit
 * "Maximum call stack size reached" on 7 September 2026, an hour after 5.0.0 went out.
 *
 * 4.0.1 could not do this because its transport took only an API key and an HTTP client. The
 * package's SparkPostTransport takes a LoggerInterface, so 5.0.0 put the logger on a branch of
 * the container that the mailer builds, and closed the loop.
 *
 * Neither half of the cycle exists on a development install: monologSendEmail defaults to
 * disabled, so no mail handler is pushed, and a dev board rarely selects SparkPost as its
 * transport. Both have to be true, which is why this shipped.
 */
class TransportLoggerCycleTest extends TestCase
{
	protected function selectSparkPost(): void
	{
		$this->setOptions(['emailTransport' => [
			'emailTransport' => 'sparkpost',
			'apiKey' => 'test-key',
			'testMode' => false,
			'clickTracking' => false,
			'openTracking' => false,
		]]);
	}

	/**
	 * Rebind sparkpostmail.log to behave as Hampel/Monolog's does: resolving it builds the
	 * mailer. Re-entry is counted and cut off, so a regression fails as an assertion rather
	 * than as a stack overflow that takes the whole PHPUnit process with it.
	 *
	 * @param int $depth by-reference counter of how many times the binding was resolved
	 */
	protected function bindMailBuildingLogger(&$depth): void
	{
		$depth = 0;
		$container = $this->app()->container();
		$container->offsetUnset('sparkpostmail.log');

		$app = $this->app();
		$container['sparkpostmail.log'] = function($c) use (&$depth, $app)
		{
			$depth++;

			if ($depth > 2)
			{
				throw new \RuntimeException(
					'sparkpostmail.log was re-entered while the mail transport was being built'
				);
			}

			// what Hampel\Monolog\SubContainer\MonologApi::getMessage() does
			$app->mailer();

			return new NullLogger();
		};
	}

	public function test_building_the_transport_does_not_resolve_the_logger()
	{
		$this->selectSparkPost();
		$this->bindMailBuildingLogger($depth);

		$sub = new SparkPost($this->app()->container(), $this->app());
		$sub->transport();

		$this->assertSame(0, $depth, 'building the transport resolved sparkpostmail.log');
	}

	public function test_building_the_api_client_does_not_resolve_the_logger()
	{
		$this->selectSparkPost();
		$this->bindMailBuildingLogger($depth);

		$sub = new SparkPost($this->app()->container(), $this->app());
		$sub->sparkpost();

		$this->assertSame(0, $depth, 'building the API client resolved sparkpostmail.log');
	}

	public function test_the_lazy_logger_resolves_on_first_use_and_only_once()
	{
		$calls = 0;
		$inner = new class extends \Psr\Log\AbstractLogger {
			public $records = [];
			public function log($level, $message, array $context = [])
			{
				$this->records[] = [$level, $message, $context];
			}
		};

		$logger = new LazyLogger(function() use (&$calls, $inner)
		{
			$calls++;
			return $inner;
		});

		$this->assertSame(0, $calls, 'constructing the lazy logger resolved the real one');

		$logger->warning('first', ['a' => 1]);
		$logger->error('second');

		$this->assertSame(1, $calls, 'the real logger was resolved more than once');
		$this->assertSame(
			[['warning', 'first', ['a' => 1]], ['error', 'second', []]],
			$inner->records
		);
	}
}
