<?php namespace Hampel\SparkPostMail\Service;

use Hampel\SparkPost\MessageEvent\BounceClass;
use Hampel\SparkPost\MessageEvent\BounceClassification;
use Hampel\SparkPostMail\EmailBounce\ParsedMessage;
use Hampel\SparkPostMail\Entity\MessageEvent;
use Hampel\SparkPostMail\Repository\MessageEventRepository;
use Hampel\SparkPostMail\Traits\LogTrait;
use XF\EmailBounce\Processor;
use XF\Mvc\Entity\ArrayCollection;
use XF\Service\AbstractService;
use XF\Service\User\EmailStopService;

class MessageEventService extends AbstractService
{
    use LogTrait;

    protected ArrayCollection $events;

    protected $timeLimit = 0;


    protected function setup()
    {
        $this->setLogger($this->app->container('sparkpostmail.log'));
        $this->setContext(['service' => MessageEventService::class]);
    }

    public function setEvents(ArrayCollection $events)
    {
        $this->events = $events;
    }

    public function setTimeLimit($timeLimit)
    {
        $this->timeLimit = $timeLimit;
    }

    public function processEvents() : int
    {
        $start = microtime(true);
        $count = 0;

        foreach ($this->events as $event)
        {
            $this->parseEvent($event);

            $count++;

            $event->processed = 1;
            $event->save();

            if (microtime(true) - $start >= $this->timeLimit) break;
        }

        return $count;
    }

    public function parseEvent(MessageEvent $event)
    {
        $payload = $event['payload'];

        $parsed = new ParsedMessage();
        $parsed->date = $event['timestamp'];
        $parsed->messageType = $event['type'];
        $parsed->messageDate = isset($payload['injection_time']) ? (new \DateTimeImmutable($payload['injection_time']))->getTimestamp() : 0;
        $parsed->recipient = $payload['rcpt_to'] ?? null;
        $parsed->bounceClass = $payload['bounce_class'] ?? 0;
        $parsed->reason = $payload['reason'] ?? '';
        $parsed->transactional = isset($payload['transactional']) && ($payload['transactional'] == 1);
        $parsed->campaign = $payload['campaign_id'] ?? null;
        $parsed->meta = $payload['rcpt_meta'] ?? null;
        $parsed->subject = $payload['subject'] ?? '';

        $this->info("Message event", $parsed->toArray());

        $parsed->rawMessage = json_encode($payload, JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR);

        if (isset($parsed->recipient))
        {
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
        else
        {
            $this->info("No user found for recipient - ignoring message event", ['recipient' => $event->recipient]);
        }

        return $this->logBounceMessage($action, $event);
    }

    public function processBounce(ParsedMessage $event)
    {
        /** @var Processor $processor */
        $processor = $this->app->get('bounce')->processor();

        $user = $event->user;
        $user_id = $user->user_id;
        $username = $user->username;
        $bounceDate = $event->date;
        $bounceClass = $event->bounceClass;

        // SparkPost sends bounce_class as a string, and may add codes we do not know about yet
        $class = BounceClass::tryFrom((int) $bounceClass);

        if ($class === null)
        {
            return 'unknown';
        }

        // Subscribe is classified admin by SparkPost, exactly as admin_failure and
        // smart_send_suppression are, but it is not a delivery failure. Mapping the
        // classification straight through would stop email for a user who had just subscribed.
        if ($class === BounceClass::Subscribe)
        {
            return 'unknown';
        }

        $type = match ($class->classification())
        {
            BounceClassification::Hard => 'hard',
            BounceClassification::Soft => 'soft',
            BounceClassification::Block => 'block',
            // treat admin failures as hard bounces
            BounceClassification::Admin => 'hard',
            BounceClassification::Undetermined => null,
        };

        if ($type === null)
        {
            return 'unknown';
        }

        $this->info("Processing bounce", compact('user_id', 'username', 'type', 'bounceClass', 'bounceDate'));

        if ($type == 'block')
        {
            return $this->processBlock($event);
        }

        return $processor->takeBounceAction($user, $type, $bounceDate);
    }

    public function processBlock(ParsedMessage $event)
    {
        // nothing to do for now
        return 'block';
    }

    public function processUnsubscribe(ParsedMessage $event)
    {
        $user = $event->user;
        $user_id = $user->user_id;
        $username = $user->username;
        $bounceDate = $event->date;
        $bounceClass = $event->bounceClass;
        $campaign = $event->campaign;

        /** @var EmailStopService $emailStopper */
        $emailStopper = $this->app->service(EmailStopService::class, $user);

        $stopped = false;

        if ($event->transactional)
        {
            $this->info("Processing transactional unsubscribe", compact('user_id', 'username', 'campaign', 'bounceClass', 'bounceDate'));

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
            $this->info("Processing non-transactional unsubscribe", compact('user_id', 'username', 'campaign', 'bounceClass', 'bounceDate'));

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

    public function stopAllNonTransactional(EmailStopService $emailStopper)
    {
        // mailing lists are non-transactional - stop them
        $emailStopper->stopMailingList();

        // other addons which send their own non-transactional emails can over-ride this function to also stop their
        // own emails
    }

    public function logBounceMessage($action, ParsedMessage $event)
    {
        /** @var MessageEventRepository $repo */
        $repo = $this->app->repository(MessageEventRepository::class);

        return $repo->logBounceMessage(
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

}
