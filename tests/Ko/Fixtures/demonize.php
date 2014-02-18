<?php
/**
 * @category Tests
 * @package Ko
 * @author Nikolay Bondarenko <misterionkell@gmail.com>
 * @version 1.0
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

$title = $argv[1];

$m = new \Ko\ProcessManager();
$m->setProcessTitle($title);
$m->demonize();

$m->setProcessTitle($title . '_afterDemonize');
sleep(3);