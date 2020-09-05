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
		// if we've still got this version of the addon running after we've upgraded to XF 2.2
		// then disable SparkPost to prevent breaking the forum
		if (\XF::$versionId >= 2020000)
		{
			return false;
		}

		$transport = self::get();
		return $transport['emailTransport'] == 'sparkpost' && !empty($transport['apiKey']);
	}

	public static function getApiKey()
	{
		$transport = self::get();
		return $transport['emailTransport'] == 'sparkpost' ? $transport['apiKey'] : null;
	}
}
