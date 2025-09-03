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

    public function toArray()
    {
        return [
            'date' => $this->date,
            'messageType' => $this->messageType,
            'messageDate' => $this->messageDate,
            'recipient' => $this->recipient,
            'bounceClass' => $this->bounceClass,
            'reason' => $this->reason,
            'transactional' => $this->transactional,
            'campaign' => $this->campaign,
            'meta' => $this->meta,
            'subject' => $this->subject,
        ];
    }
}
