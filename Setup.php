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
		// nothing to do yet
	}

	public function uninstall(array $stepParams = [])
	{
		$this->schemaManager()->dropTable('xf_sparkpost_mail_message_event');
	}

	/**
	 * Perform additional requirement checks.
	 *
	 * @param array $errors Errors will block the setup from continuing
	 * @param array $warnings Warnings will be displayed but allow the user to continue setup
	 *
	 * @return void
	 */
	public function checkRequirements(&$errors = [], &$warnings = [])
	{
		if (\XF::$versionId >= 2020000)
		{
			$errors[] = 'This version of Hampel/SparkPostMail is not compatible with XenForo v2.2 - please install v2.x of the SparkPostMail addon';
		}
	}
}