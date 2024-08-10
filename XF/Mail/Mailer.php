<?php namespace Hampel\SparkPostMail\XF\Mail;

use Hampel\SparkPostMail\Option\EmailTransport;
use XF\Mail\Mail;

class Mailer extends XFCP_Mailer
{
    public function applyMailDefaults(Mail $mail)
    {
        parent::applyMailDefaults($mail);

        // all emails are transactional unless explicitly set otherwise
        $mail->setTransactional(true);
        $mail->setClickTracking(EmailTransport::isClickTrackingEnabled());
        $mail->setOpenTracking(EmailTransport::isOpenTrackingEnabled());
    }
}
