<?php

namespace Modules\Newsletter\Models;

use App\Support\Auditing\TracksOperator;
use Illuminate\Database\Eloquent\Model;

class NewsletterCampaign extends Model
{
    use TracksOperator;

    protected $fillable = [
        'subject', 'preview_text', 'content', 'status',
        'recipients_count', 'sent_count', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public const STATUS_DRAFT   = 'draft';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT    = 'sent';
}
