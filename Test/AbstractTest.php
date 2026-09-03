<?php namespace Hampel\SparkPostMail\Test;

use Hampel\SparkPost\Exception\ApiException;
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
		return isset($this->data[$name]) && $this->data[$name] == "1";
	}

	protected function message($type, $message)
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

		// SparkPostTransport wraps the package's own exception as the previous one, so the
		// decoded errors array is available directly. Before 5.0.0 this had to parse it back out
		// of the exception message, which is the sort of coupling that dies silently.
		$previous = $e->getPrevious();

		if ($previous instanceof ApiException && isset($previous->errors[0]))
		{
			$error = $previous->errors[0];

			$sparkpostMessage = $error['message'] ?? '';
			$sparkpostDescription = $error['description'] ?? '';
			$sparkpostCode = $error['code'] ?? $previous->statusCode;
		}

		$this->errorMessage(\XF::phrase('sparkpostmail_sending_failed', [
			'error' => $e->getMessage(),
			'message' => $sparkpostMessage,
			'description' => $sparkpostDescription,
			'code' => $sparkpostCode
		]));
	}
}