<?php namespace Hampel\SparkPostMail\Option;

use XF\Option\AbstractOption;

class EmailTransport extends AbstractOption
{
	/**
	 * @return array
	 */
	public static function get()
	{
		return \XF::options()->emailTransport;
	}

	/**
	 * @return bool
	 */
	public static function isSparkPostEnabled()
	{
		$transport = self::get();
		return $transport['emailTransport'] == 'sparkpost' && !empty($transport['apiKey']);
	}

	/**
	 * @return string|null
	 */
	public static function getApiKey()
	{
		$transport = self::get();
		return $transport['emailTransport'] == 'sparkpost' ? $transport['apiKey'] : null;
	}

	/**
	 * @return bool
	 */
	public static function isClickTrackingEnabled()
	{
		$transport = self::get();
		return $transport['emailTransport'] == 'sparkpost' ? boolval($transport['clickTracking']) : false;
	}

	/**
	 * @return bool
	 */
	public static function isOpenTrackingEnabled()
	{
		$transport = self::get();
		return $transport['emailTransport'] == 'sparkpost' ? boolval($transport['openTracking']) : false;
	}

	/**
	 * @return bool
	 */
	public static function isTestModeEnabled()
	{
		$transport = self::get();
		return $transport['emailTransport'] == 'sparkpost' ? boolval($transport['testMode']) : false;
	}
}
