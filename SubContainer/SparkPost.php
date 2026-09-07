<?php namespace Hampel\SparkPostMail\SubContainer;

use GuzzleHttp\Psr7\HttpFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Hampel\SparkPost\Config;
use Hampel\SparkPost\MessageEvent\EventQuery;
use Hampel\SparkPost\MessageEvent\EventType;
use Hampel\SparkPost\SparkPost as SparkPostClient;
use Hampel\SparkPost\Transport\EventListener\SinkEnvelopeListener;
use Hampel\SparkPost\Transport\SparkPostTransport;
use Hampel\SparkPostMail\Http\ReaderClient;
use Hampel\SparkPostMail\Log\LazyLogger;
use Hampel\SparkPostMail\Option\EmailTransport;
use Hampel\SparkPostMail\Option\MessageEventsBatchSize;
use Hampel\SparkPostMail\Repository\MessageEventRepository;
use XF\SubContainer\AbstractSubContainer;

class SparkPost extends AbstractSubContainer
{
	public function initialize()
	{
		$container = $this->container;

		$container['http'] = function($c)
		{
			// A manually configured API url means a dev server on localhost, which the untrusted
			// path's SSRF checks reject - that, and only that, is what the trusted flag is for.
			return new ReaderClient(
				$this->parent['http']->reader(),
				(bool) $this->app->config('sparkPostApi')
			);
		};

		// Resolved on first use, never during construction. The mailer builds its transport by
		// firing mailer_transport_setup, which this add-on answers; Hampel/Monolog's email
		// handler calls XF\App::mailer() while building a channel. Taking the logger eagerly
		// here closes that loop and recurses until the stack is exhausted - which is what 5.0.0
		// shipped. See Log\LazyLogger.
		$container['log'] = function($c)
		{
			return new LazyLogger(function()
			{
				return $this->parent['sparkpostmail.log'];
			});
		};

		$container['sparkpost'] = function($c)
		{
			$apiKey = EmailTransport::getApiKey();
			$customApi = $this->app->config('sparkPostApi');

			$config = $customApi ? new Config($apiKey, $customApi) : Config::forRegion($apiKey);

			// PSR-17 request and stream factories; XenForo ships guzzlehttp/psr7
			$factory = new HttpFactory();

			return new SparkPostClient($config, $c['http'], $factory, $factory, $c['log']);
		};

		$container['transport'] = function($c)
		{
			$dispatcher = null;

			if (EmailTransport::isTestModeEnabled())
			{
				// Rewrite the SMTP envelope rather than the To: header, so a test message is
				// delivered to SparkPost's sink while still reading as addressed to the real
				// recipient. Before 5.0.0 this add-on appended the sink suffix to the address
				// itself, which every recipient could see.
				$dispatcher = new EventDispatcher();
				$dispatcher->addSubscriber(new SinkEnvelopeListener());
			}

			return new SparkPostTransport($c['sparkpost'], $dispatcher, $c['log']);
		};

		$container['bounce.message_event_types'] = [
			EventType::Bounce,
//			EventType::Delay,
			EventType::PolicyRejection,
			EventType::OutOfBand,
			EventType::GenerationRejection,
			EventType::SpamComplaint,
			EventType::ListUnsubscribe,
			EventType::LinkUnsubscribe,
		];
	}

	/**
	 * The query for the next fetch: which events, and the window to ask for.
	 *
	 * This stays here rather than in the job so it can be tested without one - the window
	 * arithmetic has been the subject of two bugfixes.
	 */
	public function buildEventQuery() : EventQuery
	{
		// if we've run before, start where we left off, otherwise go back 11 days and get everything
		$from = $this->app->repository(MessageEventRepository::class)->getLastRun()
			?? \XF::$time - (86400 * 11);

		if (\XF::$time - $from < 60)
		{
			// SparkPost rejects a window narrower than a minute
			$from = \XF::$time - 60;
		}

		return EventQuery::make()
			->events(...$this->getBounceMessageEventTypes())
			->from((new \DateTimeImmutable())->setTimestamp($from))
			->to((new \DateTimeImmutable())->setTimestamp(\XF::$time))
			->perPage(MessageEventsBatchSize::get());
	}

	/**
	 * @return SparkPostTransport
	 */
	public function transport()
	{
		return $this->container['transport'];
	}

	/**
	 * @return SparkPostClient
	 */
	public function sparkpost()
	{
		return $this->container['sparkpost'];
	}

	/**
	 * @return EventType[]
	 */
	public function getBounceMessageEventTypes()
	{
		return $this->container['bounce.message_event_types'];
	}
}
