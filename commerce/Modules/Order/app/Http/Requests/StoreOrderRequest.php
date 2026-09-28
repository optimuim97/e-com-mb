<?php

namespace Modules\Order\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Order\Models\Order;
use Modules\Order\Support\PaymentAvailability;

use Modules\Order\Rules\ValidPhone;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }

    public function rules(): array
    {
        return [
            'shipping_address'               => 'required|array',
            'shipping_address.first_name'    => 'required|string',
            'shipping_address.last_name'     => 'nullable|string',
            'shipping_address.address_line1' => 'required|string',
            'shipping_address.city'          => 'required|string',
            'shipping_address.commune'       => 'nullable|string|max:120',
            'shipping_address.region'        => 'nullable|string|max:120',
            'shipping_address.country'       => 'required|string|min:2|max:5',
            // Seul moyen de joindre la cliente à la livraison. Strict en Côte
            // d'Ivoire, simple garde-fou ailleurs — voir ValidPhone.
            'shipping_address.phone'         => ['nullable', 'string', new ValidPhone(
                strtoupper(trim((string) $this->input('shipping_address.country', 'CI'))) ?: 'CI',
            )],
            'shipping_address.email'         => 'nullable|email|max:191',
            'shipping_address.landmark'      => 'nullable|string|max:500',
            'shipping_address.receiver_phone'=> 'nullable|string|max:30',
            'billing_address'                => 'nullable|array',
            'shipping_method'                => 'nullable|string',
            // Exigé selon la destination — voir withValidator() : hors zone, la
            // cliente ne choisit rien, nos agents fixent le règlement plus tard.
            'payment_method'                 => 'nullable|in:'.implode(',', Order::PAYMENT_METHODS_CHECKOUT),
            'customer_note'                  => 'nullable|string|max:1000',
            // Code tapé dans le tunnel plutôt que posé sur le panier. Le montant
            // n'est jamais repris du front : il est recalculé à la création.
            'coupon_code'                    => 'nullable|string|max:60',
            // Réponse de la cliente à qui l'on vient de montrer une commande
            // similaire encore en cours : « oui, celle-ci est nouvelle ».
            'confirm_duplicate'              => 'nullable|boolean',
            // Fallback items from frontend cart (used when DB cart is empty)
            'items'                          => 'nullable|array',
            'items.*.product_id'             => 'required_with:items|integer|exists:products,id',
            'items.*.variant_id'             => 'nullable|integer|exists:product_variants,id',
            'items.*.quantity'               => 'required_with:items|integer|min:1',
            // Frais de livraison calculés côté frontend (quote API)
            'shipping_cost'                  => 'nullable|numeric|min:0',
            'shipping_unknown'               => 'nullable|boolean',
        ];
    }

    /**
     * International : le règlement est vidé plutôt qu'exigé ou refusé.
     *
     * Hors de Côte d'Ivoire, aucun moyen n'est proposé — les frais dépendent du
     * transporteur, du poids, parfois de la douane, et se fixent avec un agent.
     * L'écran n'affiche donc rien à cocher, et ce qu'un formulaire aurait laissé
     * traîner dans le champ ne doit pas décider à la place de la cliente.
     *
     * Même geste que la commande rapide (QuickOrderController), dont c'est le
     * déroulé depuis toujours. Le tunnel classique, lui, refusait l'international
     * quoi qu'il arrive : une cliente hors de Côte d'Ivoire n'avait tout
     * simplement aucun moyen d'aboutir depuis le panier.
     *
     * La zone non tarifée reste à part : elle refuse un moyen envoyé quand même,
     * parce que le total y est incomplet — voir withValidator().
     */
    protected function prepareForValidation(): void
    {
        $address = $this->input('shipping_address');

        if (is_array($address)
            && PaymentAvailability::destination($address) === PaymentAvailability::INTERNATIONAL) {
            $this->merge(['payment_method' => null]);
        }
    }

    /**
     * Le moyen de paiement dépend de la destination (voir PaymentAvailability).
     * Contrôlé ici et pas seulement à l'écran : la liste affichée au checkout
     * est une commodité, elle ne protège rien contre un appel direct à l'API.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $address = $this->input('shipping_address');
            if (! is_array($address)) {
                return;
            }

            // Abidjan est tarifée à la commune : sans elle, impossible de
            // calculer les frais. On le dit ici plutôt que de laisser filer la
            // commande en « hors zone », ce qui obligerait un agent à rappeler.
            if (PaymentAvailability::needsCommune($address)) {
                $validator->errors()->add(
                    'shipping_address.commune',
                    'Sélectionnez votre commune pour calculer les frais de livraison.',
                );

                return;
            }

            // International : rien à régler en ligne. Le champ vient d'être vidé
            // plus haut, la commande part sans, et l'agent fixe frais puis
            // règlement — exactement le déroulé de la commande rapide.
            if (PaymentAvailability::destination($address) === PaymentAvailability::INTERNATIONAL) {
                return;
            }

            $method = $this->input('payment_method');

            // Hors zone : le total n'est pas encore connu (frais à fixer). Un
            // moyen envoyé quand même est refusé plutôt que neutralisé — la
            // cliente réglerait une somme amputée des frais de livraison.
            if (PaymentAvailability::isOutOfZone($address)) {
                if (filled($method)) {
                    $validator->errors()->add(
                        'payment_method',
                        PaymentAvailability::refusalMessage($address),
                    );
                }

                return;
            }

            if (! filled($method)) {
                $validator->errors()->add('payment_method', 'Choisissez un moyen de paiement.');

                return;
            }

            if (! PaymentAvailability::accepts($address, $method)) {
                $validator->errors()->add(
                    'payment_method',
                    PaymentAvailability::refusalMessage($address),
                );
            }
        });
    }
}
