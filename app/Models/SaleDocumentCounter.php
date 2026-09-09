<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleDocumentCounter extends Model
{
    protected $fillable = ['restaurant_id', 'kind', 'last_number'];

    protected function casts(): array
    {
        return ['last_number' => 'integer'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
