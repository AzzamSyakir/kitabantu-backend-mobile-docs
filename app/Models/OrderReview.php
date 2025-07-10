<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OrderReview extends Model
{
    use HasFactory;

    protected $table = 'order_reviews';
    public $incrementing = false;
    protected $keyType = 'uuid';

    protected $fillable = [
        'id',
        'order_id',
        'rating_star',
        'rating_text',
        'review_file_url',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
