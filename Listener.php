<?php namespace Hampel\SparkPostMail;

use Hampel\SparkPostMail\SubContainer\SparkPost;
use Hampel\SparkPostMail\Option\ApiKey;
use Hampel\SparkPostMail\Option\BounceDomain;
use Hampel\SparkPostMail\SubContainer\SparkPostApi;
use Hampel\SparkPostMail\Swift\PayloadBuilder;
use Http\Adapter\Guzzle6\Client;
use SwiftSparkPost\Configuration;
use SwiftSparkPost\MtRandomNumberGenerator;
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
				]);

// can't do it the easy way
//			$transport = \SwiftSparkPost\Transport::newInstance($apikey, $config);


// need to break Transport invocation down into bits so we can replace the payload builder and adjust our returnpath
	        $eventDispatcher       = \Swift_DependencyContainer::getInstance()->lookup('transport.eventdispatcher');
	        $client                = \XF::app()->http()->client();
	        $guzzle                = new Client($client);
	        $sparkpost             = new \SparkPost\SparkPost($guzzle, ['key' => $apikey]);
	        $randomNumberGenerator = new MtRandomNumberGenerator();
	        $payloadBuilder        = new PayloadBuilder($config, $randomNumberGenerator);

			$bounceDomain = BounceDomain::get();
			if (!empty($bounceDomain))
			{
				$payloadBuilder->setReturnPath("bounce@{$bounceDomain}");
			}

	        $transport = new \SwiftSparkPost\Transport($eventDispatcher, $sparkpost, $payloadBuilder);
		}
	}
}