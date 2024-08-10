<?php namespace Hampel\SparkPostMail\SubContainer;

use Carbon\Carbon;
use Hampel\SparkPostMail\EmailBounce\Processor;
use Hampel\SparkPostMail\Option\EmailTransport;
use Hampel\Symfony\Mailer\SparkPost\Transport\SparkPostApiTransport;
use Http\Adapter\Guzzle7\Client;
use XF\Job\AbstractJob;
use XF\SubContainer\AbstractSubContainer;

class SparkPost extends AbstractSubContainer
{
	public function initialize()
	{
		$container = $this->container;

		$container['transport'] = function($c)
		{
            $apikey = EmailTransport::getApiKey();
            $client = $this->parent['http']->client();
			return new SparkPostApiTransport($apikey, $client);
		};

		$container['api'] = function($c)
		{
			$client = $this->parent['http']->client();
            $httpClient = new Client($client);
			$apikey = EmailTransport::getApiKey();

			return new \SparkPost\SparkPost($httpClient, ['key' => $apikey]);
		};

		$container['bounce'] = function($c)
		{
			return new Processor($this->app);
		};

		$container['bounce.message_event_types'] = [
			'bounce',
//			'delay',
			'policy_rejection',
			'out_of_band',
			'generation_rejection',
			'spam_complaint',
			'list_unsubscribe',
			'link_unsubscribe'
		];
	}

	public function sampleMessageEvents($events)
	{
		$response = $this->api()->request('GET', "events/message/samples", ['events' => $events])->wait();
		return $response->getBody();
	}

	public function getMessageEvents($page = 1, $per_page = 10, array $events = [], $from = null, $to = null)
	{
		$options = [
			'page' => $page,
			'per_page' => $per_page,
		];
		if (!empty($events)) $options['events'] = $events;

		if (isset($from) && is_numeric($from)) $options['from'] = $this->timestampToSparkPostDate($from);
		if (isset($from) && is_numeric($to)) $options['to'] = $this->timestampToSparkPostDate($to);

		$response = $this->api()->request('GET', "events/message", $options)->wait();
		return $response->getBody();
	}

	public function getUri($uri)
	{
		$uri = $this->stripUriPrefix($uri);

		$response = $this->api()->request('GET', $uri)->wait();
		return $response->getBody();
	}

	public function timestampToSparkPostDate($timestamp)
	{
		return urlencode(Carbon::createFromTimestamp($timestamp)->format("Y-m-d\TH:i"));
	}

	public function logJobProgress(AbstractJob $job, $message, array $context = [])
	{
		// check to see if we actually have a logger available and abort if not
		if (!isset($this->parent['cli.logger'])) return;

		/** @var \Hampel\JobRunner\Cli\Logger $logger */
		$logger = $this->parent['cli.logger'];
		$logger->logJobProgress($job, $message, $context);
	}

	/**
	 * @return SparkPostTransport
	 */
	public function transport()
	{
		return $this->container['transport'];
	}

	/**
	 * @return \SparkPost\SparkPost
	 */
	public function api()
	{
		return $this->container['api'];
	}

	/**
	 * @return Processor
	 */
	public function bounce()
	{
		return $this->container['bounce'];
	}

	/**
	 * @return array
	 */
	public function getBounceMessageEventTypes()
	{
		return $this->container['bounce.message_event_types'];
	}

	public function stripUriPrefix($uri)
	{
		// strip prefix from URI
		if (substr($uri, 0, 8) == '/api/v1/')
		{
			$uri = substr($uri, 8);
		}

		return $uri;
	}
}
