<?php namespace Hampel\SparkPostMail;

use Hampel\SparkPostMail\Option\EmailTransport;
use Hampel\SparkPostMail\SubContainer\SparkPost;
use Psr\Log\NullLogger;
use XF\App;
use XF\Container;

class Listener
{
	public static function appSetup(App $app)
	{
		$container = $app->container();

		$container['sparkpostmail.api'] = function(Container $c) use ($app)
		{
			$class = $app->extendClass(SparkPost::class);
			return new $class($c, $app);
		};

        $container['sparkpostmail.log'] = function(\XF\Container $c) use ($app)
        {
            // Hampel/Monolog is a soft dependency - it is not in addon.json require. Without the
            // NullLogger fallback this returns null, and every consumer passes the result straight
            // into setLogger(LoggerInterface), which is not nullable.
            if ($c->offsetExists('monolog'))
            {
                return $c['monolog']->newChannel('sparkpost');
            }

            return new NullLogger();
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

	public static function mailerTransportSetup(Container $container, &$transport = null)
	{
		if (EmailTransport::isSparkPostEnabled())
		{
			$transport = $container['sparkpostmail.api']->transport();
		}
	}

	/**
	 * Declare this add-on's secrets to Hampel/AdminApi, so the rest of its settings can be
	 * reported. This is not a dependency on that add-on: an event no installed add-on defines
	 * is never fired, so on a forum without it this method is never called.
	 *
	 * The one secret is a member of somebody ELSE'S option. There is no sparkpostmailApiKey -
	 * the key, the tracking flags and test mode all live as extra members inside XenForo's own
	 * emailTransport option, which is why Option\EmailTransport exists. Core owns that option
	 * and the Admin API trusts core, so its other members are already reported; what needs
	 * declaring is the member this add-on puts there.
	 *
	 * Measured before writing this: the Admin API already withholds `apiKey` on the strength of
	 * its name alone, declared or not. That is a second layer rather than a reason to skip this
	 * one - the name rule lapses silently if the member is ever renamed, and a heuristic is not
	 * where a live sending credential should rest.
	 *
	 * sparkPostApi is claimed rather than declared secret: it overrides the API base url for a
	 * local stub in development and holds no credential, and claiming the key is what lets the
	 * API report whether it is set.
	 */
	public static function adminApiRedaction(array &$declarations)
	{
		$declarations['Hampel/SparkPostMail'] = [
			'options' => ['emailTransport.apiKey'],
			'config' => ['sparkPostApi' => []],
		];
	}
}
