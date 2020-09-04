<?php namespace Hampel\SparkPostMail\XF\Service\User;

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
			$this->app->get('sparkpostmail')->setNonTransactional($mail);
		}
		return $mail;
	}
}
