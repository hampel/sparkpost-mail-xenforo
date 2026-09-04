<?php namespace Tests\Unit;

use Hampel\SparkPostMail\EmailBounce\ParsedMessage;
use Hampel\SparkPostMail\Service\MessageEventService;
use Mockery as m;
use Tests\TestCase;

/**
 * Which XenForo email a user is unsubscribed from when SparkPost reports a list- or
 * link-unsubscribe.
 *
 * The routing is a prefix match of the SparkPost campaign against a map, and the campaign is
 * the email template name - `Mail::setTemplate` puts it there. The consequential part is the
 * fallback: an unrecognised campaign on a TRANSACTIONAL email stops every email the board can
 * send that user, so a new mail type that is not in the map costs the user everything rather
 * than the one thing they clicked unsubscribe on.
 *
 * The campaigns below are real XF 2.3 email template names, not invented ones, so these break
 * if a template is renamed as well as if the map is.
 */
class UnsubscribeRoutingTest extends TestCase
{
	/**
	 * @dataProvider transactionalCampaigns
	 */
	public function test_a_transactional_unsubscribe_stops_only_the_matching_content_type(string $campaign, string $expectedStop)
	{
		// resolve the service under test BEFORE mocking the factory - mockService() replaces the
		// container's whole 'service' factory, so every later app()->service() call is a mock
		$service = $this->app()->service(MessageEventService::class);

		$this->mockService('XF:User\EmailStopService', function ($mock) use ($expectedStop)
		{
			$mock->expects()->stop($expectedStop);
			$mock->shouldNotReceive('stopAll');
		});

		$this->assertEquals('unsubscribe', $service->processUnsubscribe($this->event($campaign, true)));
	}

	public static function transactionalCampaigns(): array
	{
		return [
			'conversation_create'            => ['conversation_create', 'conversations'],
			'conversation_invite'            => ['conversation_invite', 'conversations'],
			'watched_forum_reply'            => ['watched_forum_reply', 'forum'],
			'watched_forum_thread'           => ['watched_forum_thread', 'forum'],
			'watched_thread_reply'           => ['watched_thread_reply', 'thread'],
			'xfmg_watched_album_media'       => ['xfmg_watched_album_media', 'xfmg_album'],
			'xfmg_watched_category_media'    => ['xfmg_watched_category_media', 'xfmg_category'],
			'xfmg_watched_media_comment'     => ['xfmg_watched_media_comment', 'xfmg_media'],
			'xfrm_watched_category_resource' => ['xfrm_watched_category_resource', 'resource_category'],
			'xfrm_watched_resource_update'   => ['xfrm_watched_resource_update', 'resource'],
		];
	}

	/**
	 * The expensive fallback, and the reason a new transactional mail type has to be added to the
	 * map: stopAll() disables every email XenForo sends this user, transactional included.
	 *
	 * @dataProvider unmatchedCampaigns
	 */
	public function test_an_unrecognised_transactional_campaign_stops_everything(?string $campaign)
	{
		$service = $this->app()->service(MessageEventService::class);

		$this->mockService('XF:User\EmailStopService', function ($mock)
		{
			$mock->expects()->stopAll();
			$mock->shouldNotReceive('stop');
		});

		$this->assertEquals('unsubscribe', $service->processUnsubscribe($this->event($campaign, true)));
	}

	/**
	 * The non-transactional fallback is deliberately narrower - it stops mailing lists and leaves
	 * transactional mail working, because the board still has to be able to talk to the account.
	 *
	 * @dataProvider unmatchedCampaigns
	 */
	public function test_an_unrecognised_non_transactional_campaign_stops_only_mailing_lists(?string $campaign)
	{
		$service = $this->app()->service(MessageEventService::class);

		$this->mockService('XF:User\EmailStopService', function ($mock)
		{
			$mock->expects()->stopMailingList();
			$mock->shouldNotReceive('stopAll');
			$mock->shouldNotReceive('stop');
		});

		$this->assertEquals('unsubscribe', $service->processUnsubscribe($this->event($campaign, false)));
	}

	public static function unmatchedCampaigns(): array
	{
		return [
			'a campaign nothing maps'  => ['a_template_added_next_year'],
			'an empty campaign'        => [''],
			'no campaign at all'       => [null],
		];
	}

	/**
	 * prepared_email is the one non-transactional template the add-on knows: XF\Job\UserEmail and
	 * the Welcome service both send under it.
	 */
	public function test_a_prepared_email_unsubscribe_stops_the_mailing_list_subscription()
	{
		$service = $this->app()->service(MessageEventService::class);

		$this->mockService('XF:User\EmailStopService', function ($mock)
		{
			$mock->expects()->stop('list');
			$mock->shouldNotReceive('stopAll');
			$mock->shouldNotReceive('stopMailingList');
		});

		$this->assertEquals('unsubscribe', $service->processUnsubscribe($this->event('prepared_email', false)));
	}

	/**
	 * The non-transactional map is extensible so another add-on's own mail can route somewhere
	 * better than the stop-everything-non-transactional fallback.
	 */
	public function test_another_addon_can_add_to_the_non_transactional_map()
	{
		$this->app()->extension()->addListener(
			'sparkpostmail_non_transactional_stop_map',
			function (array &$map) { $map['newsletter'] = 'newsletter_list'; }
		);

		$service = $this->app()->service(MessageEventService::class);

		$this->mockService('XF:User\EmailStopService', function ($mock)
		{
			$mock->expects()->stop('newsletter_list');
			$mock->shouldNotReceive('stopMailingList');
		});

		$this->assertEquals('unsubscribe', $service->processUnsubscribe($this->event('newsletter_september', false)));
	}

	/**
	 * The transactional map is NOT extensible by the same event - an add-on sending transactional
	 * mail under its own template gets the stop-everything fallback, which is worth knowing before
	 * assuming the event covers both.
	 */
	public function test_the_event_does_not_reach_the_transactional_map()
	{
		$this->app()->extension()->addListener(
			'sparkpostmail_non_transactional_stop_map',
			function (array &$map) { $map['newsletter'] = 'newsletter_list'; }
		);

		$service = $this->app()->service(MessageEventService::class);

		$this->mockService('XF:User\EmailStopService', function ($mock)
		{
			$mock->expects()->stopAll();
			$mock->shouldNotReceive('stop');
		});

		$this->assertEquals('unsubscribe', $service->processUnsubscribe($this->event('newsletter_september', true)));
	}

	protected function event(?string $campaign, bool $transactional): ParsedMessage
	{
		$user = $this->app()->em()->create(\XF\Entity\User::class);
		$user->setTrusted('user_id', 1);
		$user->username = 'probe';

		$event = new ParsedMessage();
		$event->user = $user;
		$event->date = \XF::$time;
		$event->bounceClass = 0;
		$event->messageType = 'list_unsubscribe';
		$event->campaign = $campaign;
		$event->transactional = $transactional;

		return $event;
	}
}
