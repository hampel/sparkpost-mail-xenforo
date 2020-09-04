<?php namespace Hampel\SparkPostMail\XF\Job;

use Hampel\SparkPostMail\Option\EmailTransport;

class UserEmail extends XFCP_UserEmail
{
	/**
	 * @param User $user
	 *
	 * @return \XF\Mail\Mail
	 */
	protected function getMail(\XF\Entity\User $user)
	{
		if (!EmailTransport::isSparkPostEnabled())
		{
			return parent::getMail($user);
		}

		// replace the entire function since we don't need to set list unsubscribe - SparkPost does that for us
		$mailer = $this->app->mailer();
		$mail = $mailer->newMail();
		$mail->setToUser($user);
		$mail->setTransactional(false); // set this to non-transactional

		return $mail;
	}
}
