<?php namespace Hampel\SparkPostMail\Test;

use Hampel\SparkPostMail\Option\EmailTransport;
use Hampel\Symfony\Mailer\SparkPost\Transport\SparkPostApiTransport;

class TransportTest extends AbstractTest
{
	public function run()
	{
		if (!EmailTransport::isSparkPostEnabled())
		{
			$group = $this->app->finder('XF:OptionGroup')->whereId('emailOptions')->fetchOne();

			$this->errorMessage(\XF::phrase('sparkpostmail_sparkpost_not_enabled', [
				'optionurl' => $this->controller->buildLink('full:options/groups', $group) . "#emailTransport",
			]));
			return false;
		}

		$transport = $this->app->mailer()->getDefaultTransport();

		if (get_class($transport) != SparkPostApiTransport::class)
		{
			$this->errorMessage(\XF::phrase('sparkpostmail_wrong_transport'));
			return false;
		}

		$email = $this->data['email'];
		$transactional = $this->getCheckbox('transactional');

		$validator = $this->app->validator('Email');
		if (!$validator->isValid($email, $error))
		{
			$this->errorMessage(\XF::phrase('sparkpostmail_invalid_email_address'));
			return false;
		}

		/** @var \Hampel\SparkPostMail\XF\Mail\Mail $mail */
		$mail = $this->app->mailer()->newMail();
		$mail->setTemplate('sparkpostmail_outbound_email_test');
		$mail->setTo($email);
		$mail->setTransactional($transactional);

		try
		{
			$sent = $mail->send();
			if (!$sent)
			{
				$this->errorMessage(\XF::phrase('sparkpostmail_no_emails_sent')); // TODO: add failed recipients to error
				return false;
			}
		}
		catch (\Exception $e)
		{
			$this->processException($e);
			return false;
		}

		return $sent;
	}

}
