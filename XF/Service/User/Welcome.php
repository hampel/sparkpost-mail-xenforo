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
		return $this->app->get('sparkpostmail')->setNonTransactional(parent::getMail($user));
	}
}
