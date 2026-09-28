<?php

namespace Modules\Newsletter\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Newsletter\Mail\NewsletterCampaignMail;
use Modules\Newsletter\Models\NewsletterCampaign;
use Modules\Newsletter\Models\NewsletterSubscriber;

/**
 * Envoie une campagne à tous les abonnés actifs.
 * Sur la queue 'notifications' pour ne pas bloquer la requête HTTP.
 */
class SendCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?string $queue = 'notifications';
    public int $timeout = 3600;

    public function __construct(public readonly int $campaignId) {}

    public function handle(): void
    {
        $campaign = NewsletterCampaign::find($this->campaignId);
        if (! $campaign || $campaign->status === NewsletterCampaign::STATUS_SENT) {
            return;
        }

        $campaign->update(['status' => NewsletterCampaign::STATUS_SENDING]);

        $sent = 0;
        NewsletterSubscriber::subscribed()
            ->whereNotNull('email')
            ->chunkById(100, function ($subscribers) use ($campaign, &$sent) {
                foreach ($subscribers as $subscriber) {
                    try {
                        Mail::to($subscriber->email)
                            ->send(new NewsletterCampaignMail($campaign, $subscriber));
                        $sent++;
                    } catch (\Throwable $e) {
                        Log::warning("[Newsletter] Échec envoi à {$subscriber->email}: " . $e->getMessage());
                    }
                }
                $campaign->update(['sent_count' => $sent]);
            });

        $campaign->update([
            'status'   => NewsletterCampaign::STATUS_SENT,
            'sent_at'  => now(),
            'sent_count' => $sent,
        ]);

        Log::info("[Newsletter] Campagne {$campaign->id} envoyée à {$sent} abonné(s).");
    }
}
