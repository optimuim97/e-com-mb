<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Order\Models\Order;

/**
 * Un message reçu sur le WhatsApp Business de la boutique.
 *
 * Trois états, et pas un de plus : à traiter, converti en commande, écarté.
 * Toute la valeur de la file tient dans le fait qu'un message ne peut pas
 * rester dans un entre-deux silencieux.
 */
class WhatsAppMessage extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_IGNORED   = 'ignored';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_CONVERTED, self::STATUS_IGNORED];

    // Sans cela Laravel déduirait `whats_app_messages` du CamelCase.
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'wa_message_id', 'from_phone', 'contact_name', 'type', 'body',
        'status', 'order_id', 'handled_by', 'handling_note',
        'received_at', 'handled_at', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload'     => 'array',
            'received_at' => 'datetime',
            'handled_at'  => 'datetime',
        ];
    }

    public function order(): BelongsTo   { return $this->belongsTo(Order::class); }
    public function handler(): BelongsTo { return $this->belongsTo(User::class, 'handled_by'); }

    /**
     * Cliente déjà connue, reconnue au numéro.
     *
     * WhatsApp donne le numéro au format international sans « + »
     * (2250707849883), la base le stocke le plus souvent tel que la cliente
     * l'a saisi (0707849883). On compare donc sur les 8 derniers chiffres,
     * qui identifient l'abonné en Côte d'Ivoire quel que soit le formatage.
     */
    public function customer(): ?User
    {
        $local = substr(preg_replace('/\D+/', '', $this->from_phone), -8);

        if (strlen($local) < 8) {
            return null;
        }

        return User::whereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$local}"])
            ->orderBy('id')
            ->first();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
