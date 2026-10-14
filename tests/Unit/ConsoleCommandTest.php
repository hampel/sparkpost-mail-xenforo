<?php namespace Tests\Unit;

use Tests\TestCase;

/**
 * The four sparkpost:* commands had no coverage of any kind until the test framework could load
 * them, and the gap mattered more than four untested commands normally would: XenForo's runner
 * walks Cli/Command and loads every PHP file it finds, so a command that cannot be constructed
 * takes down `cmd.php` itself - every command on the install, not just this add-on's.
 *
 * That is also why this asserts the whole set rather than naming each one. A command added later
 * with no test of its own is covered by the same assertion.
 *
 * Note what the helper counts: commands, not files. Cli/Command/AbstractLoggingCommand.php sits
 * beside the four and is loaded but left out of the list, because it is not a Symfony Command.
 */
class ConsoleCommandTest extends TestCase
{
	public function test_every_console_command_loads()
	{
		$this->assertConsoleCommandsLoad('Hampel/SparkPostMail');
	}
}
