<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'payment_type',
        'payment_method',
        'amount',
        'date',
        'status',
        'freelancer_id',
        'client_id',
        'external_id',
        'raw_response',
        'failure_reason',
    ];
}
