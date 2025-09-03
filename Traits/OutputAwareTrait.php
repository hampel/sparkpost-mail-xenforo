<?php namespace Hampel\SparkPostMail\Traits;

use Symfony\Component\Console\Output\OutputInterface;

trait OutputAwareTrait
{
    /**
     * The output interface
     * @var OutputInterface|null
     */
    protected ?OutputInterface $output = null;

    /**
     * Sets the output interface
     *
     * @param OutputInterface $output
     * @return void
     */
    public function setOutput(OutputInterface $output) : void
    {
        $this->output = $output;
    }
}
