<?php

namespace App\Models;

use Database\Factories\OpeningHourFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningHour extends Model
{
    /** @use HasFactory<OpeningHourFactory> */
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'context',
        'iso_weekday',
        'start_minute',
        'end_minute',
    ];

    protected function casts(): array
    {
        return [
            'iso_weekday' => 'integer',
            'start_minute' => 'integer',
            'end_minute' => 'integer',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
