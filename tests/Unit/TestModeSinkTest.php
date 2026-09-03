<?php namespace Tests\Unit;

use Hampel\SparkPostMail\SubContainer\SparkPost;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Test mode delivers to SparkPost's sink domain. Since 5.0.0 that is done by rewriting the SMTP
 * envelope rather than the To: header, so the message still reads as addressed to the real
 * recipient - the sink suffix used to be visible to anyone who received one.
 */
class TestModeSinkTest extends TestCase
{
	protected function setTestMode(bool $on): void
	{
		$this->setOptions(['emailTransport' => [
			'emailTransport' => 'sparkpost',
			'apiKey' => 'test-key',
			'testMode' => $on,
			'clickTracking' => false,
			'openTracking' => false,
		]]);
	}

	/** The dispatcher is private on Symfony's AbstractTransport, so reach it by reflection. */
	protected function dispatcherFor(bool $testMode)
	{
		$this->setTestMode($testMode);

		// a fresh sub-container re-runs initialize(), so the closures read the options just set
		$sub = new SparkPost($this->app()->container(), $this->app());
		$transport = $sub->transport();

		$property = (new \ReflectionClass(\Symfony\Component\Mailer\Transport\AbstractTransport::class))
			->getProperty('dispatcher');
		$property->setAccessible(true);

		return $property->getValue($transport);
	}

	public function test_no_envelope_rewriting_when_test_mode_is_off()
	{
		$this->assertNull($this->dispatcherFor(false));
	}

	public function test_test_mode_installs_the_sink_listener()
	{
		$this->assertNotNull($this->dispatcherFor(true));
	}

	public function test_the_sink_suffix_goes_on_the_envelope_not_the_headers()
	{
		$dispatcher = $this->dispatcherFor(true);

		$email = (new Email())->from('board@example.com')->to('member@example.com')->subject('s')->text('t');
		$envelope = new Envelope(new Address('board@example.com'), [new Address('member@example.com')]);

		$dispatcher->dispatch(new MessageEvent($email, $envelope, 'sparkpost+api://api.sparkpost.com'));

		// delivered to the sink ...
		$this->assertEquals(
			['member@example.com.sink.sparkpostmail.com'],
			array_map(fn(Address $a) => $a->getAddress(), $envelope->getRecipients())
		);

		// ... but the message still reads as addressed to the member
		$this->assertEquals(
			['member@example.com'],
			array_map(fn(Address $a) => $a->getAddress(), $email->getTo())
		);
	}
}
