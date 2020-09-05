<?php namespace Hampel\SparkPostMail\XF\Mail;

use Hampel\SparkPostDriver\Message;
use Hampel\SparkPostMail\Option\EmailTransport;

class Mail extends XFCP_Mail
{
	public function __construct(\XF\Mail\Mailer $mailer, $templateName = null, array $templateParams = null)
	{
		parent::__construct($mailer, $templateName, $templateParams);

		if (EmailTransport::isSparkPostEnabled())
		{
			// replace the message with our SwiftSparkPost Message instead
			$this->message = new Message();
		}
	}

	public function setTo($email, $name = null)
	{
		// if we're in test mode - send all email to the mail sink
		if (EmailTransport::isTestModeEnabled())
		{
			$email .= '.sink.sparkpostmail.com';
		}

		return parent::setTo($email, $name);
	}

	public function setToUser(\XF\Entity\User $user)
	{
		parent::setToUser($user);

		if (EmailTransport::isSparkPostEnabled())
		{
			$username = $user->username;
			$user_id = $user->user_id;

			// add the username and user_id as metadata we can search on
			$this->getMessageObject()->setMetadata(compact('username', 'user_id'));
		}
		return $this;
	}

	public function setTemplate($name, array $params = [])
	{
		parent::setTemplate($name, $params);

		if (EmailTransport::isSparkPostEnabled())
		{
			// set the template as the campaign_id so we can associate message event data with original message type
			$this->getMessageObject()->setCampaignId($name);
		}

		return $this;
	}

	public function setTransactional($transactional)
	{
		if (EmailTransport::isSparkPostEnabled())
		{
			$this->getMessageObject()->setTransactional($transactional);
		}

		return $this;
	}

	public function setOpenTracking($openTracking)
	{
		if (EmailTransport::isSparkPostEnabled())
		{
			$this->getMessageObject()->setOpenTracking($openTracking);
		}

		return $this;
	}

	public function setClickTracking($clickTracking)
	{
		if (EmailTransport::isSparkPostEnabled())
		{
			$this->getMessageObject()->setOpenTracking($clickTracking);
		}

		return $this;
	}
}
