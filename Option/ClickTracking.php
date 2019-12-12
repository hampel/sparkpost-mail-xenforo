<?php namespace Hampel\SparkPostMail\Option;

use XF\Option\AbstractOption;

class ClickTracking extends AbstractOption
{
	public static function get()
	{
		return (bool)\XF::options()->sparkpostmailClickTracking;
	}

	/**
	 * @return bool
	 */
	public static function isEnabled()
	{
		return self::get();
	}
}
