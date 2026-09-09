<?php

namespace App\Models;

use Database\Factories\RestaurantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Restaurant extends Model
{
    /** @use HasFactory<RestaurantFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'establishment_type',
        'description',
        'contact_email',
        'phone',
        'secondary_phone',
        'website',
        'instagram',
        'facebook',
        'tiktok',
        'address',
        'postal_code',
        'city',
        'province',
        'country',
        'latitude',
        'longitude',
        'legal_name',
        'tax_id',
        'default_vat',
        'ticket_footer',
        'timezone',
        'dine_in_enabled',
        'takeaway_enabled',
        'takeaway_prep_minutes',
        'takeaway_use_general_schedule',
        'delivery_enabled',
        'delivery_radius_km',
        'delivery_fee',
        'delivery_minimum_order',
        'delivery_prep_minutes',
        'delivery_use_general_schedule',
        'currency',
        'public_menu_enabled',
        'qr_ordering_enabled',
        'qr_acceptance_mode',
        'takeaway_acceptance_mode',
        'delivery_acceptance_mode',
        'takeaway_scheduling_enabled',
        'delivery_scheduling_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'default_vat' => 'decimal:2',
            'delivery_radius_km' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'delivery_minimum_order' => 'decimal:2',
            'dine_in_enabled' => 'boolean',
            'takeaway_enabled' => 'boolean',
            'takeaway_use_general_schedule' => 'boolean',
            'delivery_enabled' => 'boolean',
            'delivery_use_general_schedule' => 'boolean',
            'public_menu_enabled' => 'boolean',
            'qr_ordering_enabled' => 'boolean',
            'takeaway_scheduling_enabled' => 'boolean',
            'delivery_scheduling_enabled' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'memberships')
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function openingHours(): HasMany
    {
        return $this->hasMany(OpeningHour::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function productFormats(): HasMany
    {
        return $this->hasMany(ProductFormat::class);
    }

    public function modifierGroups(): HasMany
    {
        return $this->hasMany(ModifierGroup::class);
    }

    public function modifierOptions(): HasMany
    {
        return $this->hasMany(ModifierOption::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class)->orderBy('position')->orderBy('id');
    }

    public function diningTables(): HasMany
    {
        return $this->hasMany(DiningTable::class)->orderBy('position')->orderBy('id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function operationalRoles(): HasMany
    {
        return $this->hasMany(OperationalRole::class)->orderBy('name');
    }

    public function workIntervals(): HasMany
    {
        return $this->hasMany(WorkInterval::class);
    }

    public function intervals(): HasMany
    {
        return $this->workIntervals();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function activeTableOrders(): HasMany
    {
        return $this->hasMany(ActiveTableOrder::class);
    }

    public function splitPlans(): HasMany
    {
        return $this->hasMany(OrderSplitPlan::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class)->orderBy('position')->orderBy('id');
    }

    public function cashRegisters(): HasMany
    {
        return $this->hasMany(CashRegister::class)->orderBy('name');
    }

    public function cashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function publicOrderRequests(): HasMany
    {
        return $this->hasMany(PublicOrderRequest::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function saleDocuments(): HasMany
    {
        return $this->hasMany(SaleDocument::class);
    }

    public function printers(): HasMany
    {
        return $this->hasMany(Printer::class)->orderBy('name');
    }

    public function printConnectors(): HasMany
    {
        return $this->hasMany(PrintConnector::class)->orderBy('name');
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(Integration::class);
    }

    public function onlineIntents(): HasMany
    {
        return $this->hasMany(OnlinePaymentIntent::class);
    }

    public function fiscalIdentities(): HasMany
    {
        return $this->hasMany(FiscalIdentity::class);
    }

    public function fiscalRecords(): HasMany
    {
        return $this->hasMany(FiscalRecord::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class)->orderBy('code');
    }

    public function loyaltyPrograms(): HasMany
    {
        return $this->hasMany(LoyaltyProgram::class);
    }

    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(OutboundWebhookEndpoint::class);
    }

    public function accountingMapping(): HasOne
    {
        return $this->hasOne(AccountingMapping::class);
    }

    public function channelPaymentMethods(): HasMany
    {
        return $this->hasMany(ChannelPaymentMethod::class);
    }

    public function plans(): HasMany
    {
        return $this->splitPlans();
    }

    public function kitchenStations(): HasMany
    {
        return $this->hasMany(KitchenStation::class)->orderBy('position')->orderBy('id');
    }

    public function stations(): HasMany
    {
        return $this->kitchenStations();
    }

    public function kitchenDispatches(): HasMany
    {
        return $this->hasMany(KitchenDispatch::class);
    }

    public function kitchenEvents(): HasMany
    {
        return $this->hasMany(KitchenEvent::class);
    }

    public function kitchenItems(): HasMany
    {
        return $this->hasMany(KitchenItem::class);
    }

    public function items(): HasMany
    {
        return $this->kitchenItems();
    }

    public function cancellations(): HasMany
    {
        return $this->hasMany(KitchenCancellation::class);
    }
}
