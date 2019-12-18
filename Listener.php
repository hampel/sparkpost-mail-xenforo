<?php namespace Hampel\SparkPostMail;

use Hampel\SparkPostMail\Option\ClickTracking;
use Hampel\SparkPostMail\Option\OpenTracking;
use Hampel\SparkPostMail\SubContainer\SparkPost;
use Hampel\SparkPostMail\Option\ApiKey;
use Http\Adapter\Guzzle6\Client as GuzzleAdapter;
use Swift_DependencyContainer;
use SwiftSparkPost\Configuration;
use SwiftSparkPost\MtRandomNumberGenerator;
use SwiftSparkPost\Option;
use SwiftSparkPost\StandardPayloadBuilder;
use SwiftSparkPost\Transport;
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

	        $eventDispatcher       = Swift_DependencyContainer::getInstance()->lookup('transport.eventdispatcher');
	        $guzzle                = new GuzzleAdapter(\XF::app()->http()->client());
	        $sparkpost             = new \SparkPost\SparkPost($guzzle, ['key' => $apikey]);
	        $randomNumberGenerator = new MtRandomNumberGenerator();
	        $payloadBuilder        = new StandardPayloadBuilder($config, $randomNumberGenerator);

			$transport = new Transport($eventDispatcher, $sparkpost, $payloadBuilder);
		}
	}
}