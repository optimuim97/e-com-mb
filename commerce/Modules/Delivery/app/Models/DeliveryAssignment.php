<?php

namespace Modules\Delivery\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DeliveryAssignment extends Model
{
    protected $fillable = [
        'delivery_id',
        'courier_id',
        'dispatcher_id',
        'status',
        'assigned_at',
        'accepted_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
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

    public function dispatcher()
    {
        return $this->belongsTo(User::class, 'dispatcher_id');
    }
}
