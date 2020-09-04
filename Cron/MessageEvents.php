<?php namespace Hampel\SparkPostMail\Cron;

use Hampel\SparkPostMail\Option\EmailTransport;

class MessageEvents
{
	public static function fetchMessageEvents()
	{
		if (EmailTransport::isSparkPostEnabled())
		{
			\XF::app()->jobManager()->enqueueUnique('SparkPostMailMessageEvents', 'Hampel\SparkPostMail:MessageEvent', [], false);
		}
	}

	public static function processMessageEvents()
	{
		if (EmailTransport::isSparkPostEnabled())
		{
			\XF::app()->jobManager()->enqueueUnique('SparkPostMailEmailBounce', 'Hampel\SparkPostMail:EmailBounce', [], false);
		}
	}

	public static function dailyCleanup()
	{
		\XF::app()->repository('Hampel\SparkPostMail:MessageEvent')->pruneMessageEvents();
	}
}
