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

		return $this->app->get('sparkpostmail')->setNonTransactional(parent::getMail($user));
	}
}
