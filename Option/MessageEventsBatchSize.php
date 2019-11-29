<?php namespace Hampel\SparkPostMail\Option;

use XF\Option\AbstractOption;

class MessageEventsBatchSize extends AbstractOption
{
	public static function get()
	{
		return intval(\XF::options()->sparkpostmailMessageEventsBatchSize);
	}
}
