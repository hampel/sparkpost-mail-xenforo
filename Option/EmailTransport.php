<?php namespace Hampel\SparkPostMail\Option;

use XF\Option\AbstractOption;

class EmailTransport extends AbstractOption
{
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

	public static function getApiKey()
	{
		$transport = self::get();
		return $transport['emailTransport'] == 'sparkpost' ? $transport['apiKey'] : null;
	}
}
