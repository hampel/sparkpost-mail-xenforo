<?php namespace Hampel\SparkPostMail\Job;

use XF\Job\AbstractJob;
use XF\Mvc\Entity\ArrayCollection;
use Hampel\SparkPostMail\EmailBounce\Processor;
use Hampel\SparkPostMail\SubContainer\SparkPost;

class EmailBounce extends AbstractJob
{
	protected $defaultData = [
		'batch' => 100,
	];

	public function run($maxRunTime)
	{
		$start = microtime(true);
		$repository = $this->repository();
		/** @var Processor $processor */
		$processor = $this->sparkpost()->get('bounce');

		/** @var ArrayCollection $events */
		$events = $repository->getUnprocessedMessageEvents($this->data['batch']);
		if (count($events) == 0)
		{
			$this->log('No unprocessed message events found, stopping');
			return $this->complete();
		}

		$this->log('Unprocessed message events found', ['count' => count($events)]);

		$done = 0;

		foreach ($events as $event)
		{
			$processor->parseEvent($event);

			$done++;

			$repository->markMessageEventProcessed($event);

			if (microtime(true) - $start >= $maxRunTime) break;
		}

		$this->data['batch'] = $this->calculateOptimalBatch($this->data['batch'], $done, $start, $maxRunTime, 1000);

		$this->log('Processed message events', ['count' => $done]);
		return $this->resume();
	}

	public function getStatusMessage()
	{
		return \XF::phrase('sparkpostmail_processing_email_bounces...');;
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
	 * @return SparkPost
	 */
	protected function sparkpost()
	{
		return $this->app->get('sparkpostmail');
	}

	/**
	 * @return \Hampel\SparkPost\Repository\MessageEvent
	 */
	protected function repository()
	{
		return $this->app->repository('Hampel\SparkPostMail:MessageEvent');
	}

	protected function log($message, array $context = [])
	{
		$this->sparkpost()->logJobProgress($message, $context);
	}
}
