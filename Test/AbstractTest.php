<?php namespace Hampel\SparkPostMail\Test;

use XF\App;
use XF\Admin\Controller\AbstractController;

abstract class AbstractTest
{
	protected $app;
	protected $controller;
	protected $data;
	protected $defaultData = [];
	protected $messages = [];

	abstract public function run();

	public function __construct(App $app, AbstractController $controller, array $data = [])
	{
		$this->app = $app;
		$this->controller = $controller;
		$this->data = $this->setupData($data);
	}

	protected function setupData(array $data)
	{
		return array_merge($this->defaultData, $data);
	}

	public function getData()
	{
		return $this->data;
	}

	public function getMessages()
	{
		return $this->messages;
	}

	public function getErrorMessages()
	{
		return array_filter($this->messages, function($value) {
			return (isset($value['type']) && ($value['type'] == 'error'));
		});
	}

	public function getSuccessMessages()
	{
		return array_filter($this->messages, function($value) {
			return (isset($value['type']) && ($value['type'] == 'success'));
		});
	}

	protected function getCheckbox($name)
	{
		return isset($this->data[$name]) && $this->data[$name] == "1" ? true : false;
	}

	protected function message($type = 'none', $message)
	{
		$this->messages[] = compact('type', 'message');
	}

	protected function errorMessage($message)
	{
		$this->message('error', $message);
	}

	protected function successMessage($message)
	{
		$this->message('success', $message);
	}

	protected function processException(\Exception $e)
	{
		$sparkpostMessage = null;
		$sparkpostDescription = null;
		$sparkpostCode = $e->getCode();

		if (get_class($e) == \Swift_TransportException::class)
		{
			if ($previous = $e->getPrevious())
			{
				$sparkpostMessage = $previous->getMessage();
				if (!empty($sparkpostMessage))
				{
				   $body = json_decode($sparkpostMessage, true);
					if (JSON_ERROR_NONE == json_last_error())
					{
						$sparkpostMessage = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : "";
						$sparkpostDescription = isset($body['errors'][0]['description']) ? $body['errors'][0]['description'] : "";
						$sparkpostCode = isset($body['errors'][0]['code']) ? $body['errors'][0]['code'] : "";
					}
				}
			}
		}

		$this->errorMessage(\XF::phrase('sparkpostmail_sending_failed', [
			'error' => $e->getMessage(),
			'message' => $sparkpostMessage,
			'description' => $sparkpostDescription,
			'code' => $sparkpostCode
		]));
	}
}