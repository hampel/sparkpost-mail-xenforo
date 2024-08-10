<?php namespace Hampel\SparkPostMail\XF\Mail;

use Hampel\SparkPostMail\Option\EmailTransport;
use Hampel\Symfony\Mailer\SparkPost\Mime\SparkPostEmail;
use XF\Entity\User;
use XF\Mail\Mail as XFMail;

class Mail extends XFCP_Mail
{
	public function __construct(\XF\Mail\Mailer $mailer, $templateName = null, array $templateParams = null)
	{
		parent::__construct($mailer, $templateName, $templateParams);

		if (EmailTransport::isSparkPostEnabled())
		{
			// replace the message with our SparkPostEmail class instead
			$this->email = new SparkPostEmail();
		}
	}

	public function setTo($email, $name = null): XFMail
	{
		// if we're in test mode - send all email to the mail sink
		if (EmailTransport::isTestModeEnabled())
		{
			$email .= '.sink.sparkpostmail.com';
		}

		return parent::setTo($email, $name);
	}

	public function setToUser(User $user): XFMail
	{
		parent::setToUser($user);

		if (EmailTransport::isSparkPostEnabled())
		{
			$username = $user->username;
			$user_id = $user->user_id;

			// add the username and user_id as metadata we can search on
			$this->email->setMetadata(compact('username', 'user_id'));
		}
		return $this;
	}

	public function setTemplate($name, array $params = []): XFMail
	{
		parent::setTemplate($name, $params);

		if (EmailTransport::isSparkPostEnabled())
		{
			// set the template as the campaign_id so we can associate message event data with original message type
			$this->email->setCampaignId($name);
		}

		return $this;
	}

	public function setTransactional($transactional): XFMail
	{
		if (EmailTransport::isSparkPostEnabled())
		{
			$this->email->setTransactional($transactional);
		}

		return $this;
	}

	public function setOpenTracking($openTracking): XFMail
	{
		if (EmailTransport::isSparkPostEnabled())
		{
			$this->email->setOpenTracking($openTracking);
		}

		return $this;
	}

	public function setClickTracking($clickTracking): XFMail
	{
		if (EmailTransport::isSparkPostEnabled())
		{
			$this->email->setClickTracking($clickTracking);
		}

		return $this;
	}
}
