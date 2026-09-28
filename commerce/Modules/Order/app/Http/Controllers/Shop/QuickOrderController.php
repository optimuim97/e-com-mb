<?php

namespace Modules\Order\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Order\Data\AddressData;
use Modules\Order\Data\OrderData;
use Modules\Order\Http\Resources\OrderResource;
use Modules\Admin\Models\Setting;
use Modules\Order\Exceptions\DuplicateOrderException;
use Modules\Order\Rules\ValidPhone;
use Modules\Order\Support\DuplicateOrderCheck;
use Modules\Order\Services\OrderService;
use Modules\Order\Support\PaymentAvailability;
use Modules\Payment\Services\PaymentService;

class QuickOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly PaymentService $paymentService,
    ) {}

    /**
     * Vérifie l'admin setting `enable_quick_order` (défaut true).
     * Retourne 403 si désactivé.
     */
    private function ensureEnabled(): ?JsonResponse
    {
        $enabled = filter_var(Setting::get('enable_quick_order', true), FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return response()->json([
                'message' => 'La commande rapide est actuellement désactivée. Veuillez créer un compte pour finaliser votre achat.',
            ], 403);
        }
        return null;
    }

    /**
     * POST /quick-order  (sans authentification)
     *
     * Champs requis :
     *   name          — Prénom / Nom complet
     *   phone         — Numéro de téléphone (identifiant principal)
     *   commune       — Commune / Quartier de livraison
     *   payment_method — wave | orange_money | delivery
     *   items[]        — [{product_id, quantity, variant_id?}]
     *
     * Champs optionnels :
     *   note          — Note pour la livraison
     */
    public function store(Request $request): JsonResponse
    {
        if ($disabled = $this->ensureEnabled()) {
            return $disabled;
        }

        $validated = $request->validate([
            'name'           => 'required|string|max:120',
            // Le numéro sert à joindre la cliente : contrôlé, et strictement
            // en Côte d'Ivoire, où la règle est certaine. Le pays vient de la
            // même demande, d'où la lecture directe plutôt qu'une règle figée.
            'phone'          => ['required', 'string', 'max:30', new ValidPhone(
                strtoupper(trim((string) $request->input('country', 'CI'))) ?: 'CI',
            )],
            'email'          => 'nullable|email|max:160',
            'commune'        => 'required|string|max:100',
            'city'           => 'nullable|string|max:120',
            'country'        => 'nullable|string|size:2',
            'landmark'       => 'nullable|string|max:300',
            // Facultatif : hors de Côte d'Ivoire, aucun règlement en ligne n'est
            // proposé. La cliente ne peut donc rien choisir, et l'exiger
            // rendrait la commande impossible. Le contrôle réel est plus bas,
            // par PaymentAvailability, qui reste la source de vérité.
            'payment_method' => 'nullable|in:'.implode(',', \Modules\Order\Models\Order::PAYMENT_METHODS_QUICK),
            'note'           => 'nullable|string|max:500',
            // Code promo saisi dans la modale. Revalidé et rechiffré à la
            // création : ce que le front a affiché n'engage rien.
            'coupon_code'    => 'nullable|string|max:60',
            // Réponse de la cliente à qui l'on vient de montrer une commande
            // similaire encore en cours : « oui, celle-ci est nouvelle ».
            'confirm_duplicate' => 'nullable|boolean',
            'items'          => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.variant_id' => 'nullable|integer|exists:product_variants,id',
            'items.*.quantity'   => 'required|integer|min:1',
        ]);

        // Ville : fournie par le front (hors Abidjan), sinon Abidjan par défaut.
        $city = !empty($validated['city']) ? trim($validated['city']) : 'Abidjan';

        // ── Moyen de paiement autorisé pour cette destination ────────────────
        // Contrôlé avant toute écriture : sans ça, la commande rapide serait un
        // contournement de la règle appliquée au checkout.
        $pays    = strtoupper(trim((string) ($validated['country'] ?? 'CI'))) ?: 'CI';
        $adresse = ['country' => $pays, 'city' => $city, 'commune' => $validated['commune']];

        // Une destination sans règlement en ligne — l'international, ou une zone
        // non tarifée — ne doit pas exiger de moyen de paiement : c'est l'agent
        // qui le fixera après avoir arrêté les frais avec la cliente.
        $moyensPossibles = PaymentAvailability::allowed($adresse);

        if ($moyensPossibles === []) {
            $validated['payment_method'] = null;
        } elseif (! PaymentAvailability::accepts($adresse, $validated['payment_method'] ?? null)) {
            throw ValidationException::withMessages([
                'payment_method' => PaymentAvailability::refusalMessage($adresse),
            ]);
        }

        // ── Trouver ou créer l'utilisateur par téléphone ─────────────────────
        $user = User::where('phone', $validated['phone'])->first();

        if (! $user) {
            // Génère un email placeholder unique
            $slug  = Str::slug($validated['phone']);
            $email = "quick-{$slug}@clients.local";

            $user = User::create([
                'name'     => $validated['name'],
                'phone'    => $validated['phone'],
                'email'    => $email,
                'password' => Hash::make(Str::random(24)),
            ]);
        } else {
            // Met à jour le nom si l'utilisateur existait sans nom
            if (empty($user->name)) {
                $user->update(['name' => $validated['name']]);
            }
        }

        // ── Construire l'objet adresse minimale ──────────────────────────────
        [$firstName, $lastName] = $this->splitName($validated['name']);

        $shippingAddress = new AddressData(
            first_name:   $firstName,
            last_name:    $lastName,
            address_line1: $validated['commune'],
            city:         $city,
            // Le pays est repris tel quel : il détermine la destination, donc
            // l'onglet de l'écran des expéditions et les règles de règlement.
            country:      $pays,
            phone:        $validated['phone'],
            email:        $validated['email'] ?? null,
            landmark:     $validated['landmark'] ?? null,
            commune:      $validated['commune'],
        );

        $orderData = new OrderData(
            shipping_address: $shippingAddress,
            payment_method:   $validated['payment_method'] === 'cod' ? 'delivery' : $validated['payment_method'],
            billing_address:  $shippingAddress,
            shipping_method:  'standard',
            customer_note:    $validated['note'] ?? null,
            items:            $validated['items'],
            coupon_code:      $validated['coupon_code'] ?? null,
            confirm_duplicate: (bool) ($validated['confirm_duplicate'] ?? false),
        );

        // ── Créer la commande ─────────────────────────────────────────────────
        try {
            $order = $this->orderService->createFromCart($user, $orderData);
        } catch (DuplicateOrderException $e) {
            // 409 : la commande n'est pas refusée, elle attend une réponse.
            // L'écran montre la commande déjà en cours, puis renvoie la même
            // requête avec `confirm_duplicate` si la cliente maintient.
            return response()->json([
                'message'               => $e->getMessage(),
                'requires_confirmation' => true,
                'duplicate_order'       => DuplicateOrderCheck::summary($e->existing),
            ], 409);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // ── Token d'accès pour que le client puisse voir sa commande ─────────
        $token = $user->createToken('quick-order')->plainTextToken;

        $payload = (new OrderResource($order))->toArray($request);
        $payload['access_token']  = $token;
        $payload['token_type']    = 'Bearer';

        // Si GeniusPay est activé et que le client a choisi Wave/Orange Money,
        // on initie le paiement en ligne et on renvoie l'URL de redirection.
        if (
            in_array($validated['payment_method'], ['wave', 'orange_money'], true)
            && $this->paymentService->geniusPayEnabled()
        ) {
            try {
                $paymentData = $this->paymentService->initiate($order, $validated['payment_method']);
                $payload['payment_url'] = $paymentData['payment_url'] ?? null;
            } catch (\Throwable $e) {
                \Log::error('QuickOrder payment init failed: ' . $e->getMessage());
            }
        }

        return response()->json($payload, 201);
    }

    /** Sépare "Fatou Konaté" → ["Fatou", "Konaté"] */
    private function splitName(string $name): array
    {
        $parts = explode(' ', trim($name), 2);
        return [
            $parts[0] ?? $name,
            $parts[1] ?? '',
        ];
    }
}
