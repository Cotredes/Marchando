<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalRecord extends Model
{
    protected $fillable = ['restaurant_id', 'fiscal_identity_id', 'sale_document_id', 'environment', 'record_type', 'fiscal_type', 'serie', 'numero', 'issue_date', 'total_minor', 'tax_total_minor', 'previous_hash', 'hash', 'qr_content', 'software_version', 'status', 'aeat_response', 'attempts', 'sent_at'];

    protected function casts(): array
    {
        return ['total_minor' => 'integer', 'tax_total_minor' => 'integer', 'attempts' => 'integer', 'aeat_response' => 'array', 'issue_date' => 'date', 'sent_at' => 'immutable_datetime'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function identity(): BelongsTo
    {
        return $this->belongsTo(FiscalIdentity::class, 'fiscal_identity_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(SaleDocument::class, 'sale_document_id');
    }

    public function tries(): HasMany
    {
        return $this->hasMany(FiscalAttempt::class);
    }
}
