<?php namespace Hampel\SparkPostMail\Repository;

use Carbon\Carbon;
use XF\Mvc\Entity\Repository;

class MessageEvent extends Repository
{
	public function storeMessageEvent(array $event)
	{
		$entity = $this->finder('Hampel\SparkPostMail:MessageEvent')->where('event_id', $event['event_id'])->fetchOne();

		if (!$entity)
		{
			// didn't find an existing entity - create a new one
			$entity = $this->em->create('Hampel\SparkPostMail:MessageEvent');
			$entity->event_id = $event['event_id'];
		}

		$entity->type = $event['type'];
		$entity->recipient = $event['rcpt_to'];
		$entity->timestamp = Carbon::parse($event['timestamp'])->timestamp;
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

	public function markMessageEventProcessed($event)
	{
		$event->processed = 1;
		$event->save();
	}

	public function getUnprocessedMessageEvents($limit = 100)
	{
		return $this->finder('Hampel\SparkPostMail:MessageEvent')->unprocessed($limit)->fetch();
	}

	public function pruneMessageEvents($cutOff = null)
	{
		if (!isset($cutOff))
		{
			$cutOff = $this->daysAgo(28); // delete stored message events older than 28 days
		}

		$this->db()->delete('xf_sparkpost_mail_message_event', 'timestamp < ? AND processed = 1', $cutOff);
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

	protected function daysAgo($days)
	{
		return Carbon::createFromTimestamp(\XF::$time)->subDays($days)->timestamp;
	}
}
