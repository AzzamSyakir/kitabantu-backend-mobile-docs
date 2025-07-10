<?php

namespace App\Helpers;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMqHelper
{
  protected $connection;
  protected $channel;

  public function __construct()
  {
    $this->OpenConnection();
  }

  protected function OpenConnection()
  {
    $this->connection = new AMQPStreamConnection(
      env('RABBITMQ_HOST', '127.0.0.1'),
      env('RABBITMQ_PORT', 5672),
      env('RABBITMQ_USER', 'guest'),
      env('RABBITMQ_PASSWORD', 'guest')
    );

    $this->channel = $this->connection->channel();
  }

  public function Close()
  {
    if ($this->channel) {
      $this->channel->Close();
    }

    if ($this->connection) {
      $this->connection->Close();
    }
  }

  protected function GetQueueList(): array
  {
    $raw = env('RABBITMQ_QUEUE_NAMES', '');
    $items = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    return array_filter(array_map('trim', $items));
  }

  public function GetQueueByIndex(int $index): ?string
  {
    $queues = $this->GetQueueList();
    return $queues[$index] ?? null;
  }

  public function CreateQueue(string $queueName)
  {
    $this->channel->queue_declare($queueName, false, true, false, false);
  }

  public function PublishMessage(string $queueName, string $message)
  {
    $this->CreateQueue($queueName);
    $msg = new AMQPMessage($message, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
    $this->channel->basic_publish($msg, '', $queueName);
  }
}
