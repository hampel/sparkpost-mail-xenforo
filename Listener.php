<?php namespace Hampel\SparkPostMail;

use Hampel\SparkPostMail\Option\ClickTracking;
use Hampel\SparkPostMail\Option\OpenTracking;
use Hampel\SparkPostMail\SubContainer\SparkPost;
use Hampel\SparkPostMail\Option\ApiKey;
use Hampel\SparkPostMail\SubContainer\SparkPostApi;
use SwiftSparkPost\Configuration;
use SwiftSparkPost\Option;
use XF\App;
use XF\Container;

class Listener
{
	public static function appSetup(App $app)
	{
		$container = $app->container();

		$container['sparkpostmail'] = function(Container $c) use ($app)
		{
			$class = $app->extendClass(SparkPost::class);
			return new $class($c, $app);
		};
	}

	public static function appAdminSetup(App $app)
	{
		$container = $app->container();

		$container->factory('sparkpostmail.test', function($class, array $params, Container $c) use ($app)
		{
			$class = \XF::stringToClass($class, '\%s\Test\%s');
			$class = $app->extendClass($class);

			array_unshift($params, $app);

			return $c->createObject($class, $params, true);
		}, false);
	}

	public static function mailerTransportSetup(Container $container, \Swift_Transport &$transport = null)
	{
		if ($apikey = ApiKey::get())
		{
			$config = Configuration::newInstance()
				->setOptions([
					Option::TRANSACTIONAL    => true, // all emails are transactional unless explicitly marked
					Option::OPEN_TRACKING    => OpenTracking::isEnabled(), // disable open tracking
					Option::CLICK_TRACKING   => ClickTracking::isEnabled(), // disable click tracking
				]);

			 $transport = \SwiftSparkPost\Transport::newInstance($apikey, $config);
		}
	}
}