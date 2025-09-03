<?php namespace Hampel\SparkPostMail\Traits;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerTrait;

trait LogTrait
{
    use LoggerTrait;
    use LoggerAwareTrait;
    use ContextAwareTrait;

    /**
     * Implement the log function from LoggerTrait
     *
     * @param $level
     * @param $message
     * @param array $context
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        if ($this->logger)
        {
            $context = array_merge($this->context, $context);
            $this->logger->log($level, $message, $context);
        }
    }
}
