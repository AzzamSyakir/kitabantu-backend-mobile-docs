<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class OrderComplaint extends Model
{
  use HasFactory;

  protected $table = 'order_complaints';

  protected $fillable = [
    'id',
    'order_id',
    'complaint_type',
    'description',
    'evidence_url',
    'contact_info',
  ];

  public $incrementing = false;
  protected $keyType = 'string';

  public function order()
  {
    return $this->belongsTo(Order::class);
  }
}
