<?php

include(__DIR__.'/../config.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$queue1 = "routing_1";
$queue2 = "routing_2";
$exchange = "exRouting";
$exchangeType = "direct";
$routingKey1 = "testing";
$routingKey2 = "production";
// $consumerTag = "routingConsumer";

$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();

$channel->queue_declare($queue1, false, true, false, false, false);
$channel->queue_declare($queue2, false, true, false, false, false);

$channel->exchange_declare($exchange, $exchangeType, false, true, false);

$channel->queue_bind($queue1, $exchange, $routingKey1);
$channel->queue_bind($queue2, $exchange, $routingKey2);



for ($i = 0; $i < 100; $i ++) {
    $messageBody = "Hello world #".$i." from routing mode";
    $message = new AMQPMessage($messageBody, ['content_type' => 'text/plain', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
    // $channel->basic_publish($message, $exchange, $routingKey);
    if ($i%2) {
        $channel->basic_publish($message, $exchange, $routingKey1);
        echo "send message using routing key - ".$routingKey1.": ".$messageBody.PHP_EOL;
    } else {
        $channel->basic_publish($message, $exchange, $routingKey2);
        echo "send message using routing key - ".$routingKey2.": ".$messageBody.PHP_EOL;
    }
    // sleep(1);
}

$channel->close();
$connection->close();