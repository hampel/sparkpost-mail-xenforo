<?php namespace Hampel\SparkPostMail\XF\Admin\Controller;

use XF\Entity\User;

class Tools extends XFCP_Tools
{
	public function actionTestSparkPostMail()
	{
		$this->setSectionContext('sparkpostmailTest');
        $this->assertAdminPermission('option');

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
				return $this->error(\XF::phrase('sparkpostmail_this_test_could_not_be_run'), 500);
			}
		}

		$viewParams = compact('results', 'messages', 'test', 'options');
		return $this->view('XF:Tools\TestSparkPostMail', 'sparkpostmail_tools_test_sparkpost', $viewParams);
	}

    /**
     * @param User $user
     *
     * @return Mail
     */
    protected function getMail(User $user)
    {
        $mail = parent::getMail($user);

        if ($mail instanceof \Hampel\SparkPostMail\XF\Mail\Mail)
        {
            // if we're running SparkPost, set this email to non-transactional
            $mail->setTransactional(false);
        }

        return $mail;
    }
}