<?php namespace Hampel\SparkPostMail\EmailBounce;

use Carbon\Carbon;
use Hampel\Json\Json;
use Hampel\Json\JsonException;
use Hampel\SparkPostMail\Entity\MessageEvent;
use Hampel\SparkPostMail\Repository\EmailBounce;
use XF\App;
use XF\EmailBounce\Processor as xfProcessor;
use XF\Service\User\EmailStop;

class Processor
{
	protected $classifications = [
		1 => ['type' => 'undetermined', 'name' => 'undetermined'],
		10 => ['type' => 'hard', 'name' => 'invalid_recipient'],
		20 => ['type' => 'soft', 'name' => 'soft_bounce'],
		21 => ['type' => 'soft', 'name' => 'dns_failure'],
		22 => ['type' => 'soft', 'name' => 'mailbox_full'],
		23 => ['type' => 'soft', 'name' => 'too_large'],
		24 => ['type' => 'soft', 'name' => 'timeout'],
		25 => ['type' => 'admin', 'name' => 'admin_failure'],
		26 => ['type' => 'admin', 'name' => 'smart_send_suppression'],
		30 => ['type' => 'hard', 'name' => 'generic_bounce_no_rcpt'],
		40 => ['type' => 'soft', 'name' => 'generic_bounce'],
		50 => ['type' => 'block', 'name' => 'mail_block'],
		51 => ['type' => 'block', 'name' => 'spam_block'],
		52 => ['type' => 'block', 'name' => 'spam_content'],
		53 => ['type' => 'block', 'name' => 'prohibited_attachment'],
		54 => ['type' => 'block', 'name' => 'relaying_denied'],
		60 => ['type' => 'soft', 'name' => 'auto_reply'],
		70 => ['type' => 'soft', 'name' => 'transient_failure'],
		80 => ['type' => 'admin', 'name' => 'subscribe'],
		90 => ['type' => 'hard', 'name' => 'unsubscribe'],
		100 => ['type' => 'soft', 'name' => 'challenge_response'],
	];

	/**
	 * @var App
	 */
	protected $app;

	public function __construct(App $app)
	{
		$this->app = $app;
	}

	/**
	 * @param array $event
	 *
	 * @return ParsedMessage
	 */
	public function parseEvent(MessageEvent $event)
	{
		$payload = $event['payload'];

		$parsed = new ParsedMessage();
		$parsed->date = $event['timestamp'];
		$parsed->messageType = $event['type'];
		$parsed->messageDate = isset($payload['injection_time']) ? Carbon::parse($payload['injection_time'])->timestamp : 0;
		$parsed->recipient = $payload['rcpt_to'] ?? null;
		$parsed->bounceClass = $payload['bounce_class'] ?? 0;
		$parsed->reason = $payload['reason'] ?? '';
		$parsed->transactional = isset($payload['transactional']) && ($payload['transactional'] == 1);
		$parsed->campaign = $payload['campaign_id'] ?? null;
		$parsed->meta = $payload['rcpt_meta'] ?? null;
		$parsed->subject = $payload['subject'] ?? '';

		try {
			$parsed->rawMessage = Json::encode($payload, JSON_PRETTY_PRINT);
		}
		catch (JsonException $e)
		{
			// Json encoding failed - serialize it instead
			$parsed->rawMessage = serialize($event['payload']);
		}

		if (isset($parsed->recipient))
		{
			/** @var User $user */
			$parsed->user = $this->app->em()->findOne('XF:User', ['email' => $parsed->recipient]);
		}
		else
		{
			$parsed->user = null;
		}

		return $this->processEvent($parsed);
	}

	public function processEvent(ParsedMessage $event)
	{
		$action = null;

		if (isset($event->user))
		{
			switch ($event->messageType)
			{
				case 'bounce':
				case 'policy_rejection':
				case 'out_of_band':
				case 'generation_rejection':
				case 'spam_complaint':
					$action = $this->processBounce($event);
					// bounce
					break;
				case 'list_unsubscribe':
				case 'link_unsubscribe':
					$action = $this->processUnsubscribe($event);
					// unsubscribe
					break;
				case 'delivery':
				case 'injection':
				case 'delay':
				case 'open':
				case 'initial_open':
				case 'click':
				case 'generation_failure':
					// ignore ... shouldn't be getting these anyway!
					$action = 'ignore';
					break;
				default:
					// shouldn't get here
					$action = 'unknown';
					break;
			}
		}

		return $this->logBounceMessage($action, $event);
	}

	public function processBounce(ParsedMessage $event)
	{
		/** @var xfProcessor $processor */
		$processor = $this->app->get('bounce')->processor();

		switch ($event->bounceClass) {
			case 10: // Invalid Recipient
			case 30: // Generic Bounce: No RCPT
			case 90: // Unsubscribe
				// hard
				return $processor->takeBounceAction($event->user, 'hard', $event->date);
			case 50: // Mail Block
			case 51: // Spam Block
			case 52: // Spam Content
			case 53: // Prohibited Attachment
			case 54: // Relaying Denied
				// blocked
				return $this->processBlock($event);
			case 20: // Soft Bounce
			case 21: // DNS Failure
			case 22: // Mailbox Full
			case 23: // Too Large
			case 24: // Timeout
			case 40: // Generic Bounce
			case 60: // Auto-Reply
			case 70: // Transient Failure
			case 100: // Challenge-Response
				// soft
				return $processor->takeBounceAction($event->user, 'soft', $event->date);
			case 25: // Admin Failure
			case 26: // Smart Send Suppression
				// treat admin failures as hard bounces
				return $processor->takeBounceAction($event->user, 'hard', $event->date);
			default:
				return 'unknown';
		}
	}

	public function processBlock(ParsedMessage $event)
	{
		// nothing to do for now
		return 'block';
	}

	public function processUnsubscribe(ParsedMessage $event)
	{
		/** @var \XF\Service\User\EmailStop $emailStopper */
		$emailStopper = $this->app->service('XF:User\EmailStop', $event->user);

		$stopped = false;

		if ($event->transactional)
		{
			$transactionalStopMap = [
				'conversation' => 'conversations',
				'watched_forum' => 'forum',
				'watched_thread' => 'thread',
				'xfmg_watched_album' => 'xfmg_album',
				'xfmg_watched_category' => 'xfmg_category',
				'xfmg_watched_media' => 'xfmg_media',
				'xfrm_watched_category' => 'resource_category',
				'xfrm_watched_resource' => 'resource',
			];

			// check our campaign to see if it matches any of our content types
			if (!empty($event->campaign))
			{
				foreach ($transactionalStopMap as $content => $stop)
				{
					if (substr($event->campaign, 0, strlen($content)) == $content)
					{
						$emailStopper->stop($stop);

						$stopped = true;
						break; // stop looking - we found what we were looking for
					}
				}
			}

			if (!$stopped)
			{
				// didn't find anything content-specific, stop everything just in case
				// note that we can't really continue interacting with this user's account if they don't want transactional emails, so just disable everything
				// SparkPost should have added them to the transactional stop list anyway - but this way we won't even try to send them emails of any kind

				$emailStopper->stopAll();
			}
		}
		else
		{
			$nonTransactionalStopMap = [
				'prepared_email' => 'list', // UserEmail and Welcome emails both used "prepared_email" template
			];

			$this->app->fire('sparkpostmail_non_transactional_stop_map', [&$nonTransactionalStopMap]);

			// check our campaign to see if it matches any of our content types
			if (!empty($event->campaign))
			{
				foreach ($nonTransactionalStopMap as $content => $stop)
				{
					if (substr($event->campaign, 0, strlen($content)) == $content)
					{
						$emailStopper->stop($stop);

						$stopped = true;
						break; // stop looking - we found what we were looking for
					}
				}
			}

			if (!$stopped)
			{
				// didn't find anything that matches - unsubscribe from all non-transactional emails, just in case
				//
				// no need to unsubscribe from transactional emails - just stop mailing lists any any other
				// non-transaction emails we send

				$this->stopAllNonTransactional($emailStopper);
			}
		}

		return 'unsubscribe';
	}

	public function stopAllNonTransactional(EmailStop $emailStopper)
	{
		// mailing lists are non-transactional - stop them
		$emailStopper->stopMailingList();

		// other addons which send their own non-transactional emails can over-ride this function to also stop their
		// own emails
	}

	public function logBounceMessage($action, ParsedMessage $event)
	{
		/** @var EmailBounce $bounceRepo */
		$bounceRepo = $this->app->repository('Hampel\SparkPostMail:EmailBounce');

		return $bounceRepo->logBounceMessage(
			$event->messageDate,
			$event->messageType,
			$action ?: '',
			isset($event->user) ? $event->user->user_id : null,
			$event->recipient,
			substr($event->rawMessage, 0, 1024 * 1024), // limit logging to 1MB to prevent potential insert issues
			$event->bounceClass,
			$event->reason
		);
	}

	public function getPhrasedClassifications()
	{
		$phrase_prefix = 'sparkpostmail_bounce_classification_';

		return array_map(function ($value) use ($phrase_prefix)
		{
			$value['type_phrase'] = \XF::phrase("{$phrase_prefix}{$value['type']}");
			$value['name_phrase'] = \XF::phrase("{$phrase_prefix}{$value['name']}");
			$value['desc_phrase'] = \XF::phrase("{$phrase_prefix}{$value['name']}_desc");
			return $value;
		}, $this->classifications);
	}

}
