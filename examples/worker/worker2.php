<?php

include(__DIR__.'/../config.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;

$queue = "worker";
$exchange = "exWorker";
$exchangeType = "direct";
$consumerTag = "workerConsumer";

$connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
$channel = $connection->channel();

$channel->queue_declare($queue, false, true, false, false);
$channel->basic_qos(null, 1, null);

$channel->exchange_declare($exchange, $exchangeType, false, true, false);

$channel->queue_bind($queue, $exchange);


/**
 * @param \PhpAmqpLib\Message\AMQPMessage $message
 */
function process_message($message)
{
    echo "worker2 receive message: ".PHP_EOL;
    echo $message->body.PHP_EOL;
    echo "------".PHP_EOL;
    $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
    sleep(2);
}

$channel->basic_consume($queue, $consumerTag, false, false, false, false, 'process_message');

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
