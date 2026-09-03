<?php namespace Tests\Fixture;

use XF\Http\Reader;

/**
 * Stands in for XF\Http\Reader so ReaderClient can be tested without HTTP. Records how it was
 * called and returns whatever the test set up - including null, which is how the real reader
 * reports a request that never completed.
 */
class SpyReader extends Reader
{
    /** @var array{method: string, args: array}|null */
    public ?array $call = null;

    public $response = null;

    public ?string $error = null;

    public function __construct()
    {
        // the real Reader takes two Guzzle clients; this one makes no requests
    }

    public function request($method, $url, array $limits = [], $saveTo = null, array $options = [], &$error = null)
    {
        return $this->record('request', $method, $url, $limits, $saveTo, $options, $error);
    }

    public function requestUntrusted($method, $url, array $limits = [], $saveTo = null, array $options = [], &$error = null)
    {
        return $this->record('requestUntrusted', $method, $url, $limits, $saveTo, $options, $error);
    }

    protected function record($readerMethod, $method, $url, array $limits, $saveTo, array $options, &$error)
    {
        $this->call = [
            'method' => $readerMethod,
            'args' => compact('method', 'url', 'limits', 'saveTo', 'options'),
        ];

        $error = $this->error;

        return $this->response;
    }
}
