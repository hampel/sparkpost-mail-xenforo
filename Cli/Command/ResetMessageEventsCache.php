<?php namespace Hampel\SparkPostMail\Cli\Command;

use Hampel\SparkPostMail\Repository\MessageEventRepository;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ResetMessageEventsCache extends AbstractLoggingCommand
{
	protected function configure()
	{
		$this
			->setName('sparkpost:reset-message-events')
			->setDescription('Reset message events cache');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
        \XF::repository(MessageEventRepository::class)->resetMessageEventCache();

        $this->logConsole('notice', 'Message event cache has been reset', 'Reset message event cache');

        return self::SUCCESS;
	}
}