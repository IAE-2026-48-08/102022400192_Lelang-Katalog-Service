<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = [
        'name',
        'description',
        'starting_price',
        'current_highest_bid',
        'auction_status',
        'auction_deadline',
        'image_url',
    ];

    protected $casts = [
        'auction_deadline' => 'datetime',
        'starting_price' => 'integer',
        'current_highest_bid' => 'integer',
    ];
}