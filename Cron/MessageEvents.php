<?php namespace Hampel\SparkPostMail\Cron;

use Hampel\SparkPostMail\Job\FetchMessageEventsJob;
use Hampel\SparkPostMail\Job\ProcessMessageEventsJob;
use Hampel\SparkPostMail\Option\EmailTransport;
use Hampel\SparkPostMail\Repository\MessageEventRepository;

class MessageEvents
{
	public static function fetchMessageEvents()
	{
		if (EmailTransport::isSparkPostEnabled())
		{
			\XF::app()->jobManager()->enqueueUnique('SparkPostMailFetchMessageEvents', FetchMessageEventsJob::class, [], false);
		}
	}

	public static function processMessageEvents()
	{
		if (EmailTransport::isSparkPostEnabled())
		{
			\XF::app()->jobManager()->enqueueUnique('SparkPostMailProcessMessageEvents', ProcessMessageEventsJob::class, [], false);
		}
	}

	public static function dailyCleanup()
	{
		\XF::repository(MessageEventRepository::class)->pruneMessageEvents();
	}
}
