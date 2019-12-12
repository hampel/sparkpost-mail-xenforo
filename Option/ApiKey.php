<?php namespace Hampel\SparkPostMail\Option;

use XF\Option\AbstractOption;

class ApiKey extends AbstractOption
{
	public static function emailTransport()
	{
		return \XF::options()->emailTransport;
	}

	public static function get()
	{
		$transport = self::emailTransport();
		return $transport['emailTransport'] == 'sparkpost' ? $transport['sparkpostmailApiKey'] : null;
	}
}
