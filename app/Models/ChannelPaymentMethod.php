<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelPaymentMethod extends Model
{
    public const CHANNELS = ['dine_in', 'takeaway', 'delivery'];

    public const METHODS = ['cash', 'card', 'online'];

    protected $fillable = ['restaurant_id', 'channel', 'method', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
