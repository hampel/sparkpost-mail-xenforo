<?php namespace Hampel\SparkPostMail\SubContainer;

use Carbon\Carbon;
use Hampel\SparkPostMail\EmailBounce\Processor;
use Hampel\SparkPostMail\Option\ApiKey;
use SparkPost\SparkPostResponse;
use SwiftSparkPost\Option;
use XF\Mail\Mail;
use XF\SubContainer\AbstractSubContainer;

class SparkPost extends AbstractSubContainer
{
	public function initialize()
	{
		$container = $this->container;

		$container['api'] = function($c)
		{
			$client = $this->parent['http']->client();
			$httpClient = new \Http\Adapter\Guzzle6\Client($client);
			return new \SparkPost\SparkPost($httpClient, ['key' => ApiKey::get()]);
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
		/** @var SparkPostResponse $response */
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

		/** @var SparkPostResponse $response */
		$response = $this->api()->request('GET', "events/message", $options)->wait();
		return $response->getBody();
	}

	public function getUri($uri)
	{
		/** @var SparkPostResponse $response */
		$response = $this->api()->request('GET', $uri)->wait();
		return $response->getBody();
	}

	public function timestampToSparkPostDate($timestamp)
	{
		return urlencode(Carbon::createFromTimestamp($timestamp)->format("Y-m-d\TH:i"));
	}

	/**
	 * @param \XF\Mail\Mail $mail
	 *
	 * @return \XF\Mail\Mail
	 */
	public function setNonTransactional(Mail $mail)
	{
		// set SparkPost message options
		$mail->getMessageObject()->setOptions([
			Option::TRANSACTIONAL   => false,
		]);

		return $mail;
	}

	public function logJobProgress($message, array $context = [])
	{
		// check to see if we actually have a logger available and abort if not
		if (!isset($this->parent['cli.logger'])) return;

		/** @var Logger $logger */
		$logger = $this->parent['cli.logger'];
		$logger->logJobProgress($message, $context, $this);
	}

	/**
	 * @return SparkPost
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
}
