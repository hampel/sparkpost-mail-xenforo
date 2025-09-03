<?php namespace Hampel\SparkPostMail\Api;

use Carbon\Carbon;
use GuzzleHttp\Utils;
use Hampel\SparkPostMail\Exception\ClientException;
use Hampel\SparkPostMail\Exception\RequestException;
use Hampel\SparkPostMail\Exception\ServerException;
use Hampel\SparkPostMail\Traits\LogTrait;
use Hampel\SparkPostMail\Option\EmailTransport;

class SparkPostApi
{
    use LogTrait;

    /**
     * @var \XF\App
     */
    protected $app;

    /** @var string */
    protected $baseUrl;

    /** @var bool */
    protected $trustedUrl;

    public function __construct(\XF\App $app, $baseUrl = 'https://api.sparkpost.com/api/v1', $trustedUrl = false)
    {
        $this->app = $app;
        $this->baseUrl = $baseUrl;
        $this->trustedUrl = $trustedUrl;
    }

    public function getMessageEvents($page = 1, $per_page = 10, array $events = [], $from = null, $to = null)
    {
        $parameters = [
            'page' => $page,
            'per_page' => $per_page,
        ];
        if (!empty($events)) $parameters['events'] = implode(",", $events);

        if (isset($from) && is_numeric($from)) $parameters['from'] = $this->timestampToSparkPostDate($from);
        if (isset($to) && is_numeric($to)) $parameters['to'] = $this->timestampToSparkPostDate($to);

        $this->info("Fetching SparkPost message events", [
            'page' => $parameters['page'],
            'per_page' => $parameters['per_page'],
            'events' => $parameters['events'],
            'from' => $parameters['from'],
            'to' => $parameters['to'],
        ]);

        return $this->get("events/message", $parameters);
    }

    public function getUri($uri)
    {
        $this->info("Fetching SparkPost Uri", compact('uri'));

        return $this->get($this->stripUriPrefix($uri));
    }

    protected function get($url, $parameters = [], $options = []) : array
    {
        $baseOptions = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => EmailTransport::getApiKey()
            ]
        ];

        $options = array_merge($baseOptions, $options);

        if (!empty($parameters))
        {
            $params = http_build_query($parameters);
            $url = "{$url}?{$params}";
        }

        $url = "{$this->baseUrl}/{$url}";

        $trusted = $this->trustedUrl;
        $this->debug("SparkPost api call", compact('url', 'trusted'));

        if ($this->trustedUrl)
        {
            // only used when we set a manual API url, used to bypass checks so we can use localhost for testing
            $response = $this->app->http()->reader()->get($url, [], null, $options, $error);
        }
        else
        {
            // treat URLs as untrusted by default, will run through proxy server if configured
            $response = $this->app->http()->reader()->getUntrusted($url, [], null, $options, $error);
        }

        if(!$response)
        {
            // something went wrong with the HTTP request
            throw new RequestException('fetching message events', $error);
        }

        $status = $response->getStatusCode();

        if ($status == 200)
        {
            // we're all good
            return Utils::jsonDecode($response->getBody()->getContents(), true);
        }

        $reason = $response->getReasonPhrase();

        if ($status >= 500)
        {
            // service unavailable or similar - hopefully a temporary error
            throw new ServerException('fetching message events', $reason, $status);
        }
        else
        {
            // some other more serious error - will log it and create error message for XF logs
            throw new ClientException('fetching message events', $reason, $status);
        }
    }

    public function timestampToSparkPostDate($timestamp)
    {
        return Carbon::createFromTimestamp($timestamp)->format("Y-m-d\TH:i");
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
