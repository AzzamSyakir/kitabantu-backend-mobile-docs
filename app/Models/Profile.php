<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
  public $incrementing = false;
  protected $keyType = 'string';

  protected $fillable = [
    'id',
    'user_id',
    'full_name',
    'phone_number',
    'nick_name',
    'gender',
    'domicile',
    'preferred_service',
    'picture_url',
  ];

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }
}
