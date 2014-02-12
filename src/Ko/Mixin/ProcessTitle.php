<?php
namespace Ko\Mixin;

/**
 * Class ProcessTitle
 *
 * @package Ko\Mixin
 * @author Nikolay Bondarenko <misterionkell@gmail.com>
 * @version 1.0
 */
trait ProcessTitle
{
    /**
     * Sets the process title.
     *
     * @param string $title
     */
    public function setProcessTitle($title)
    {
        if(function_exists('cli_set_process_title')) {
            cli_set_process_title($title); //PHP >= 5.5.
        } else if(function_exists('setproctitle')) {
            setproctitle($title); //PECL proctitle
        }
    }

    /**
     * Returns the current process title.
     *
     * @return string
     */
    public function getProcessTitle()
    {
        if(function_exists('cli_get_process_title')) {
            return cli_get_process_title(); //PHP >= 5.5.
        } else {
            return exec('ps -p ' . getmypid() . ' -o command| tail -1', $out);
        }
    }
}