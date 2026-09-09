<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KitchenEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['restaurant_id', 'kitchen_station_id', 'entity_type', 'entity_id', 'type', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
