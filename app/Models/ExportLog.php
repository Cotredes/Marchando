<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportLog extends Model
{
    protected $fillable = ['restaurant_id', 'user_id', 'kind', 'period_from', 'period_to'];

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
