<?php namespace Tests\Unit;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Hampel\SparkPostMail\Http\NetworkException;
use Hampel\SparkPostMail\Http\ReaderClient;
use Psr\Http\Client\ClientExceptionInterface;
use Tests\Fixture\SpyReader;
use Tests\TestCase;

class ReaderClientTest extends TestCase
{
    protected SpyReader $reader;

    public function setUp(): void
    {
        parent::setUp();

        $this->reader = new SpyReader();
    }

    protected function client(bool $trusted = false): ReaderClient
    {
        return new ReaderClient($this->reader, $trusted);
    }

    /**
     * The PSR-18 contract, and the thing most likely to be got wrong: an HTTP error status is an
     * ordinary response. hampel/sparkpost classifies 4xx and 5xx itself and cannot do that if
     * the client throws first.
     *
     * @dataProvider errorStatuses
     */
    public function test_an_http_error_status_is_returned_not_thrown(int $status)
    {
        $this->reader->response = new Response($status, [], '{"errors":[{"message":"nope"}]}');

        $response = $this->client()->sendRequest(new Request('GET', 'https://api.sparkpost.com/api/v1/events/message'));

        $this->assertEquals($status, $response->getStatusCode());
        $this->assertEquals('{"errors":[{"message":"nope"}]}', (string) $response->getBody());
    }

    public static function errorStatuses(): array
    {
        return ['400' => [400], '401' => [401], '404' => [404], '429' => [429], '500' => [500], '503' => [503]];
    }

    public function test_a_successful_response_is_returned()
    {
        $this->reader->response = new Response(200, [], '{"results":[]}');

        $response = $this->client()->sendRequest(new Request('GET', 'https://api.sparkpost.com/api/v1/events/message'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * The other half of the contract: a request that never completed must throw, and the
     * exception must satisfy ClientExceptionInterface, which is what the package catches.
     */
    public function test_a_request_that_never_completed_throws()
    {
        $this->reader->response = null;
        $this->reader->error = 'The URL is not requestable (private IP)';

        $request = new Request('GET', 'https://api.sparkpost.com/api/v1/events/message');

        try
        {
            $this->client()->sendRequest($request);
            $this->fail('expected a NetworkException');
        }
        catch (NetworkException $e)
        {
            $this->assertInstanceOf(ClientExceptionInterface::class, $e);
            $this->assertEquals('The URL is not requestable (private IP)', $e->getMessage());
            $this->assertSame($request, $e->getRequest());
        }
    }

    public function test_a_failure_with_no_error_message_still_throws_usefully()
    {
        $this->reader->response = null;
        $this->reader->error = null;

        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('The request could not be completed');

        $this->client()->sendRequest(new Request('GET', 'https://api.sparkpost.com/api/v1/events/message'));
    }

    public function test_requests_are_untrusted_by_default()
    {
        $this->reader->response = new Response(200);

        $this->client()->sendRequest(new Request('GET', 'https://api.sparkpost.com/api/v1/events/message'));

        $this->assertEquals('requestUntrusted', $this->reader->call['method']);
    }

    public function test_the_trusted_path_is_opt_in()
    {
        $this->reader->response = new Response(200);

        $this->client(true)->sendRequest(new Request('GET', 'http://localhost:8080/api/v1/events/message'));

        $this->assertEquals('request', $this->reader->call['method']);
    }

    public function test_method_and_uri_are_passed_through()
    {
        $this->reader->response = new Response(200);

        $this->client()->sendRequest(new Request('POST', 'https://api.sparkpost.com/api/v1/transmissions'));

        $this->assertEquals('POST', $this->reader->call['args']['method']);
        $this->assertEquals('https://api.sparkpost.com/api/v1/transmissions', $this->reader->call['args']['url']);
    }

    public function test_headers_are_flattened_to_one_string_each()
    {
        $this->reader->response = new Response(200);

        $this->client()->sendRequest(new Request('GET', 'https://api.sparkpost.com/api/v1/events/message', [
            'Authorization' => 'key',
            'Accept' => ['application/json', 'text/plain'],
        ]));

        $headers = $this->reader->call['args']['options']['headers'];
        $this->assertEquals('key', $headers['Authorization']);
        $this->assertEquals('application/json, text/plain', $headers['Accept']);
    }

    public function test_a_request_body_is_forwarded()
    {
        $this->reader->response = new Response(200);

        $this->client()->sendRequest(new Request('POST', 'https://api.sparkpost.com/api/v1/transmissions', [], '{"a":1}'));

        $this->assertEquals('{"a":1}', $this->reader->call['args']['options']['body']);
    }

    public function test_an_empty_body_is_not_forwarded()
    {
        $this->reader->response = new Response(200);

        $this->client()->sendRequest(new Request('GET', 'https://api.sparkpost.com/api/v1/events/message'));

        $this->assertArrayNotHasKey('body', $this->reader->call['args']['options']);
    }

    /**
     * Reader merges caller options OVER its own defaults, so passing http_errors here would
     * turn error statuses back into exceptions and break the contract above.
     */
    public function test_http_errors_is_never_overridden()
    {
        $this->reader->response = new Response(200);

        $this->client()->sendRequest(new Request('GET', 'https://api.sparkpost.com/api/v1/events/message'));

        $this->assertArrayNotHasKey('http_errors', $this->reader->call['args']['options']);
    }
}
