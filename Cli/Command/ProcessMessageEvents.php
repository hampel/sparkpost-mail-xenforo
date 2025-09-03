<?php namespace Hampel\SparkPostMail\Cli\Command;

use Hampel\SparkPostMail\Job\ProcessMessageEventsJob;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\JobRunnerTrait;

class ProcessMessageEvents extends AbstractLoggingCommand
{
    use JobRunnerTrait;

	protected function configure()
	{
		$this
			->setName('sparkpost:process-message-events')
			->setDescription('Process message events');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
        $this->logConsole('notice', 'Processing message events');

        $this->setupAndRunJob(
            'SparkPostMailProcessMessageEvents',
            ProcessMessageEventsJob::class,
            [],
            $output
        );

        return self::SUCCESS;
	}
}