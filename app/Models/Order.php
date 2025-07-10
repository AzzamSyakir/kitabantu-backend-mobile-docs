<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'freelancer_id',
        'client_id',
        'work_location',
        'work_start_time',
        'work_end_time',
        'estimated_travel_time',
        'hourly_rate',
        'total_cost',
        'note',
        'status',
        'is_sent_to_server',
        'sent_to_server_at',
        'server_response_status',
    ];

    protected $casts = [
        'work_start_time' => 'datetime',
        'work_end_time' => 'datetime',
        'sent_to_server_at' => 'datetime',
        'is_sent_to_server' => 'boolean',
        'hourly_rate' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'working_hours_duration' => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(User::class);
    }

    public function freelancer()
    {
        return $this->belongsTo(User::class);
    }

    public function freelancerReceiver()
    {
        return $this->belongsTo(User::class);
    }

    public function clientReceiver()
    {
        return $this->belongsTo(User::class);
    }
}
