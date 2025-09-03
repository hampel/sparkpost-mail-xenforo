<?php namespace Hampel\SparkPostMail\Cli\Command;

use Hampel\SparkPostMail\Job\FetchMessageEventsJob;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\JobRunnerTrait;

class FetchMessageEvents extends AbstractLoggingCommand
{
    use JobRunnerTrait;

	protected function configure()
	{
		$this
			->setName('sparkpost:fetch-message-events')
			->setDescription('Fetch message events from SparkPost API');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
        $this->logConsole('notice', 'Fetching message events');

        $this->setupAndRunJob(
            'SparkPostMailFetchMessageEvents',
            FetchMessageEventsJob::class,
            [],
            $output
        );

        return self::SUCCESS;
	}
}