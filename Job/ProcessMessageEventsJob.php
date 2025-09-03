<?php namespace Hampel\SparkPostMail\Job;

use Hampel\SparkPostMail\Repository\MessageEventRepository;
use Hampel\SparkPostMail\Service\MessageEventService;
use XF\Job\JobResult;
use XF\Mvc\Entity\ArrayCollection;

class ProcessMessageEventsJob extends AbstractLoggingJob
{
	protected $defaultData = [
		'batch' => 100,
        'run_count' => 0,
        'run_time' => 0,
        'events_processed' => 0,
        'total_events' => 0,
	];

	public function run($maxRunTime)
	{
		$start = microtime(true);

		/** @var ArrayCollection $events */
		$events = $this->app->repository(MessageEventRepository::class)
            ->getUnprocessedMessageEvents($this->data['batch']);

		if ($events->count() == 0)
		{
            $this->logWorkDone($start);

			$this->debug('No unprocessed message events found, stopping');
			return $this->complete();
		}

		$this->info('Unprocessed message events found', ['count' => $events->count()]);

		$eventProcessor = $this->app->service(MessageEventService::class);
        $eventProcessor->setEvents($events);
        $eventProcessor->setTimeLimit($maxRunTime);
        $count = $eventProcessor->processEvents();

        $this->data['events_processed'] = $count;
        $this->data['total_events'] = $this->data['total_events'] + $count;
		$this->data['batch'] = $this->calculateOptimalBatch($this->data['batch'], $count, $start, $maxRunTime, 1000);

        $this->logWorkDone($start);

		$this->info('Processed message events', ['count' => $count]);
		return $this->resume();
	}

	public function getStatusMessage()
	{
		$action = \XF::phrase('sparkpostmail_processing_message_events...');

        return sprintf('%s #%d: (%d/%d) events processed', $action, $this->data['run_count'], $this->data['events_processed'], $this->data['total_events']);
	}

	public function canCancel()
	{
		return false;
	}

	public function canTriggerByChoice()
	{
		return true;
	}

    /**
     * @param $start
     */
    protected function logWorkDone($start)
    {
        // increment how much work we've done for future batch size calculations
        $this->data['run_count']++;
        $this->data['run_time'] += (microtime(true) - $start);
    }

    public function complete() : JobResult
    {
        $this->info('Job complete', [
            'events_processed' => $this->data['events_processed'],
            'run_count' => $this->data['run_count'],
            'run_time' => $this->data['run_time'],
        ]);

        return JobResult::newComplete($this->jobId, [], sprintf("Job complete: %d events processed", $this->data['total_events']));
    }
}
