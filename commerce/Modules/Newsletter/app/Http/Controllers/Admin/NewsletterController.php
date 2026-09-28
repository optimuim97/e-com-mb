<?php

namespace Modules\Newsletter\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Newsletter\Jobs\SendCampaignJob;
use Modules\Newsletter\Mail\NewsletterCampaignMail;
use Modules\Newsletter\Models\NewsletterCampaign;
use Modules\Newsletter\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Mail;

class NewsletterController extends Controller implements \Illuminate\Routing\Controllers\HasMiddleware
{
    public static function middleware(): array
    {
        return \App\Support\ResourcePermissions::build([
            'newsletter.view' => ['subscribers', 'campaigns'],
            'newsletter.edit' => [
                'storeSubscriber', 'destroySubscriber',
                'storeCampaign', 'updateCampaign', 'destroyCampaign', 'test', 'send',
            ],
        ]);
    }

    // ── Abonnés ───────────────────────────────────────────────────────────────

    public function subscribers(Request $request): JsonResponse
    {
        $query = NewsletterSubscriber::query()
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), fn($q) => $q->where('email', 'like', '%'.$request->search.'%'))
            ->latest();

        return response()->json([
            'data'  => $query->paginate((int) $request->get('per_page', 30)),
            'stats' => [
                'subscribed'   => NewsletterSubscriber::subscribed()->count(),
                'unsubscribed' => NewsletterSubscriber::where('status', 'unsubscribed')->count(),
                'total'        => NewsletterSubscriber::count(),
            ],
        ]);
    }

    /** Ajout manuel d'un abonné depuis l'admin. */
    public function storeSubscriber(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email|max:180',
            'name'  => 'nullable|string|max:120',
        ]);

        $sub = NewsletterSubscriber::firstOrNew(['email' => strtolower(trim($data['email']))]);
        $sub->fill([
            'name'   => $data['name'] ?? $sub->name,
            'status' => NewsletterSubscriber::STATUS_SUBSCRIBED,
            'source' => $sub->exists ? $sub->source : 'admin',
            'unsubscribed_at' => null,
        ])->save();

        return response()->json(['message' => 'Abonné ajouté.'], 201);
    }

    public function destroySubscriber(NewsletterSubscriber $subscriber): JsonResponse
    {
        $subscriber->delete();
        return response()->json(['message' => 'Abonné supprimé.']);
    }

    // ── Campagnes ─────────────────────────────────────────────────────────────

    public function campaigns(): JsonResponse
    {
        return response()->json(
            NewsletterCampaign::latest()->paginate(20)
        );
    }

    public function storeCampaign(Request $request): JsonResponse
    {
        $data = $this->validateCampaign($request);
        $campaign = NewsletterCampaign::create($data + ['status' => 'draft']);

        return response()->json($campaign, 201);
    }

    public function updateCampaign(Request $request, NewsletterCampaign $campaign): JsonResponse
    {
        if ($campaign->status === NewsletterCampaign::STATUS_SENT) {
            return response()->json(['message' => 'Une campagne envoyée ne peut plus être modifiée.'], 422);
        }
        $campaign->update($this->validateCampaign($request));
        return response()->json($campaign);
    }

    public function destroyCampaign(NewsletterCampaign $campaign): JsonResponse
    {
        $campaign->delete();
        return response()->json(['message' => 'Campagne supprimée.']);
    }

    /** Envoie un email de test à une adresse donnée. */
    public function test(Request $request, NewsletterCampaign $campaign): JsonResponse
    {
        $data = $request->validate(['email' => 'required|email']);

        $fake = new NewsletterSubscriber([
            'email' => $data['email'],
            'name'  => 'Test',
            'unsubscribe_token' => 'test-token',
        ]);

        Mail::to($data['email'])->send(new NewsletterCampaignMail($campaign, $fake));

        return response()->json(['message' => "Email de test envoyé à {$data['email']}."]);
    }

    /** Lance l'envoi de la campagne à tous les abonnés. */
    public function send(NewsletterCampaign $campaign): JsonResponse
    {
        if ($campaign->status === NewsletterCampaign::STATUS_SENT) {
            return response()->json(['message' => 'Campagne déjà envoyée.'], 422);
        }

        $recipients = NewsletterSubscriber::subscribed()->count();
        if ($recipients === 0) {
            return response()->json(['message' => 'Aucun abonné actif.'], 422);
        }

        $campaign->update(['recipients_count' => $recipients, 'status' => NewsletterCampaign::STATUS_SENDING]);
        SendCampaignJob::dispatch($campaign->id);

        return response()->json([
            'message' => "Envoi lancé vers {$recipients} abonné(s).",
            'recipients' => $recipients,
        ]);
    }

    private function validateCampaign(Request $request): array
    {
        return $request->validate([
            'subject'      => 'required|string|max:200',
            'preview_text' => 'nullable|string|max:200',
            'content'      => 'required|string',
        ]);
    }
}
