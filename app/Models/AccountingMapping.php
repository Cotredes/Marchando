<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingMapping extends Model
{
    protected $fillable = ['restaurant_id', 'sales_account', 'vat_account', 'customers_account', 'journal', 'notes'];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
