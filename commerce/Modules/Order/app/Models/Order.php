<?php

namespace Modules\Order\Models;

use App\Support\Auditing\TracksOperator;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use Modules\Order\Support\PaymentAvailability;
use Modules\Payment\Models\Payment;

class Order extends Model
{
    use HasFactory, SoftDeletes, TracksOperator;

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }

    protected $fillable = [
        'number', 'user_id', 'status',
        'subtotal', 'shipping_cost', 'discount_amount', 'tax_amount', 'total', 'currency',
        'shipping_address', 'billing_address',
        'shipping_method', 'payment_method', 'tracking_number',
        'coupon_code', 'customer_note', 'admin_note',
        'metadata', 'destination',
        'paid_at', 'shipped_at', 'delivered_at', 'exported_at', 'archived_at',
        'payment_reminder_1_sent_at', 'payment_reminder_3_sent_at',
    ];

    /**
     * La destination suit toujours l'adresse de livraison.
     *
     * Posée au niveau du modèle et non dans un service : la boutique, la
     * commande rapide et le comptoir créent tous des commandes par des chemins
     * différents, et un agent peut encore corriger une adresse depuis
     * l'administration. Un seul point garantit qu'aucun de ces cas ne produit
     * une commande absente de l'écran des expéditions.
     */
    protected static function booted(): void
    {
        static::saving(function (self $order) {
            if ($order->isDirty('shipping_address') || $order->destination === null) {
                $order->destination = PaymentAvailability::destination($order->shipping_address);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'shipping_address' => 'array',
            'billing_address'  => 'array',
            'metadata'         => 'array',
            'subtotal'         => 'decimal:2',
            'shipping_cost'    => 'decimal:2',
            'discount_amount'  => 'decimal:2',
            'tax_amount'       => 'decimal:2',
            'total'            => 'decimal:2',
            'paid_at'          => 'datetime',
            'shipped_at'       => 'datetime',
            'delivered_at'     => 'datetime',
            'exported_at'      => 'datetime',
            'archived_at'      => 'datetime',
            'payment_reminder_1_sent_at' => 'datetime',
            'payment_reminder_3_sent_at' => 'datetime',
        ];
    }

    const STATUSES = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];

    // ── Moyens de paiement acceptés, par contexte (source unique de vérité). ──
    // ⚠ Incohérence connue à normaliser (décision produit + migration + front) :
    // « paiement à la livraison » vaut 'cod' au checkout mais 'delivery' en
    // commande rapide (mappé), et le POS ajoute 'cash'/'cinetpay'.
    const PAYMENT_METHODS_CHECKOUT = ['wave', 'orange_money', 'mtn', 'card', 'stripe', 'cod'];
    const PAYMENT_METHODS_QUICK    = ['wave', 'orange_money', 'delivery', 'cod'];
    // 'cinetpay' conservé pour les commandes historiques (avant la bascule Stripe).
    const PAYMENT_METHODS_POS      = ['cash', 'wave', 'orange_money', 'card', 'stripe', 'cinetpay', 'cod'];

    public function user()     { return $this->belongsTo(User::class); }
    public function items()    { return $this->hasMany(OrderItem::class); }
    public function payments() { return $this->hasMany(Payment::class); }

    public static function generateNumber(): string
    {
        $year  = now()->year;
        $count = static::whereYear('created_at', $year)->count() + 1;
        return 'ORD-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

    public static function generateTrackingNumber(): string
    {
        $year = now()->year;
        do {
            $rand     = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
            $tracking = 'RB-' . $year . '-' . $rand;
        } while (static::where('tracking_number', $tracking)->exists());

        return $tracking;
    }
}
