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
}