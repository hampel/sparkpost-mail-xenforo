<?php namespace Hampel\SparkPostMail\Job;

use Carbon\Carbon;
use Hampel\SparkPostMail\Exception\SparkPostException;
use Hampel\SparkPostMail\Repository\MessageEventRepository;
use XF\Job\JobResult;

class FetchMessageEventsJob extends AbstractLoggingJob
{
	protected $defaultData = [
		'uri' => null,
		'query_start' => null,
		'run_count' => 0,
		'run_time' => 0,
        'total_events' => 0,
        'events_stored' => 0,
	];

	public function run($maxRunTime)
	{
		$start = microtime(true);
		$repository = $this->repository();

		try
		{
			// save the start time for this query so we know what to use for the "from" time next time we run
			$this->data['query_start'] = $this->data['query_start'] ?? \XF::$time;

			if (empty($this->data['uri']))
			{
				// first call - need to set parameters
				$body = $this->api->getMessageEvents();
			}
			else
			{
				$uri = $this->data['uri'];

				// subsequent calls - just use uri
				$body = $this->api->getUri($uri);
			}

			if (isset($body['total_count']) && !isset($this->data['uri']))
			{
				// first run through - log how many messages we found
				$this->info("Message events found", ['count' => $body['total_count']]);
                $this->data['total_events'] = $body['total_count'];
			}

			if (empty($body['results']))
			{
				$this->logWorkDone($start);

				$this->debug("No data returned from query - job is complete");

				// didn't get any data back - stop now
				return $this->complete();
			}

			// store the message events in the database for processing
			array_walk($body['results'], function ($event) use ($repository) {
				// only bother if we've got a user to match against, otherwise there's no point
				if (isset($event['rcpt_to']))
				{
                    $this->info("Storing message event", [
                       'recipient' => $event['rcpt_to'],
                       'type' => $event['type'],
                       'event_id' => $event['event_id'],
                    ]);

					$repository->storeMessageEvent($event);
				}
                else
                {
                    $this->info("No recipient found for event {$event['event_id']}, ignoring");
                }

                $this->data['events_stored']++;
			});

			$this->logWorkDone($start);

			if (isset($body['links']))
			{
				if (!isset($body['links']['next']))
				{
					$this->info("No further events to store - job is complete");

					// we're done
					return $this->complete();
				}

				$this->info("Additional message events found", ['uri' => $body['links']['next']]);

				// next link found - resume processing
				$this->data['uri'] = $body['links']['next'];
				return $this->resume();
			}

		}
		catch (SparkPostException $e)
		{
			if ($e->getCode() == 429)
			{
                $this->warning("SparkPost API call rate limited", ['message' => $e->getMessage()]);
                \XF::logException($e, false, "SparkPost API call rate limited");

				// rate limited!
				return $this->rateLimited();
			}
			else
			{
                $this->error("SparkPost API call failed", ['message' => $e->getMessage()]);
                \XF::logException($e, false, "SparkPost API call failed");

				// failure
				return $this->fail($e);
			}
		}

		// really shouldn't get to here
		return $this->complete();
	}

	public function getStatusMessage()
	{
        $action = \XF::phrase('sparkpostmail_fetching_message_events...');

		return sprintf('%s #%d: (%d/%d) events stored', $action, $this->data['run_count'], $this->data['events_stored'], $this->data['total_events']);
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

	public function complete() : JobResult
	{
		$this->info('Job complete', [
            'events_stored' => $this->data['events_stored'],
			'started' => $this->timestampToDateString($this->data['query_start']),
			'run_count' => $this->data['run_count'],
			'run_time' => $this->data['run_time'],
		]);
		$this->repository()->setMessageEventCache($this->data['query_start'], $this->data['run_count'], $this->data['run_time']);

        return JobResult::newComplete($this->jobId, [], sprintf("Job complete: %d events stored for processing", $this->data['events_stored']));
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
     * @return MessageEventRepository
     */
    protected function repository()
    {
        return $this->app->repository(MessageEventRepository::class);
    }
}
