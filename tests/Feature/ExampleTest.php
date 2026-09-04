<?php namespace Tests\Feature;

use Tests\TestCase;

/**
 * Placeholder so the Feature suite collects.
 *
 * phpunit.xml declares a Feature suite, and a suite that collects nothing is not caught by
 * failOnEmptyTestSuite - that flag fires when the whole RUN collects nothing, and the Unit
 * suite always does. Without this the run reports OK while `--testsuite Feature` exits 1.
 * A .gitkeep keeps the directory in git but does not make the suite collect.
 */
class ExampleTest extends TestCase
{
	public function testBasicTest()
	{
		$this->assertTrue(true);
	}
}
