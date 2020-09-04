<?php namespace Hampel\SparkPostMail\XF\Service\User;

use Hampel\SparkPostMail\Option\EmailTransport;

class Welcome extends XFCP_Welcome
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
			$mail->setTransactional(false); // set welcome emails to be non-transactional
		}

		return $mail;
	}
}
