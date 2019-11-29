<?php namespace Hampel\SparkPostMail\Finder;

use XF\Mvc\Entity\Finder;

class MessageEvent extends Finder
{
	public function unprocessed($limit = 100)
	{
		$this->where('processed', 0);
		$this->setDefaultOrder('timestamp', 'ASC');
		$this->limit($limit);

		return $this;
	}
}
