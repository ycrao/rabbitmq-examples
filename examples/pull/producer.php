<?php

include(__DIR__.'/../config.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$queue = "pull";
$exchange = "exPull";
$exchangeType = "direct";
$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();

$channel->queue_declare($queue, false, true, false, false, false, ['x-max-priority' => ['I', 10]]);

$channel->exchange_declare($exchange, $exchangeType, false, true, false);

$channel->queue_bind($queue, $exchange);


$messages = [];
for ($i = 0; $i < 1000; $i ++) {
    $messages[] = [
        "id" => uniqid(),
        "text" => "Hello world #".$i." from pull mode",
    ];
}

foreach ($messages as $msg) {
    $msgBody = json_encode($msg);
    $toSend = new AMQPMessage($msgBody, ['content_type' => 'text/plain', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
    $channel->basic_publish($toSend, $exchange);
    echo "send message: ".$msgBody.PHP_EOL;
    usleep(2000);
}

$channel->close();
$connection->close();