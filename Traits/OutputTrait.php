<?php namespace Hampel\SparkPostMail\Traits;

use Symfony\Component\Console\Output\OutputInterface;

trait OutputTrait
{
    protected $verbosityMap = [
        'debug' => OutputInterface::VERBOSITY_DEBUG,
        'info' => OutputInterface::VERBOSITY_VERBOSE,
        'notice' => OutputInterface::VERBOSITY_NORMAL,
        'warning' => OutputInterface::VERBOSITY_NORMAL,
        'error' => OutputInterface::VERBOSITY_QUIET,
        'critical' => OutputInterface::VERBOSITY_QUIET,
        'alert' => OutputInterface::VERBOSITY_QUIET,
        'emergency' => OutputInterface::VERBOSITY_QUIET,
    ];

    protected $styleMap = [
        'debug' => null,
        'info' => 'info',
        'notice' => 'comment',
        'warning' => 'comment',
        'error' => 'error',
        'critical' => 'error',
        'alert' => 'error',
        'emergency' => 'error',
    ];

    /**
     * Write a string as standard output.
     *
     * @param  string  $string
     * @param  string|null  $style
     * @param  int|string|null  $verbosity
     * @return void
     */
    abstract public function line($string, ?string $style = null, int $verbosity = 0) : void;

    abstract public function getDefaultVerbosity();

    protected function getVerbosity(string $level)
    {
        return $this->verbosityMap[$level] ?? $this->getDefaultVerbosity();
    }

    /**
     * @param string $level
     * @return mixed|null
     */
    protected function getStyle(string $level) : ?string
    {
        return $this->styleMap[$level] ?? null;
    }

    protected function styleString(string $string, string $style = null) : string
    {
        return $style ? "<$style>$string</$style>" : $string;
    }
}
