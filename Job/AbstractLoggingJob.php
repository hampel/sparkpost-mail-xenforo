<?php namespace Hampel\SparkPostMail\Job;

use Hampel\SparkPostMail\Traits\ApiAwareTrait;
use Hampel\SparkPostMail\Traits\LogTrait;
use XF\App;
use XF\Job\AbstractJob;

abstract class AbstractLoggingJob extends AbstractJob
{
    use LogTrait;
    use ApiAwareTrait;

    public function __construct(App $app, $jobId, array $data = [], int $previousAttempts = 0)
    {
        parent::__construct($app, $jobId, $data);

        $this->setLogger($app->container('sparkpostmail.log'));
        $this->setContext(['jobId' => $jobId, 'job' => get_class($this)]);
        $this->setApi($app->container('sparkpostmail.api'));
    }
}
