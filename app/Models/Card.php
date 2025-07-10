<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Card extends Model
{
  use HasFactory;
  public $incrementing = false;
  protected $keyType = 'string';

  protected $fillable = [
    'id',
    'card_number',
    'card_holder_name',
    'user_id',
    'card_address',
  ];
}