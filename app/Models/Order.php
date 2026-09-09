<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = ['restaurant_id', 'dining_table_id', 'opened_by_user_id', 'opened_by_employee_id', 'current_employee_id', 'customer_id', 'channel', 'origin', 'status', 'payment_status', 'currency', 'guest_count', 'total_minor', 'version', 'business_date', 'opened_at', 'closed_at', 'paid_at'];

    protected function casts(): array
    {
        return ['guest_count' => 'integer', 'total_minor' => 'integer', 'version' => 'integer', 'business_date' => 'date', 'opened_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime', 'paid_at' => 'immutable_datetime'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    public function openedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function openedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'opened_by_employee_id');
    }

    public function currentEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'current_employee_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function saleDocuments(): HasMany
    {
        return $this->hasMany(SaleDocument::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(OrderRound::class)->orderBy('sequence');
    }

    public function draftRound(): HasOne
    {
        return $this->hasOne(OrderRound::class)->where('draft_slot', 1);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(ActiveTableOrder::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(OrderDiscount::class);
    }

    public function splitPlans(): HasMany
    {
        return $this->hasMany(OrderSplitPlan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function fulfillment(): HasOne
    {
        return $this->hasOne(OrderFulfillment::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(OrderCharge::class);
    }

    public function publicRequests(): HasMany
    {
        return $this->hasMany(PublicOrderRequest::class, 'accepted_order_id');
    }

    public function fulfillmentLabel(): string
    {
        return $this->channel === 'dine_in' ? ($this->table?->name ?? 'Mesa') : ($this->channel === 'takeaway' ? 'Take Away' : 'Delivery');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
