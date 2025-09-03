<?php namespace Hampel\SparkPostMail\Cli\Command;

use Hampel\SparkPostMail\Traits\ApiAwareTrait;
use Hampel\SparkPostMail\Traits\LogConsoleTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\AbstractCommand;
use XF\PrintableException;

abstract class AbstractLoggingCommand extends AbstractCommand
{
    use LogConsoleTrait;
    use ApiAwareTrait;

    /**
     * Initializes the command after the input has been bound and before the input
     * is validated.
     *
     * This is mainly useful when a lot of commands extends one main command
     * where some things need to be initialized based on the input arguments and options.
     *
     * @see InputInterface::bind()
     * @see InputInterface::validate()
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        // set up our logging
        $this->output = $output;
        $this->setLogger(\XF::app()->container('sparkpostmail.log'));
        $this->setContext(['command' => $this->getName()]);
        $this->setApi(\XF::app()->container('sparkpostmail.api'));
    }

    public function fail(\Throwable|string|null $exception = null)
    {
        if (is_string($exception))
        {
            $this->logConsole('error', $exception);
            throw new PrintableException($exception);
        }
        elseif ($exception instanceof \Throwable)
        {
            $this->logConsole('error', $exception->getMessage());
            throw new PrintableException($exception->getMessage());
        }
    }
}
