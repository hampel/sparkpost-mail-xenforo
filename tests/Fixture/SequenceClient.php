<?php namespace Tests\Fixture;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client that hands back canned responses in order and records the URIs it was asked
 * for. Stands in for ReaderClient so the fetch job's paging can be driven from the captured
 * SparkPost payloads in tests/mock.
 */
class SequenceClient implements ClientInterface
{
    /** @var string[] */
    public array $requestedUris = [];

    /** @var ResponseInterface[] */
    protected array $responses;

    public function __construct(array $bodies)
    {
        $this->responses = array_map(fn(string $body) => new Response(200, [], $body), $bodies);
    }

    public function sendRequest(RequestInterface $request) : ResponseInterface
    {
        $this->requestedUris[] = (string) $request->getUri();

        return array_shift($this->responses) ?? new Response(200, [], '{"results":[]}');
    }
}
