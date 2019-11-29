<?php namespace Hampel\SparkPostMail\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class MessageEvent extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_sparkpost_mail_message_event';
		$structure->shortName = 'Hampel\SparkPostMail:MessageEvent';
		$structure->primaryKey = 'event_id';
		$structure->columns = [
			'event_id' => ['type' => self::STR, 'maxLength' => 32, 'required' => true],
			'type' => ['type' => self::STR, 'maxLength' => 32, 'required' => true],
			'recipient' => ['type' => self::STR, 'maxLength' => 120, 'required' => true],
			'processed' => ['type' => self::BOOL, 'default' => false],
			'timestamp' => ['type' => self::UINT, 'required' => true],
			'payload' => ['type' => self::JSON_ARRAY, 'required' => true], // safe to change this to JSON_ARRAY since we aren't actually using it yet
		];

		return $structure;
	}
}
