<?php namespace Hampel\SparkPostMail\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use XF\Http\Reader;

/**
 * A PSR-18 client over XenForo's own HTTP reader, so hampel/sparkpost shares the board's
 * outbound HTTP stack instead of bringing its own.
 *
 * This is what makes the package usable here at all: requests keep XenForo's proxy
 * configuration and, on the untrusted path, its SSRF checks - which is why this add-on
 * previously had to write its own API client rather than use an off-the-shelf one.
 *
 * XF\Http\Reader already behaves the way PSR-18 requires. Reader::_request() sets
 * 'http_errors' => false, so a 4xx or 5xx arrives as an ordinary response rather than an
 * exception, and a request that never completed returns null with $error set.
 */
class ReaderClient implements ClientInterface
{
    protected Reader $reader;

    /**
     * Untrusted is the default and the only setting production should use: it runs the SSRF
     * checks and forces allow_redirects off. Trusted exists solely for a manually configured
     * API url pointing at a dev server on localhost, which the untrusted checks reject.
     */
    protected bool $trusted;

    public function __construct(Reader $reader, bool $trusted = false)
    {
        $this->reader = $reader;
        $this->trusted = $trusted;
    }

    public function sendRequest(RequestInterface $request) : ResponseInterface
    {
        $method = $this->trusted ? 'request' : 'requestUntrusted';

        // Reader merges these over its own defaults, so anything set here wins - notably do not
        // pass http_errors, which would turn error statuses back into exceptions
        $options = ['headers' => $this->flattenHeaders($request)];

        $body = $this->readBody($request);
        if ($body !== '')
        {
            $options['body'] = $body;
        }

        $response = $this->reader->$method(
            $request->getMethod(),
            (string) $request->getUri(),
            [],
            null,
            $options,
            $error
        );

        if (!$response)
        {
            throw new NetworkException($request, $error ?: 'The request could not be completed');
        }

        return $response;
    }

    /**
     * PSR-7 keeps each header as a list of values; the reader wants one string per header.
     */
    protected function flattenHeaders(RequestInterface $request) : array
    {
        return array_map(function(array $values)
        {
            return implode(', ', $values);
        }, $request->getHeaders());
    }

    protected function readBody(RequestInterface $request) : string
    {
        $body = $request->getBody();

        if ($body->isSeekable())
        {
            $body->rewind();
        }

        return $body->getContents();
    }
}
