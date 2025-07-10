<?php

namespace App\Jobs;

use App\Helpers\RabbitMqHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishMessageJob implements ShouldQueue
{
  use InteractsWithQueue, Queueable, SerializesModels;

  protected string $queueName;
  protected string $message;

  public function __construct(string $queueName, array|string $message)
  {
    $this->queueName = $queueName;
    $this->message = is_array($message) ? json_encode($message) : $message;
  }

  public function handle(): void
  {
    $rabbitMq = new RabbitMqHelper();
    $rabbitMq->publishMessage($this->queueName, $this->message);
    $rabbitMq->close();
  }
}
