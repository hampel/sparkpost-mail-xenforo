<?php namespace Hampel\SparkPostMail\Option;

use XF\Option\AbstractOption;

class OpenTracking extends AbstractOption
{
	public static function get()
	{
		return (bool)\XF::options()->sparkpostmailOpenTracking;
	}

	/**
	 * @return bool
	 */
	public static function isEnabled()
	{
		return self::get();
	}
}
