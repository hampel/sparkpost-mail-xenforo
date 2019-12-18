<?php namespace Hampel\SparkPostMail\XF\Job;

class UserEmail extends XFCP_UserEmail
{
	/**
	 * @param User $user
	 *
	 * @return \XF\Mail\Mail
	 */
	protected function getMail(\XF\Entity\User $user)
	{
		return $this->app->get('sparkpostmail')->setNonTransactional(parent::getMail($user));
	}
}
