<?php namespace Hampel\SparkPostMail;

use Hampel\SparkPostMail\Option\EmailTransport;
use Hampel\SparkPostMail\SubContainer\SparkPost;
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
		if (EmailTransport::isSparkPostEnabled())
		{
			$transport = $container['sparkpostmail']->transport();
		}
	}
}