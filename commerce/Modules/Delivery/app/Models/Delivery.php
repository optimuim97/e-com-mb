<?php

namespace Modules\Delivery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Delivery\Enums\DeliveryStatus;
use Modules\Delivery\Enums\DeliveryType;
use Modules\Delivery\Enums\PaymentFlow;


class Delivery extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_number',
        'customer_id',
        'courier_id',
        'status',
        'delivery_type',
        'payment_flow',
        'product_amount',
        'delivery_fee',
        'total_amount',
        'qr_code',
        'qr_code_hash',
        'qr_code_expires_at',
        'signature_base64',
        'confirmed_at',
        'delivery_lat',
        'delivery_lng',
        'assigned_at',
        'delivered_at',
        'notes',
        'failure_reason',
    ];

    protected $casts = [
        'status' => DeliveryStatus::class,
        'delivery_type' => DeliveryType::class,
        'payment_flow' => PaymentFlow::class,
        'confirmed_at' => 'datetime',
        'assigned_at' => 'datetime',
        'delivered_at' => 'datetime',
        'qr_code_expires_at' => 'datetime',
        'product_amount' => 'integer',
        'delivery_fee' => 'integer',
        'total_amount' => 'integer',
        'delivery_lat' => 'decimal:8',
        'delivery_lng' => 'decimal:8',
    ];

    // Relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }

    public function payments()
    {
        return $this->hasMany(DeliveryPayment::class);
    }

    public function assignments()
    {
        return $this->hasMany(DeliveryAssignment::class);
    }

    // Scopes
    public function scopeToday($query)
    {
        return $query->whereDate('assigned_at', today());
    }

    public function scopePending($query)
    {
        return $query->where('status', DeliveryStatus::PENDING);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', DeliveryStatus::IN_PROGRESS);
    }

    public function scopeForCourier($query, int $courierId)
    {
        return $query->where('courier_id', $courierId);
    }

    // Helpers
    public function isCod(): bool
    {
        return $this->payment_flow === PaymentFlow::COD;
    }

    public function isPrepaid(): bool
    {
        return $this->payment_flow === PaymentFlow::PREPAID;
    }

    public function canBeConfirmed(): bool
    {
        return in_array($this->status, [
            DeliveryStatus::PENDING,
            DeliveryStatus::IN_PROGRESS,
        ]);
    }
}
