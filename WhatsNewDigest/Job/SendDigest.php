<?php namespace Hampel\SparkPostMail\WhatsNewDigest\Job;

class SendDigest extends XFCP_SendDigest // extends Hampel\WhatsNewDigest\Job\SendDigest
{
    protected function getMail($user)
    {
        $mail = parent::getMail($user);

        if ($mail instanceof \Hampel\SparkPostMail\XF\Mail\Mail)
        {
            // if we're running SparkPost, set this email to non-transactional
            $mail->setTransactional(false);
        }

        return $mail;
    }
}
