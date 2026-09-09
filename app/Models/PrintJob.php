<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintJob extends Model
{
    public const KINDS = ['kitchen_ticket', 'kitchen_void', 'customer_ticket', 'invoice_doc', 'cash_report', 'test', 'drawer_kick'];

    protected $fillable = ['restaurant_id', 'printer_id', 'print_connector_id', 'order_id', 'sale_document_id', 'kind', 'reference', 'payload', 'status', 'attempts', 'claimed_at', 'printed_at', 'error', 'is_reprint', 'created_by_user_id', 'created_by_employee_id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'attempts' => 'integer', 'is_reprint' => 'boolean', 'claimed_at' => 'immutable_datetime', 'printed_at' => 'immutable_datetime'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(PrintConnector::class, 'print_connector_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
