<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Redis;
use Exception;

class SocketHelper
{
  protected string $roomPrefix = 'socket_room:';

  public function TriggerSendChatEvent(array $payload)
  {
    $requiredFields = ['room', 'user_id'];

    foreach ($requiredFields as $field) {
      if (empty($payload[$field])) {
        throw new \InvalidArgumentException("The '{$field}' field is required.");
      }
    }

    $finalPayload = [
      'event' => 'send_chat',
      'room' => $payload['room'],
      'chat' => $payload['chat'],
      'user_id' => $payload['user_id'],
    ];

    if (!empty($payload['file'])) {
      $finalPayload['file'] = $payload['file'];
    }

    $channel = env('REDIS_SOCKET_CHANNEL', 'socket-channels');

    Redis::connection('socket')->publish($channel, json_encode($finalPayload));
  }
}