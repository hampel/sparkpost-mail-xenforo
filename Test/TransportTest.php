<?php namespace Hampel\SparkPostMail\Test;

use SwiftSparkPost\Option;
use SwiftSparkPost\Transport;
use Hampel\SparkPostMail\Option\ApiKey;

class TransportTest extends AbstractTest
{
	public function run()
	{
		$apikey = ApiKey::get();
		if (empty($apikey))
		{
			$group = $this->app->finder('XF:OptionGroup')->whereId('sparkpostmail')->fetchOne();

			$this->errorMessage(\XF::phrase('sparkpostmail_error_apikey_required', [
				'optionurl' => $this->controller->buildLink('full:options/groups', $group) . "#sparkpostmailApiKey",
			]));
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

		/** @var \SparkPost\XF\Mail\SparkPostMail $mail */
		$mail = $this->app->mailer()->newMail();
		$mail->setTo($email);
		$mail->setContent(
			\XF::phrase('sparkpostmail_outbound_email_test_subject', ['board' => \XF::options()->boardTitle]),
			\XF::phrase('sparkpostmail_outbound_email_test_body', [
				'username' => \XF::visitor()->username,
				'board' => \XF::options()->boardTitle,
				'boardurl' => \XF::options()->boardUrl,
			])
		);

		$mail->getMessageObject()->setOptions([
			Option::TRANSACTIONAL => $transactional,
		]);

		$transport = $this->app->mailer()->getDefaultTransport();

		if (get_class($transport) != Transport::class)
		{
			$this->errorMessage(\XF::phrase('sparkpostmail_wrong_transport'));
			return false;
		}

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
