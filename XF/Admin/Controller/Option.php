<?php namespace Hampel\SparkPostMail\XF\Admin\Controller;

use XF\Mvc\ParameterBag;

class Option extends XFCP_Option
{
	public function actionEmailTransportSetup(ParameterBag $params)
	{
		if ($this->isPost())
		{
			if($this->filter('new_type', 'str') == 'sparkpost')
			{
				$option = $this->assertEmailTransportOption($params->option_id);

				$viewParams = [
					'option' => $option,
					'type' => 'sparkpost'
				];
				return $this->view('XF:Option\EmailTransportServer', 'sparkpostmail_option_email_transport_sparkpost', $viewParams);

			}
		}

		return parent::actionEmailTransportSetup($params);
	}

	public function actionEmailTransportSparkpost(ParameterBag $params)
	{
		$this->assertPostOnly();

		$option = $this->assertEmailTransportOption($params->option_id);

		$optionValue = $this->filter([
			'emailTransport' => 'str',
			'apiKey' => 'str',
			'clickTracking' => 'bool',
			'openTracking' => 'bool',
			'testMode' => 'bool',
		]);

		$this->getOptionRepo()->updateOption($option->option_id, $optionValue);

		return $this->redirect($this->getDynamicRedirect());
	}
}
