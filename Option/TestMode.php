<?php namespace Hampel\SparkPostMail\Option;

use XF\Option\AbstractOption;

class TestMode extends AbstractOption
{
	public static function get()
	{
		return (bool)\XF::options()->sparkpostmailTestMode;
	}

	/**
	 * @return bool
	 */
	public static function isEnabled()
	{
		return self::get();
	}
}
