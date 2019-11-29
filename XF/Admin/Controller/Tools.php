<?php namespace Hampel\SparkPostMail\XF\Admin\Controller;

use SparkPost\SparkPostException;

class Tools extends XFCP_Tools
{
	public function actionTestSparkPost()
	{
		$this->setSectionContext('sparkpostmailTest');

		$messages = [];
		$results = false;
		$test = '';
		$options = [
			'email' => \XF::visitor()->email,
			'transactional' => true,
		];

		if ($this->isPost())
		{
			$test = $this->filter('test', 'str');
			$options = $this->filter('options', 'array');

			/** @var AbstractTest $tester */
			$tester = $this->app->container()->create('sparkpostmail.test', $test, [$this, $options]);
			if ($tester)
			{
				$results = $tester->run();
				$messages = $tester->getMessages();
			}
			else
			{
				return $this->error(\XF::phrase('sparkpost_this_test_could_not_be_run'), 500);
			}
		}

		$viewParams = compact('results', 'messages', 'test', 'options', 'pool');
		return $this->view('XF:Tools\TestSparkPostMail', 'sparkpostmail_tools_test_sparkpost', $viewParams);
	}
}