<?php namespace Hampel\SparkPostMail\Http;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Thrown when a request never completed - DNS failure, timeout, or a URL XenForo's SSRF checks
 * refused to fetch. PSR-18 requires a client to throw for this and only this: an HTTP error
 * status is an ordinary response, not an exception.
 *
 * hampel/sparkpost catches ClientExceptionInterface, which this satisfies through
 * NetworkExceptionInterface, so it needs no knowledge of XenForo's reader.
 */
class NetworkException extends \RuntimeException implements NetworkExceptionInterface
{
    protected RequestInterface $request;

    public function __construct(RequestInterface $request, string $message, ?\Throwable $previous = null)
    {
        $this->request = $request;

        parent::__construct($message, 0, $previous);
    }

    public function getRequest() : RequestInterface
    {
        return $this->request;
    }
}
