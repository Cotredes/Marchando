<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicOrderRequestLine extends Model
{
    protected $fillable = ['public_order_request_id', 'product_id', 'product_format_id', 'product_name', 'format_name', 'quantity', 'unit_total_minor', 'line_total_minor', 'vat_rate', 'selections', 'snapshot', 'notes'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_total_minor' => 'integer', 'line_total_minor' => 'integer', 'selections' => 'array', 'snapshot' => 'array'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PublicOrderRequest::class, 'public_order_request_id');
    }
}
