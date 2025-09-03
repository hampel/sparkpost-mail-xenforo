<?php namespace Hampel\SparkPostMail\Traits;

trait ContextAwareTrait
{
    /**
     * Any context to be added to logs.
     *
     * @var array
     */
    protected $context = [];

    /**
     * Add context to all future logs.
     *
     * @param  array  $context
     * @return $this
     */
    public function setContext(array $context = [])
    {
        $this->context = array_merge($this->context, $context);
    }
}
