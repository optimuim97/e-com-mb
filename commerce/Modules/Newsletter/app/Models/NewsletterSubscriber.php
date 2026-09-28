<?php

namespace Modules\Newsletter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsletterSubscriber extends Model
{
    protected $fillable = [
        'email', 'name', 'status', 'unsubscribe_token', 'source', 'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return ['unsubscribed_at' => 'datetime'];
    }

    public const STATUS_SUBSCRIBED   = 'subscribed';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    protected static function booted(): void
    {
        static::creating(function (self $sub) {
            if (empty($sub->unsubscribe_token)) {
                $sub->unsubscribe_token = Str::random(48);
            }
        });
    }

    public function scopeSubscribed($query)
    {
        return $query->where('status', self::STATUS_SUBSCRIBED);
    }
}
