<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatHistory extends Model
{

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'chat_room_id',
        'sender_id',
        'chats',
        'file',
    ];
}
