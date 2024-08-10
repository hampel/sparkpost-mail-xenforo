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
        $mail = parent::getMail($user);

		if (EmailTransport::isSparkPostEnabled())
		{
            $mail->setTransactional(false); // set this to non-transactional
        }

		return $mail;
	}
}
