<?php namespace Hampel\SparkPostMail\Cron;

class MessageEvents
{
	public static function fetchMessageEvents()
	{
		\XF::app()->jobManager()->enqueueUnique('SparkPostMailMessageEvents', 'Hampel\SparkPostMail:MessageEvent', [], false);
	}

	public static function processMessageEvents()
	{
		\XF::app()->jobManager()->enqueueUnique('SparkPostMailEmailBounce', 'Hampel\SparkPostMail:EmailBounce', [], false);
	}

	public static function dailyCleanup()
	{
		\XF::app()->repository('Hampel\SparkPostMail:MessageEvent')->pruneMessageEvents();
	}
}
