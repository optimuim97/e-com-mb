<?php

namespace Modules\Delivery\Models;

use App\Support\Auditing\TracksOperator;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Delivery\Enums\CourierStatus;

class Courier extends Authenticatable
{
    use SoftDeletes, TracksOperator;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'status',
        'fcm_token',
        'biometric_enabled',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'fcm_token',
    ];

    protected $casts = [
        'biometric_enabled' => 'boolean',
        'last_login_at' => 'datetime',
        'status' => CourierStatus::class,
    ];

    // Relationships
    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }

    public function payments()
    {
        return $this->hasMany(DeliveryPayment::class);
    }

    public function assignments()
    {
        return $this->hasMany(DeliveryAssignment::class);
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
