<?php

namespace Modules\Newsletter\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Admin\Models\Setting;
use Modules\Newsletter\Models\NewsletterCampaign;
use Modules\Newsletter\Models\NewsletterSubscriber;
use App\Support\Shop;

class NewsletterCampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly NewsletterCampaign $campaign,
        public readonly NewsletterSubscriber $subscriber,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->campaign->subject);
    }

    public function content(): Content
    {
        $unsubscribeUrl = rtrim(config('app.url'), '/')
            . '/api/newsletter/unsubscribe/' . $this->subscriber->unsubscribe_token;

        return new Content(
            view: 'newsletter::campaign',
            with: [
                'subject'        => $this->campaign->subject,
                'previewText'    => $this->campaign->preview_text,
                'bodyHtml'       => $this->campaign->content,
                'shopName'       => Shop::name(),
                'shopLogo'       => Setting::get('shop_logo_url', ''),
                'unsubscribeUrl' => $unsubscribeUrl,
                'subscriberName' => $this->subscriber->name,
            ],
        );
    }
}
