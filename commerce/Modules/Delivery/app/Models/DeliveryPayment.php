<?php

namespace Modules\Delivery\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Delivery\Enums\PaymentMethod;
use Modules\Delivery\Enums\MobileMoneyOperator;

class DeliveryPayment extends Model
{
    protected $table = 'delivery_payments';

    protected $fillable = [
        'delivery_id',
        'courier_id',
        'payment_method',
        'mobile_operator',
        'transaction_reference',
        'amount_expected',
        'amount_received',
        'change_amount',
        'paid_at',
    ];

    protected $casts = [
        'payment_method' => PaymentMethod::class,
        'mobile_operator' => MobileMoneyOperator::class,
        'amount_expected' => 'integer',
        'amount_received' => 'integer',
        'change_amount' => 'integer',
        'paid_at' => 'datetime',
    ];

    // Relationships
    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }

    // Accessors
    public function getAmountExpectedFloatAttribute(): float
    {
        return $this->amount_expected / 100;
    }

    public function getAmountReceivedFloatAttribute(): float
    {
        return $this->amount_received / 100;
    }

    public function getChangeAmountFloatAttribute(): float
    {
        return $this->change_amount / 100;
    }
}
