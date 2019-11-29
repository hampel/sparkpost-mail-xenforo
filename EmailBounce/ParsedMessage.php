<?php namespace Hampel\SparkPostMail\EmailBounce;

class ParsedMessage
{
	public $date;
	public $messageType;
	public $messageDate;
	public $recipient;
	public $bounceClass;
	public $reason;
	public $transactional;
	public $campaign;
	public $meta;
	public $subject;

	public $rawMessage;

	/** @var \XF\Entity\User */
	public $user;
}
