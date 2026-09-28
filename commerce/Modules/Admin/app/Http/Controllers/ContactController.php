<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ResourcePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\Admin\Models\ContactInquiry;
use Modules\Admin\Models\Setting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Support\Shop;

class ContactController extends Controller implements HasMiddleware
{
    // store() = formulaire de contact public, hors périmètre permission.
    public static function middleware(): array
    {
        return ResourcePermissions::build([
            'contact.view'   => ['index', 'show', 'unreadCount'],
            'contact.edit'   => ['update'],
            'contact.delete' => ['destroy'],
        ]);
    }

    // ── Public: store a new inquiry ──────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'    => 'required|string|max:120',
            'email'   => 'required|email|max:200',
            'phone'   => 'nullable|string|max:30',
            'subject' => 'required|string|max:200',
            'message' => 'required|string|max:3000',
        ]);

        $inquiry = ContactInquiry::create($data);

        // Notify admin by email (non-blocking)
        try {
            $adminEmail = Setting::get('shop_email') ?? config('mail.from.address');
            if ($adminEmail) {
                Mail::send('emails.admin.contact', ['inquiry' => $inquiry], function ($m) use ($inquiry, $adminEmail) {
                    $m->to($adminEmail)
                      ->subject("📩 Nouveau message — {$inquiry->subject} | " . Shop::name());
                });
            }
        } catch (\Throwable $e) {
            Log::warning('[Contact] Email admin failed: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Votre message a bien été envoyé. Nous vous répondrons dans les plus brefs délais.',
        ], 201);
    }

    // ── Admin: list inquiries ────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $inquiries = ContactInquiry::query()
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20);

        return response()->json($inquiries);
    }

    // ── Admin: show + mark as read ───────────────────────────────────────────
    public function show(ContactInquiry $inquiry): JsonResponse
    {
        $inquiry->markAsRead();
        return response()->json($inquiry);
    }

    // ── Admin: update status ─────────────────────────────────────────────────
    public function update(Request $request, ContactInquiry $inquiry): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:new,read,replied',
        ]);

        $inquiry->update($data);
        return response()->json($inquiry);
    }

    // ── Admin: delete ────────────────────────────────────────────────────────
    public function destroy(ContactInquiry $inquiry): JsonResponse
    {
        $inquiry->delete();
        return response()->json(['message' => 'Supprimé.']);
    }

    // ── Admin: unread count (pour le badge) ──────────────────────────────────
    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'count' => ContactInquiry::where('status', 'new')->count(),
        ]);
    }
}
