<?php namespace Tests\Unit;

use Tests\TestCase;

/**
 * The two template modifications are what put SparkPost on the email transport options page, and
 * they are the part of this add-on most likely to break silently: a modification that stops
 * matching after a XenForo upgrade applies to nothing, the choice disappears from the page, and
 * the add-on carries on working for everyone who already selected it. One such modification here
 * had already died that way, targeting a template a later XenForo removed.
 *
 * Until test framework 4.2 this could only be checked by eye, or by reading
 * xf_template_modification_log on a running forum - which this add-on's own release testing did,
 * in a sandbox, by hand.
 */
class TransportOptionTemplateTest extends TestCase
{
	public function test_both_transport_option_modifications_apply()
	{
		$this->assertTemplateModificationApplied('sparkpostmail_option_email_transport_setup');
		$this->assertTemplateModificationApplied('sparkpostmail_option_template_advancedEmailTranspo');
	}
}
