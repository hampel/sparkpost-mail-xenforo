<?php namespace Hampel\SparkPostMail\Job;

use Hampel\SparkPost\Exception\ExceptionInterface as SparkPostException;
use Hampel\SparkPost\Exception\RateLimitException;
use Hampel\SparkPost\MessageEvent\EventCursor;
use Hampel\SparkPostMail\Repository\MessageEventRepository;
use XF\Job\JobResult;

class FetchMessageEventsJob extends AbstractLoggingJob
{
	protected $defaultData = [
		'cursor' => null,
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

			$messageEvents = $this->api->sparkpost()->messageEvents();
			$firstCall = empty($this->data['cursor']);

			// the cursor is an opaque string and the only thing the job has to carry between runs
			$page = $firstCall
				? $messageEvents->search($this->api->buildEventQuery())
				: $messageEvents->next(EventCursor::fromString($this->data['cursor']));

			if ($firstCall && $page->totalCount !== null)
			{
				// first run through - log how many messages we found
				$this->info("Message events found", ['count' => $page->totalCount]);
				$this->data['total_events'] = $page->totalCount;
			}

			if (!$page->results)
			{
				$this->logWorkDone($start);

				$this->debug("No data returned from query - job is complete");

				// didn't get any data back - stop now
				return $this->complete();
			}

			// store the message events in the database for processing
			foreach ($page->results as $event)
			{
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
			}

			$this->logWorkDone($start);

			if (!$page->hasMore())
			{
				$this->info("No further events to store - job is complete");

				// we're done
				return $this->complete();
			}

			$this->info("Additional message events found");

			$this->data['cursor'] = (string) $page->next();
			return $this->resume();
		}
		catch (RateLimitException $e)
		{
			$this->warning("SparkPost API call rate limited", [
				'message' => $e->getMessage(),
				'retryAfter' => $e->retryAfter,
			]);
			\XF::logException($e, false, "SparkPost API call rate limited");

			return $this->rateLimited($e->retryAfter);
		}
		catch (SparkPostException $e)
		{
			$this->error("SparkPost API call failed", ['message' => $e->getMessage()]);
			\XF::logException($e, false, "SparkPost API call failed");

			// failure
			return $this->fail($e);
		}
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

	/**
	 * SparkPost sends Retry-After on a 429 often enough to be worth honouring; fall back to two
	 * minutes when it did not.
	 */
	protected function rateLimited(?int $retryAfter = null)
	{
		$delay = ($retryAfter !== null && $retryAfter > 0) ? $retryAfter : 120;
		$continueDate = (new \DateTimeImmutable())->modify("+{$delay} seconds");

		$this->log("API rate limited - sleeping", [
			'cursor' => $this->data['cursor'],
			'continueDate' => $this->dateString($continueDate),
		]);

		return $this->resumeLater($continueDate->getTimestamp());
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
		return $this->dateString((new \DateTimeImmutable())->setTimestamp($timestamp));
	}

	protected function dateString(\DateTimeInterface $date)
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
