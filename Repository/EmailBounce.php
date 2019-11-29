<?php namespace Hampel\SparkPostMail\Repository;

use XF\Mvc\Entity\Repository;

class EmailBounce extends Repository
{
	public function logBounceMessage($email_date, $message_type, $action_taken, $user_id, $recipient, $raw_message, $status_code, $diagnostic_info)
	{
		$bounce = $this->em->create('XF:EmailBounceLog');
		$bounce->bulkSet(compact('email_date', 'message_type', 'action_taken', 'user_id', 'recipient', 'raw_message', 'status_code', 'diagnostic_info'));
		$bounce->save();

		return $bounce;
	}
}
