<?php namespace Hampel\SparkPostMail\Job;

use Carbon\Carbon;
use XF\Job\AbstractJob;
use Hampel\SparkPostMail\SubContainer\SparkPost;
use Hampel\SparkPostMail\Option\MessageEventsBatchSize;

class MessageEvent extends AbstractJob
{
	protected $defaultData = [
		'uri' => null,
		'query_start' => null,
		'run_count' => 0,
		'run_time' => 0,
	];

	public function run($maxRunTime)
	{
		$start = microtime(true);
		$sp = $this->sparkpost();
		$repository = $this->repository();

		try
		{
			// save the start time for this query so we know what to use for the "from" time next time we run
			$this->data['query_start'] = $this->data['query_start'] ?? \XF::$time;

			if (empty($this->data['uri']))
			{
				$from = $this->getFromTime();
				if ($from >= $this->data['query_start']) $from = $this->data['query_start'] - 60; // sanity checking, start time should not be later than end time

				$this->log("Retrieving initial batch of message events", ['from' => $from, 'from_string' => $this->timestampToDateString($from)]);

				// first call - need to set parameters
				$body = $sp->getMessageEvents(
					1,
					MessageEventsBatchSize::get(),
					$sp->getBounceMessageEventTypes(),
					$from,
					\XF::$time // now
				);
			}
			else
			{
				$uri = $this->data['uri'];
				$this->log("Retrieving additional message events", ['uri' => $uri]);

				// subsequent calls - just use uri
				$body = $sp->getUri($uri);
			}

			if (isset($body['total_count']) && !isset($this->data['uri']))
			{
				// first run through - log how many messages we found
				$this->log("Message events found", ['count' => $body['total_count']]);
			}

			if (empty($body['results']))
			{
				$this->logWorkDone($start);

				$this->log("No data returned from query");

				// didn't get any data back - stop now
				return $this->complete();
			}

			// store the message events in the database for processing
			array_walk($body['results'], function ($event) use ($repository) {
				// only bother if we've got a user to match against, otherwise there's no point
				if (isset($event['rcpt_to']))
				{
					$repository->storeMessageEvent($event);
				}
			});

			$this->log("Message events stored in database for processing");

			$this->logWorkDone($start);

			if (isset($body['links']))
			{
				if (!isset($body['links']['next']))
				{
					$this->log("No further events to process - we're done");

					// we're done
					return $this->complete();
				}

				$this->log("Additional message events found", ['uri' => $body['links']['next']]);

				// next link found - resume processing
				$this->data['uri'] = $body['links']['next'];
				return $this->resume();
			}

		}
		catch (\SparkPost\SparkPostException $e)
		{
			if ($e->getCode() == 429)
			{
				// rate limited!
				return $this->rateLimited();
			}
			else
			{
				// rethrow exception to let someone else handle it
				throw $e;
			}
		}

		// really shouldn't get to here
		return $this->complete();
	}

	public function getStatusMessage()
	{
		return \XF::phrase('sparkpostmail_fetching_message_events...');;
	}

	public function canCancel()
	{
		return false;
	}

	public function canTriggerByChoice()
	{
		return true;
	}

	protected function getFromTime()
	{
		$last_run = $this->repository()->getLastRun();

		// if we've run before, use that as start date, otherwise just go back 11 days and get everything
		return $last_run ?? $this->daysAgo(11);
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

	protected function rateLimited()
	{
		// delay for 2 minutes before running again
		$continueDate = Carbon::now()->addMinutes(2);
		$this->log("API rate limited - sleeping", [
			'uri' => $this->data['uri'],
			'continueDate' => $this->dateString($continueDate)
		]);

		return $this->resumeLater($continueDate->timestamp);
	}

	public function resumeLater($timestamp)
    {
        $job = $this->resume();
        $job->continueDate = $timestamp;

        return $job;
    }

	public function complete()
	{
		$this->log('Job complete', [
			'started' => $this->timestampToDateString($this->data['query_start']),
			'run_count' => $this->data['run_count'],
			'run_time' => $this->data['run_time'],
		]);
		$this->repository()->setMessageEventCache($this->data['query_start'], $this->data['run_count'], $this->data['run_time']);

		return parent::complete();
	}

	protected function daysAgo($days)
	{
		return Carbon::createFromTimestamp(\XF::$time)->subDays($days)->timestamp;
	}

	protected function timestampToDateString($timestamp)
	{
		return $this->dateString(Carbon::createFromTimestamp($timestamp));
	}

	protected function dateString(Carbon $date)
	{
		return $date->format("Y-m-d H:i:sP");
	}

	/**
	 * @return SparkPost
	 */
	protected function sparkpost()
	{
		return $this->app->get('sparkpostmail');
	}

	/**
	 * @return \Hampel\SparkPostMail\Repository\MessageEvent
	 */
	protected function repository()
	{
		return $this->app->repository('Hampel\SparkPostMail:MessageEvent');
	}

	protected function log($message, array $context = [])
	{
		$this->sparkpost()->logJobProgress($this, $message, $context);
	}
}
