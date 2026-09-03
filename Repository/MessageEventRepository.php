<?php namespace Hampel\SparkPostMail\Repository;

use Hampel\SparkPostMail\Entity\MessageEvent;
use Hampel\SparkPostMail\Finder\MessageEventFinder;
use XF\Entity\EmailBounceLog;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Repository;

class MessageEventRepository extends Repository
{
	public function storeMessageEvent(array $event) : ?MessageEvent
	{
		$entity = $this->finder(MessageEventFinder::class)->where('event_id', $event['event_id'])->fetchOne();

		if (!$entity)
		{
			// didn't find an existing entity - create a new one
			$entity = $this->em->create(MessageEvent::class);
			$entity->event_id = $event['event_id'];
		}

		$entity->type = $event['type'];
		$entity->recipient = $event['rcpt_to'];
		$entity->timestamp = (new \DateTimeImmutable($event['timestamp']))->getTimestamp();
		$entity->payload = $event;

		if ($entity->save(false))
		{
			return $entity;
		}
		else
		{
			return null;
		}
	}

	public function getUnprocessedMessageEvents(int $limit = 100) : ArrayCollection
	{
		return $this->finder(MessageEventFinder::class)->unprocessed($limit)->fetch();
	}

	public function pruneMessageEvents(int $days = 28) : int
	{
    	$timestamp = $this->daysAgo($days);

		return $this->db()->delete('xf_sparkpost_mail_message_event', 'timestamp < ? AND processed = 1', $timestamp);
	}

	public function getMessageEventDataFromCache()
	{
		return $this->app()->simpleCache()->getSet('Hampel/SparkPostMail')->getValue('message-event');
	}

	public function setMessageEventDataToCache($data)
	{
		$this->app()->simpleCache()->getSet('Hampel/SparkPostMail')->setValue('message-event', $data);
	}

	public function resetMessageEventCache()
	{
		$this->setMessageEventDataToCache([]);
	}

	public function setMessageEventCache($last_run, $run_count, $run_time)
	{
		$this->setMessageEventDataToCache(compact('last_run', 'run_count', 'run_time'));
	}

	public function getLastRun()
	{
		$me = $this->getMessageEventDataFromCache();
		return (isset($me['last_run'])) ? intval($me['last_run']) : null;
	}

	protected function daysAgo(int $days) : int
	{
		// XenForo forces UTC at startup, so plain arithmetic is exact here
		return \XF::$time - ($days * 86400);
	}

    public function logBounceMessage($email_date, $message_type, $action_taken, $user_id, $recipient, $raw_message, $status_code, $diagnostic_info) : EmailBounceLog
    {
        $bounce = $this->em->create(EmailBounceLog::class);
        $bounce->bulkSet(compact('email_date', 'message_type', 'action_taken', 'user_id', 'recipient', 'raw_message', 'status_code', 'diagnostic_info'));
        $bounce->save();

        return $bounce;
    }
}
