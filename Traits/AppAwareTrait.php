<?php namespace Hampel\SparkPostMail\Traits;

use XF\App;

trait AppAwareTrait
{
    /**
     * XenForo app
     *
     * @var App|null
     */
    protected ?App $app = null;

    /**
     * Set up our app
     *
     * @param App $app
     * @return void
     */
    public function setApp(App $app)
    {
        $this->app = $app;
    }
}
