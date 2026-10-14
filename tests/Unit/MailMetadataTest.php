<?php namespace Tests\Unit;

use Hampel\SparkPost\Transport\Mime\SparkPostEmail;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Two things have to reach SparkPost with every message for the unsubscribe design to work at
 * all: the campaign id, which is the mail template's name, and the recipient's user metadata.
 * MessageEventService reads that campaign back and prefix-matches it against a stop map to
 * decide what to unsubscribe someone from - so a message whose campaign never arrives is a
 * message whose unsubscribe falls through to the catch-all and stops everything.
 *
 * Nothing covered this before, and the reason is worth knowing: the values are written onto the
 * message object rather than returned by anything, so they were observable only in a real send.
 * `getEmailObject()` is what makes them assertable without one.
 *
 * The last test here is the other half of the rule in CLAUDE.md - every extension guards on
 * Option\EmailTransport::isSparkPostEnabled(), so the add-on must be completely inert when
 * another transport is selected.
 */
class MailMetadataTest extends TestCase
{
	protected function setTransport(string $type): void
	{
		// The SMTP keys are supplied even for the SparkPost case: XF\Mail\Mailer reads them
		// unguarded when it builds a transport, so an array holding only this add-on's keys
		// warns on "smtpSsl" before any of this add-on's code runs.
		$this->setOptions(['emailTransport' => [
			'emailTransport' => $type,
			'apiKey' => $type === 'sparkpost' ? 'test-key' : '',
			'testMode' => false,
			'clickTracking' => false,
			'openTracking' => false,
			'smtpHost' => 'localhost',
			'smtpPort' => 1025,
			'smtpAuth' => '',
			'smtpLoginUsername' => '',
			'smtpLoginPassword' => '',
			'smtpSsl' => false,
		]]);
	}

	/**
	 * makeEntity() assigns normally, and a primary key cannot be assigned - XF answers
	 * "Column 'user_id' is read only, can only be set with forceSet". setTrusted() is the way
	 * in, as BounceClassificationTest already does it.
	 */
	protected function member(): \XF\Entity\User
	{
		$user = $this->app()->em()->create(\XF\Entity\User::class);
		$user->setTrusted('user_id', 42);
		$user->username = 'Member';
		$user->email = 'member@example.com';

		return $user;
	}

	public function test_the_campaign_id_is_the_mail_template_name()
	{
		$this->setTransport('sparkpost');

		$mail = $this->app()->mailer()->newMail();
		$mail->setTemplate('watched_thread_reply');

		$email = $mail->getEmailObject();

		$this->assertInstanceOf(SparkPostEmail::class, $email);
		$this->assertSame(
			'watched_thread_reply',
			$email->getCampaignId(),
			'the campaign id is what processUnsubscribe() prefix-matches against the stop map'
		);
	}

	public function test_the_recipient_is_attached_as_searchable_metadata()
	{
		$this->setTransport('sparkpost');

		$mail = $this->app()->mailer()->newMail();
		$mail->setToUser($this->member());

		$this->assertSame(
			['username' => 'Member', 'user_id' => 42],
			$mail->getEmailObject()->getMetadata()
		);
	}

	public function test_the_transactional_flag_reaches_the_message()
	{
		$this->setTransport('sparkpost');

		$mail = $this->app()->mailer()->newMail();
		$mail->setTransactional(false);

		$this->assertFalse(
			$mail->getEmailObject()->isTransactional(),
			'a non-transactional message is what makes an unsubscribe actionable rather than ignored'
		);
	}

	public function test_the_add_on_is_inert_when_another_transport_is_selected()
	{
		$this->setTransport('smtp');

		$mail = $this->app()->mailer()->newMail();
		$mail->setTemplate('watched_thread_reply')->setToUser($this->member());

		$email = $mail->getEmailObject();

		$this->assertInstanceOf(Email::class, $email);
		$this->assertNotInstanceOf(
			SparkPostEmail::class,
			$email,
			'every extension guards on isSparkPostEnabled(); none may act when SMTP is selected'
		);
	}
}
