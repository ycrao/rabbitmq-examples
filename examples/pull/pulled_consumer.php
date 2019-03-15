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

while (true) {
    $records = [];
    $timeStart = microtime(true);
    $wait = 30;  // waiting for 30 seconds
    $timeSpent = 0;
    while (($timeSpent <= $wait) && (count($records) <= 100)) {
        // consumer pull message by basic_get()
        $response = $channel->basic_get($queue);
        $timeSpent = microtime(true) - $timeStart;
        if (is_null($response)) {
            break;
        }
        $records[] = json_decode($response->body, true);
        $tag = $response->delivery_info['delivery_tag'];
    }
    
    if (count($records)) {
        echo "under pull mode we get records:".PHP_EOL;
        echo "--------------------".PHP_EOL;
        var_dump($records);
        echo "--------------------".PHP_EOL;
        $channel->basic_ack($tag, true);
    } else {
        sleep(10);
    }
}

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