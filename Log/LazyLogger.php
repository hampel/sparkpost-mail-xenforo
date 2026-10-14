<?php namespace Hampel\SparkPostMail\Log;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * A logger that resolves the real one on first use rather than on construction.
 *
 * This exists to keep the mail transport off the logger's branch of the container.
 * Hampel/Monolog's email handler calls XF\App::mailer() while it builds a channel; the mailer
 * builds its transport by firing mailer_transport_setup; and this add-on answers that event.
 * Resolving the logger while constructing the transport therefore re-enters mailer construction,
 * and XF\Container caches only once a closure returns, so the second pass rebuilds instead of
 * reusing and the recursion never terminates.
 *
 * Deferring the resolution breaks it: by the time anything actually logs, the mailer is built
 * and cached, so the same call returns immediately.
 *
 * Note the PARAMETERS carry no type declarations and the return type is `void`, which is the one
 * signature that satisfies both psr/log versions this add-on will meet. XenForo's own autoloader
 * registers first, so core's copy wins whatever this add-on's lock resolves: 2.3 ships psr/log
 * 1.1.4, where log() is untyped with no return type, and 2.4 will ship 2 or 3 with
 * `log($level, string|\Stringable $message, array $context = []): void`.
 *
 * Widening a parameter is allowed and narrowing is not, so untyped parameters satisfy both.
 * Adding a return type where psr/log 1 has none is also allowed - so `: void` is required for 2.4
 * and harmless on 2.3. Verified against 1.1.4 and 3.0.2.
 */
class LazyLogger extends AbstractLogger
{
	/** @var callable */
	protected $resolver;

	/** @var LoggerInterface|null */
	protected $logger;

	public function __construct(callable $resolver)
	{
		$this->resolver = $resolver;
	}

	public function log($level, $message, array $context = []): void
	{
		if ($this->logger === null)
		{
			$this->logger = ($this->resolver)();
		}

		$this->logger->log($level, $message, $context);
	}
}
