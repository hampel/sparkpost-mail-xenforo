<?php namespace Hampel\SparkPostMail\Test;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
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

		// SparkPostApiTransport reports API failures as a single TransportException whose message
		// ends with SparkPost's own errors array. There is no structured accessor for it, so this
		// parses the message format; if that ever changes, the fields below stay empty and the full
		// message still reaches the admin through 'error'.
		if ($e instanceof TransportExceptionInterface && preg_match('/(\[.*\])$/s', $e->getMessage(), $match))
		{
			$errors = json_decode($match[1], true);

			if (JSON_ERROR_NONE == json_last_error() && isset($errors[0]))
			{
				$sparkpostMessage = $errors[0]['message'] ?? '';
				$sparkpostDescription = $errors[0]['description'] ?? '';
				$sparkpostCode = $errors[0]['code'] ?? $e->getCode();
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