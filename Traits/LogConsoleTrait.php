<?php namespace Hampel\SparkPostMail\Traits;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerTrait;
use Symfony\Component\Console\Output\OutputInterface;

trait LogConsoleTrait
{
    use LoggerTrait;
    use LoggerAwareTrait;
    use OutputTrait;
    use OutputAwareTrait;
    use ContextAwareTrait;

    /**
     * Any context to be added to logs.
     *
     * @var array
     */
    protected $context = [];

    protected function logConsole($level, $message, $logMessage = null, $context = [])
    {
        $logMessage = $logMessage ?? $message;
        $verbosity = $this->getVerbosity($level);
        $style = $this->getStyle($level);

        $this->log($level, $logMessage, $context);
        $this->line($message, $style, $verbosity);
    }

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

    /**
     * Write a string as standard output.
     *
     * @param  string  $string
     * @param  string|null  $style
     * @param  int|null $verbosity
     * @return void
     */
    public function line($string, ?string $style = null, ?int $verbosity = null) : void
    {
        if ($this->output)
        {
            $this->output->writeln($this->styleString($string), $verbosity);
        }
    }

    public function getDefaultVerbosity() : ?int
    {
        if ($this->output)
        {
            return $this->output->getVerbosity();
        }

        return 0;
    }
}
