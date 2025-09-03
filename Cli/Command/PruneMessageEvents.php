<?php namespace Hampel\SparkPostMail\Cli\Command;

use Hampel\SparkPostMail\Repository\MessageEventRepository;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PruneMessageEvents extends AbstractLoggingCommand
{
	protected function configure()
	{
		$this
			->setName('sparkpost:prune-message-events')
			->setDescription('Prune processed message events')
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_REQUIRED,
                "Prune message events older than the specified number of days"
            );
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
        $days = $input->getOption('days');

        $repo = \XF::repository(MessageEventRepository::class);

        if ($days)
        {
            $this->logConsole('notice', "Pruning processed message events older than {$days} days");
            $count = $repo->pruneMessageEvents($days);
        }
        else
        {
            $this->logConsole('notice', "Pruning old processed message events");
            $count = $repo->pruneMessageEvents();
        }

        $this->logConsole('notice', "Pruned {$count} message events");

        return self::SUCCESS;
	}
}