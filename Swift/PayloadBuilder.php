<?php namespace Hampel\SparkPostMail\Swift;

class PayloadBuilder implements \SwiftSparkPost\PayloadBuilder
{
	private $payloadBuilder;

	private $return_path;

	public function __construct(\SwiftSparkPost\Configuration $config, \SwiftSparkPost\RandomNumberGenerator $randomNumberGenerator)
	{
		$this->payloadBuilder = new \SwiftSparkPost\StandardPayloadBuilder($config, $randomNumberGenerator);
	}

	public function setReturnPath($return_path)
	{
		$this->return_path = $return_path;
	}

	public function getReturnPath()
	{
		return $this->return_path;
	}

	/**
	 * @param Swift_Mime_Message $message
	 *
	 * @return array
	 */
    public function buildPayload(\Swift_Mime_Message $message)
    {
    	$payload = $this->payloadBuilder->buildPayload($message);

    	if (!empty($this->return_path))
	    {
	    	$payload['return_path'] = $this->return_path;
	    }

    	return $payload;
    }
}
