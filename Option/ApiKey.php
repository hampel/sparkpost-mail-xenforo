<?php namespace Hampel\SparkPostMail\Option;

use Http\Adapter\Guzzle6\Client as ClientAdapter;
use SparkPost\SparkPost;
use SparkPost\SparkPostException;
use XF\Option\AbstractOption;

class ApiKey extends AbstractOption
{
	public static function verifyOption(&$value, \XF\Entity\Option $option)
	{
		if (!empty($value))
		{
			try
			{
				$client = \XF::app()->http()->client();

				$httpAdapter = new ClientAdapter($client);
				$sparkpost = new SparkPost($httpAdapter, ['key' => $value, 'async' => false]);
				$result = $sparkpost->transmissions->get();

				if ($result->getStatusCode() != 200)
				{
					$option->error(\XF::phrase('sparkpostmail_error', ['api' => 'transmissions.get', 'message' => 'Did not get 200 response from API call']), $option->option_id);

					return false;
				}
			}
			catch (SparkPostException $e)
			{
				$prev = $e->getPrevious();
				$message = $prev->getMessage();
				$code = $prev->getCode();

				if (!empty($code)) $message .= " ({$code})";

				$option->error(\XF::phrase('sparkpostmail_error', ['api' => 'transmissions.get', 'message' => $message]), $option->option_id);

				return false;
			}
		}

		return true;
	}

	public static function get()
	{
		return \XF::options()->sparkpostmailApiKey;
	}
}
