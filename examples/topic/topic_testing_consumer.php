<?php

include(__DIR__.'/../config.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;

$queue1 = "topic_1";
$exchange = "exTopic";
$exchangeType = "topic";
$routingKey1 = "testing.*";
$consumerTag = "topicConsumer";

$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();


$channel->queue_declare($queue1, false, true, false, false);

$channel->exchange_declare($exchange, $exchangeType, false, true, false);

$channel->queue_bind($queue1, $exchange, $routingKey1);



/**
 * @param \PhpAmqpLib\Message\AMQPMessage $message
 */
function process_message($message)
{
    $routeKey = $message->delivery_info['routing_key'];
    echo "receive message using routing key - ".$routeKey.": ".PHP_EOL;
    echo $message->body.PHP_EOL;
    echo "------".PHP_EOL;
    $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
    sleep(1);
}

$channel->basic_consume($queue1, $consumerTag, false, false, false, false, 'process_message');

/**
 * @param \PhpAmqpLib\Channel\AMQPChannel $channel
 * @param \PhpAmqpLib\Connection\AbstractConnection $connection
 */
function shutdown($channel, $connection)
{
    $channel->close();
    $connection->close();
}

register_shutdown_function('shutdown', $channel, $connection);

// Loop as long as the channel has callbacks registered
while (count($channel->callbacks)) {
    $channel->wait();
}
