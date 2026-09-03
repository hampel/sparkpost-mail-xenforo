<?php namespace Tests;

use Hampel\Testing\TestCase as BaseTestCase;
use Psr\Log\NullLogger;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    public function setUp(): void
    {
        parent::setUp();

        // Logging is a soft dependency on another add-on. Under $addonsToLoad isolation that
        // add-on's listener still registers the container key while its composer autoload is
        // not set up, so the channel resolves to a class that cannot be loaded. Tests must not
        // depend on what else is installed - LoggerFallbackTest re-registers the real binding
        // itself where it needs to exercise it.
        $this->swap('sparkpostmail.log', new NullLogger());
    }

    /*
     * Set $rootDir to '../../../..' if you use a vendor in your addon id (ie <Vendor/AddonId>)
     * Otherwise, set this to '../../..' for no vendor
     *
     * No trailing slash!
     */
    protected $rootDir = '../../../..';

    /**
     * @var array $addonsToLoad an array of XenForo addon ids to load
     *
     * Specifying an array of addon ids will cause only those addons to be loaded - useful for isolating your addon for
     * testing purposes
     *
     * Leave empty to load all addons
     */
    protected $addonsToLoad = ['Hampel/SparkPostMail'];

	/**
	 * Helper function to load mock data from a file (eg json)
	 * To use, create a "mock" folder relative to the tests folder, eg:
	 * 'src/addons/MyVendor/MyAddon/tests/mock'
	 *
	 * @param $file
	 *
	 * @return false|string
	 */
	protected function getMockData($file)
	{
		return file_get_contents(__DIR__ . '/mock/' . $file);
	}
}
