<?php namespace Hampel\SparkPostMail\SubContainer;

use Carbon\Carbon;
use Hampel\SparkPostMail\Api\SparkPostApi;
use Hampel\SparkPostMail\Option\EmailTransport;
use Hampel\SparkPostMail\Option\MessageEventsBatchSize;
use Hampel\SparkPostMail\Repository\MessageEventRepository;
use Hampel\Symfony\Mailer\SparkPost\Transport\SparkPostApiTransport;
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
            // on our dev server we may want to over-ride the API url and disable "untrusted" mode, so we can connect to
            // our dev API server running on localhost. This should never be used in production.
            $customApi = $this->app->config('sparkPostApi');
            if ($customApi)
            {
                // dev mode over-ride
                $api = new SparkPostApi($this->app, $customApi, true);
            }
            else
            {
                // production version
                $api = new SparkPostApi($this->app);
            }

            $api->setLogger($this->parent['sparkpostmail.log']);
            return $api;
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

//	public function sampleMessageEvents($events)
//	{
//		$response = $this->api()->request('GET', "events/message/samples", ['events' => $events])->wait();
//		return $response->getBody();
//	}

	public function getMessageEvents()
	{
        $page = 1;
        $perPage = MessageEventsBatchSize::get();
        $events = $this->getBounceMessageEventTypes();

        // if we've run before, use that as start date, otherwise just go back 11 days and get everything
        $from = $this->app->repository(MessageEventRepository::class)->getLastRun() ?? Carbon::createFromTimestamp(\XF::$time)->subDays(11)->timestamp;

        if (\XF::$time - $from < 60)
        {
            // sanity checking, start time should not be later than end time
            $from = \XF::$time - 60;
        }

        $to = \XF::$time;

		return $this->api()->getMessageEvents($page, $perPage, $events, $from, $to);
	}

	public function getUri($uri)
	{
		return $this->api()->getUri($uri);
	}

	/**
	 * @return SparkPostApiTransport
	 */
	public function transport()
	{
		return $this->container['transport'];
	}

	/**
	 * @return SparkPostApi
	 */
	public function api()
	{
		return $this->container['api'];
	}

	/**
	 * @return array
	 */
	public function getBounceMessageEventTypes()
	{
		return $this->container['bounce.message_event_types'];
	}
}
