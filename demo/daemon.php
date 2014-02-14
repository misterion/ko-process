<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

$manager = new Ko\ProcessManager();
$manager->setProcessTitle('Master:working...');

$fork = $manager->spawn(function(Ko\Process $p) {
    $connection = new AMQPConnection('localhost', 5672, 'guest', 'guest');
    $channel = $connection->channel();

    $channel->queue_declare('hello', false, true, false, false);

    $callback = function($msg) use (&$p) {
        $p->setProcessTitle('Worker:processJob ' . $msg->body);

        //will execute our job in separate process
        $m = new Ko\ProcessManager();
        $m->fork(function(Ko\Process $jobProcess) use ($msg) {
            $jobProcess->setProcessTitle('Job:processing ' . $msg->body);

            echo " [x] Received ", $msg->body, "\n";
            sleep(2);
            echo " [x] Done", "\n";
        })->onSuccess(function() use ($msg){
            //Ack on success
            $msg->delivery_info['channel']
                ->basic_ack($msg->delivery_info['delivery_tag']);
        })->wait();

        $p->setProcessTitle('Worker:waiting for job... ');

        pcntl_signal_dispatch();
        if ($p->isShouldShutdown()) {
            exit();
        }
    };

    $channel->basic_qos(null, 1, null);
    $channel->basic_consume('hello', '', false, false, false, false, $callback);

    while(count($channel->callbacks)) {
        $channel->wait();
    }

    $channel->close();
    $connection->close();
});