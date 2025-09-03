<?php namespace Hampel\SparkPostMail\Traits;

use Hampel\SparkPostMail\SubContainer\SparkPost;

trait ApiAwareTrait
{
    /**
     * SparkPost API subcontainer
     *
     * @var SparkPost|null
     */
    protected ?SparkPost $api = null;

    /**
     * Set up our API subcontainer
     *
     * @param SparkPost $api
     * @return void
     */
    public function setApi(SparkPost $api)
    {
        $this->api = $api;
    }
}
