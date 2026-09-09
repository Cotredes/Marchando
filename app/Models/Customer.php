<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = ['restaurant_id', 'display_name', 'phone', 'phone_normalized', 'email', 'is_company', 'legal_name', 'tax_id', 'fiscal_address', 'postal_code', 'city', 'province', 'country', 'notes'];

    protected function casts(): array
    {
        return ['is_company' => 'boolean'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function saleDocuments(): HasMany
    {
        return $this->hasMany(SaleDocument::class);
    }

    public function fiscalName(): string
    {
        return $this->legal_name ?: ($this->display_name ?? 'Cliente');
    }
}
