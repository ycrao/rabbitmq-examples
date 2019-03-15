<?php

include(__DIR__.'/../config.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$queue = "simple";
$exchange = "exSimple";
$exchangeType = "direct";
$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();

$channel->queue_declare($queue, false, true, false, false);
$channel->exchange_declare($exchange, $exchangeType, false, true, false);

$channel->queue_bind($queue, $exchange);

for ($i = 0; $i < 100; $i ++) {
    $messageBody = "Hello world #".$i." from simple mode";
    $message = new AMQPMessage($messageBody, ['content_type' => 'text/plain', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
    $channel->basic_publish($message, $exchange);
    echo "send message: ".$messageBody.PHP_EOL;
}

$channel->close();
$connection->close();