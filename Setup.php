<?php namespace Hampel\SparkPostMail;

use XF\AddOn\AbstractSetup;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
	public function install(array $stepParams = [])
	{
		$this->schemaManager()->createTable('xf_sparkpost_mail_message_event', function(Create $table)
		{
			$table->addColumn('event_id', 'varchar', 32);
			$table->addColumn('type', 'varchar', 32);
			$table->addColumn('recipient', 'varchar', 120);
			$table->addColumn('processed', 'tinyint')->setDefault(0);
			$table->addColumn('timestamp', 'int');
			$table->addColumn('payload', 'text');
			$table->addPrimaryKey('event_id');
			$table->addKey('processed');
		});
	}

	public function upgrade(array $stepParams = [])
	{
		if ($this->addOn->version_id < 2000000)
		{
			$db = $this->db();

			$emailTransport = $db->fetchOne('SELECT option_value FROM xf_option WHERE option_id = \'emailTransport\'');
			$emailTransport = json_decode($emailTransport, true);
			if ($emailTransport &&
				isset($emailTransport['emailTransport']) &&
				$emailTransport['emailTransport'] == 'sparkpost' &&
				isset($emailTransport['apiKey']) &&
				!empty($emailTransport['apiKey']))
			{
				$clickTracking = $db->fetchOne('SELECT option_value FROM xf_option WHERE option_id = \'sparkpostmailClickTracking\'');
				if (is_null($clickTracking))
				{
					$emailTransport['clickTracking'] = false;
				}
				else
				{
					$emailTransport['clickTracking'] = boolval($clickTracking);
				}

				$openTracking = $db->fetchOne('SELECT option_value FROM xf_option WHERE option_id = \'sparkpostmailOpenTracking\'');
				if (is_null($openTracking))
				{
					$emailTransport['openTracking'] = false;
				}
				else
				{
					$emailTransport['openTracking'] = boolval($openTracking);
				}

				$testMode = $db->fetchOne('SELECT option_value FROM xf_option WHERE option_id = \'sparkpostmailTestMode\'');
				if (is_null($testMode))
				{
					$emailTransport['testMode'] = false;
				}
				else
				{
					$emailTransport['testMode'] = boolval($testMode);
				}

				$this->executeUpgradeQuery('UPDATE xf_option SET option_value = ? WHERE option_id = \'emailTransport\'', json_encode($emailTransport));
			}
		}
	}

	public function uninstall(array $stepParams = [])
	{
		$this->schemaManager()->dropTable('xf_sparkpost_mail_message_event');
	}

	public function checkRequirements(&$errors = [], &$warnings = [])
	{
		$vendorDirectory = sprintf("%s/vendor", $this->addOn->getAddOnDirectory());
		if (!file_exists($vendorDirectory))
		{
			$errors[] = "vendor folder does not exist - cannot proceed with addon install";
		}
	}
}