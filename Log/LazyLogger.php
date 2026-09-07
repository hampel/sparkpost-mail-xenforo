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
 * Note the signature carries no type declarations. XenForo's own autoloader registers first and
 * it ships psr/log 1.1.4, whose LoggerInterface::log() is untyped and has no return type - so
 * that is the contract this must match, whatever the add-on's own lock resolves.
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

	public function log($level, $message, array $context = [])
	{
		if ($this->logger === null)
		{
			$this->logger = ($this->resolver)();
		}

		$this->logger->log($level, $message, $context);
	}
}
