<?php

namespace Modules\Newsletter\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Newsletter\Models\NewsletterSubscriber;

class SubscriptionController extends Controller
{
    /**
     * POST /newsletter/subscribe  (public)
     * Body : { email, name? }
     */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email|max:180',
            'name'  => 'nullable|string|max:120',
            'source'=> 'nullable|string|max:40',
        ]);

        $email = strtolower(trim($data['email']));

        $subscriber = NewsletterSubscriber::firstOrNew(['email' => $email]);
        // Réinscription d'un désabonné = réactivation
        $subscriber->fill([
            'name'   => $data['name'] ?? $subscriber->name,
            'source' => $subscriber->exists ? $subscriber->source : ($data['source'] ?? 'footer'),
            'status' => NewsletterSubscriber::STATUS_SUBSCRIBED,
            'unsubscribed_at' => null,
        ])->save();

        return response()->json([
            'message' => 'Merci ! Votre inscription est confirmée.',
        ], 201);
    }

    /**
     * GET /newsletter/unsubscribe/{token}  (public, lien email)
     * Renvoie une page HTML simple de confirmation.
     */
    public function unsubscribe(string $token)
    {
        $subscriber = NewsletterSubscriber::where('unsubscribe_token', $token)->first();

        if ($subscriber && $subscriber->status !== NewsletterSubscriber::STATUS_UNSUBSCRIBED) {
            $subscriber->update([
                'status' => NewsletterSubscriber::STATUS_UNSUBSCRIBED,
                'unsubscribed_at' => now(),
            ]);
        }

        $message = $subscriber
            ? 'Vous êtes désormais désabonné(e). Vous ne recevrez plus nos emails.'
            : 'Lien invalide ou déjà traité.';

        return response(
            '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<div style="font-family:sans-serif;max-width:480px;margin:80px auto;text-align:center;color:#3a2520;">'
            . '<h2 style="color:#a8324f;">🌹</h2><p style="font-size:16px;line-height:1.6;">' . e($message) . '</p>'
            . '<a href="' . rtrim(config('app.url'), '/') . '" style="color:#e8336d;">Retour à la boutique</a></div>',
            200
        )->header('Content-Type', 'text/html');
    }
}
