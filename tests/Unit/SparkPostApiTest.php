<?php namespace Tests\Unit;

use Hampel\SparkPostMail\Api\SparkPostApi;
use Tests\TestCase;

class SparkPostApiTest extends TestCase
{
	protected SparkPostApi $api;

	public function setUp(): void
	{
		parent::setUp();

		$this->api = new SparkPostApi($this->app());
	}

	/**
	 * XF forces date_default_timezone_set('UTC') at startup, so the format is unambiguous.
	 * SparkPost rejects anything but Y-m-d\TH:i on the from/to query parameters.
	 */
	public function test_timestampToSparkPostDate_formats_for_the_api()
	{
		$this->assertEquals('2025-09-02T08:00', $this->api->timestampToSparkPostDate(1756800000));
	}

	public function test_timestampToSparkPostDate_truncates_seconds()
	{
		$this->assertEquals('2025-09-02T08:00', $this->api->timestampToSparkPostDate(1756800059));
	}

	/**
	 * Paged responses come back with links.next carrying the full /api/v1/ path, which would
	 * double up against the base url. Regression for the 2.1.1 bugfix.
	 */
	public function test_stripUriPrefix_removes_the_api_version_prefix()
	{
		$this->assertEquals(
			'events/message?page=2&per_page=1000',
			$this->api->stripUriPrefix('/api/v1/events/message?page=2&per_page=1000')
		);
	}

	public function test_stripUriPrefix_leaves_an_unprefixed_uri_alone()
	{
		$this->assertEquals('events/message', $this->api->stripUriPrefix('events/message'));
	}

	public function test_stripUriPrefix_only_strips_a_leading_prefix()
	{
		$this->assertEquals(
			'events/message?next=/api/v1/foo',
			$this->api->stripUriPrefix('events/message?next=/api/v1/foo')
		);
	}
}
