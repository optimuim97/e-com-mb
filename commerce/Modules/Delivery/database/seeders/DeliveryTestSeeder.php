<?php
// Modules/Delivery/Database/Seeders/DeliveryTestSeeder.php

namespace Modules\Delivery\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Delivery\Models\Courier;
use Modules\Delivery\Models\Customer;
use Modules\Delivery\Models\Delivery;
use Modules\Delivery\Models\DeliveryPayment;

class DeliveryTestSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Couriers de test ────────────────────────────────────────────
        $courier1 = Courier::firstOrCreate(
            ['email' => 'livreur1@test.com'],
            [
                'first_name' => 'Jean',
                'last_name'  => 'Kouadio',
                'phone'      => '+2250700000011',
                'password'   => Hash::make('password123'),
                'status'     => 'ACTIVE',
            ]
        );

        $courier2 = Courier::firstOrCreate(
            ['email' => 'livreur2@test.com'],
            [
                'first_name' => 'Marie',
                'last_name'  => 'Koné',
                'phone'      => '+2250700000022',
                'password'   => Hash::make('password123'),
                'status'     => 'ACTIVE',
            ]
        );

        // ── 2. Clients Abidjan ─────────────────────────────────────────────
        $customers = [
            [
                'name'    => 'Fatou Bamba',
                'phone'   => '+2250707010101',
                'email'   => 'fatou@example.com',
                'address' => 'Cocody Riviera 3, Rue des Jardins',
                'zone'    => 'Cocody',
                'latitude'  => 5.3600,
                'longitude' => -3.9800,
            ],
            [
                'name'    => 'Ibrahim Coulibaly',
                'phone'   => '+2250707020202',
                'email'   => null,
                'address' => 'Yopougon Maroc, face au marché',
                'zone'    => 'Yopougon',
                'latitude'  => 5.3300,
                'longitude' => -4.0700,
            ],
            [
                'name'    => 'Aminata Diallo',
                'phone'   => '+2250707030303',
                'email'   => 'aminata@example.com',
                'address' => 'Plateau, Avenue Noguès, immeuble Cosmos',
                'zone'    => 'Plateau',
                'latitude'  => 5.3200,
                'longitude' => -4.0200,
            ],
            [
                'name'    => 'Kouassi Assoumou',
                'phone'   => '+2250707040404',
                'email'   => null,
                'address' => 'Marcory Résidentiel, Rue 24',
                'zone'    => 'Marcory',
                'latitude'  => 5.3000,
                'longitude' => -3.9900,
            ],
            [
                'name'    => 'Nathalie Gnagne',
                'phone'   => '+2250707050505',
                'email'   => 'nathalie@example.com',
                'address' => 'Abobo Baoulé, quartier Andokoi',
                'zone'    => 'Abobo',
                'latitude'  => 5.4100,
                'longitude' => -4.0100,
            ],
            [
                'name'    => 'Seydou Traoré',
                'phone'   => '+2250707060606',
                'email'   => null,
                'address' => 'Adjamé, Rue 12 mosquée',
                'zone'    => 'Adjamé',
                'latitude'  => 5.3500,
                'longitude' => -4.0400,
            ],
            [
                'name'    => 'Awa Doumbia',
                'phone'   => '+2250707070707',
                'email'   => 'awa@example.com',
                'address' => 'Deux Plateaux, Vallons, Villa 42',
                'zone'    => 'Cocody',
                'latitude'  => 5.3800,
                'longitude' => -3.9600,
            ],
            [
                'name'    => 'Roger N\'Goran',
                'phone'   => '+2250707080808',
                'email'   => null,
                'address' => 'Koumassi, marché Gouro, parcelle 8',
                'zone'    => 'Koumassi',
                'latitude'  => 5.2900,
                'longitude' => -3.9800,
            ],
        ];

        $customerModels = [];
        foreach ($customers as $data) {
            $customerModels[] = Customer::firstOrCreate(
                ['phone' => $data['phone']],
                $data
            );
        }

        // ── 3. Livraisons — couverture complète ───────────────────────────

        $qrCode = fn(string $id) => 'QR-LIVRAISON-' . strtoupper($id) . '-' . now()->format('Ymd');

        $deliveries = [

            // ── Scénario 1 : PENDING / COD — test "Commencer la livraison"
            [
                'order_number'   => 'CMD-20260525-001',
                'customer'       => $customerModels[0],
                'courier'        => $courier1,
                'status'         => 'PENDING',
                'delivery_type'  => 'LOCAL_STANDARD',
                'payment_flow'   => 'COD',
                'product_amount' => 18500,
                'delivery_fee'   => 2000,
                'total_amount'   => 20500,
                'notes'          => 'Appeler avant d\'arriver',
                'assigned_at'    => now()->subHours(2),
            ],

            // ── Scénario 2 : IN_PROGRESS / COD — test Encaisser + Confirmer
            [
                'order_number'   => 'CMD-20260525-002',
                'customer'       => $customerModels[1],
                'courier'        => $courier1,
                'status'         => 'IN_PROGRESS',
                'delivery_type'  => 'LOCAL_STANDARD',
                'payment_flow'   => 'COD',
                'product_amount' => 35000,
                'delivery_fee'   => 2500,
                'total_amount'   => 37500,
                'notes'          => null,
                'assigned_at'    => now()->subHours(1),
            ],

            // ── Scénario 3 : IN_PROGRESS / PREPAID — test Confirmer seulement
            [
                'order_number'   => 'CMD-20260525-003',
                'customer'       => $customerModels[2],
                'courier'        => $courier1,
                'status'         => 'IN_PROGRESS',
                'delivery_type'  => 'LOCAL_EXTENDED',
                'payment_flow'   => 'PREPAID',
                'product_amount' => 52000,
                'delivery_fee'   => 3000,
                'total_amount'   => 55000,
                'notes'          => 'Commande fragile — manip avec soin',
                'assigned_at'    => now()->subMinutes(45),
            ],

            // ── Scénario 4 : IN_PROGRESS / MIXED — test les deux boutons
            [
                'order_number'   => 'CMD-20260525-004',
                'customer'       => $customerModels[3],
                'courier'        => $courier1,
                'status'         => 'IN_PROGRESS',
                'delivery_type'  => 'LOCAL_STANDARD',
                'payment_flow'   => 'MIXED',
                'product_amount' => 28000,
                'delivery_fee'   => 1500,
                'total_amount'   => 29500,
                'notes'          => 'Partie payée en ligne, reste COD',
                'assigned_at'    => now()->subMinutes(30),
            ],

            // ── Scénario 5 : DELIVERED / COD — test affichage livraison terminée
            [
                'order_number'   => 'CMD-20260524-010',
                'customer'       => $customerModels[4],
                'courier'        => $courier1,
                'status'         => 'DELIVERED',
                'delivery_type'  => 'LOCAL_STANDARD',
                'payment_flow'   => 'COD',
                'product_amount' => 15000,
                'delivery_fee'   => 2000,
                'total_amount'   => 17000,
                'notes'          => null,
                'assigned_at'    => now()->subDay(),
                'delivered_at'   => now()->subDay()->addHours(3),
                'confirmed_at'   => now()->subDay()->addHours(3),
            ],

            // ── Scénario 6 : DELIVERED / PREPAID — historique carte verte
            [
                'order_number'   => 'CMD-20260524-011',
                'customer'       => $customerModels[5],
                'courier'        => $courier2,
                'status'         => 'DELIVERED',
                'delivery_type'  => 'LOCAL_EXTENDED',
                'payment_flow'   => 'PREPAID',
                'product_amount' => 85000,
                'delivery_fee'   => 5000,
                'total_amount'   => 90000,
                'notes'          => null,
                'assigned_at'    => now()->subDay(),
                'delivered_at'   => now()->subDay()->addHours(2),
                'confirmed_at'   => now()->subDay()->addHours(2),
            ],

            // ── Scénario 7 : FAILED — test raison d'échec
            [
                'order_number'   => 'CMD-20260524-012',
                'customer'       => $customerModels[6],
                'courier'        => $courier1,
                'status'         => 'FAILED',
                'delivery_type'  => 'LOCAL_STANDARD',
                'payment_flow'   => 'COD',
                'product_amount' => 22000,
                'delivery_fee'   => 2000,
                'total_amount'   => 24000,
                'notes'          => null,
                'assigned_at'    => now()->subDay(),
                'failure_reason' => 'Client absent — porte fermée, numéro ne répond pas',
            ],

            // ── Scénario 8 : CANCELLED — test livraison annulée
            [
                'order_number'   => 'CMD-20260524-013',
                'customer'       => $customerModels[7],
                'courier'        => $courier2,
                'status'         => 'CANCELLED',
                'delivery_type'  => 'LOCAL_STANDARD',
                'payment_flow'   => 'PREPAID',
                'product_amount' => 9500,
                'delivery_fee'   => 1500,
                'total_amount'   => 11000,
                'notes'          => null,
                'assigned_at'    => now()->subDay(),
                'failure_reason' => 'Commande annulée par le client avant livraison',
            ],

            // ── Scénario 9 : PENDING / COD montant élevé — test quickAmounts
            [
                'order_number'   => 'CMD-20260525-005',
                'customer'       => $customerModels[5],
                'courier'        => $courier1,
                'status'         => 'PENDING',
                'delivery_type'  => 'NATIONAL_EXPEDITION',
                'payment_flow'   => 'COD',
                'product_amount' => 97500,
                'delivery_fee'   => 6000,
                'total_amount'   => 103500,
                'notes'          => 'Colis expédié depuis Abidjan — signature obligatoire',
                'assigned_at'    => now()->subHours(3),
            ],

            // ── Scénario 10 : PENDING sans zone — test affichage sans zone
            [
                'order_number'   => 'CMD-20260525-006',
                'customer_data'  => [
                    'name'    => 'Client Sans Zone',
                    'phone'   => '+2250709999999',
                    'address' => '15 Rue Inconnu, Abidjan',
                    'zone'    => null,
                ],
                'courier'        => $courier2,
                'status'         => 'PENDING',
                'delivery_type'  => 'LOCAL_STANDARD',
                'payment_flow'   => 'COD',
                'product_amount' => 7500,
                'delivery_fee'   => 1500,
                'total_amount'   => 9000,
                'notes'          => null,
                'assigned_at'    => now()->subMinutes(15),
            ],
        ];

        foreach ($deliveries as $data) {
            // Résoudre le customer
            if (isset($data['customer_data'])) {
                $customer = Customer::firstOrCreate(
                    ['phone' => $data['customer_data']['phone']],
                    $data['customer_data']
                );
            } else {
                $customer = $data['customer'];
            }

            $courier = $data['courier'];

            Delivery::firstOrCreate(
                ['order_number' => $data['order_number']],
                [
                    'customer_id'    => $customer->id,
                    'courier_id'     => $courier->id,
                    'status'         => $data['status'],
                    'delivery_type'  => $data['delivery_type'],
                    'payment_flow'   => $data['payment_flow'],
                    'product_amount' => $data['product_amount'],
                    'delivery_fee'   => $data['delivery_fee'],
                    'total_amount'   => $data['total_amount'],
                    'qr_code'        => $qrCode($data['order_number']),
                    'notes'          => $data['notes'] ?? null,
                    'failure_reason' => $data['failure_reason'] ?? null,
                    'assigned_at'    => $data['assigned_at'],
                    'delivered_at'   => $data['delivered_at'] ?? null,
                    'confirmed_at'   => $data['confirmed_at'] ?? null,
                ]
            );
        }

        // ── 4. Paiements pour les livraisons DELIVERED ────────────────────
        $delivered = Delivery::whereIn('order_number', [
            'CMD-20260524-010',
            'CMD-20260524-011',
        ])->get();

        foreach ($delivered as $delivery) {
            if ($delivery->payments()->exists()) continue;

            if ($delivery->payment_flow === 'COD') {
                DeliveryPayment::create([
                    'delivery_id'           => $delivery->id,
                    'courier_id'            => $delivery->courier_id,
                    'payment_method'        => 'CASH',
                    'mobile_operator'       => null,
                    'transaction_reference' => null,
                    'amount_expected'       => $delivery->total_amount,
                    'amount_received'       => $delivery->total_amount + 3000, // client a rendu + monnaie
                    'change_amount'         => 3000,
                    'paid_at'               => $delivery->delivered_at,
                ]);
            }
        }

        // ── Résumé ─────────────────────────────────────────────────────────
        $this->command->newLine();
        $this->command->info('╔══════════════════════════════════════════╗');
        $this->command->info('║     LIVRAISONS DE TEST — App Livreur     ║');
        $this->command->info('╚══════════════════════════════════════════╝');
        $this->command->newLine();
        $this->command->table(
            ['Commande', 'Statut', 'Flux', 'Total', 'Livreur'],
            Delivery::with(['courier'])->get()->map(fn($d) => [
                $d->order_number,
                $d->status,
                $d->payment_flow,
                number_format($d->total_amount, 0, ',', ' ') . ' FCFA',
                $d->courier->first_name ?? '—',
            ])->toArray()
        );
        $this->command->newLine();
        $this->command->info('Comptes livreurs de test :');
        $this->command->line('  📧 livreur1@test.com  /  🔑 password123');
        $this->command->line('  📧 livreur2@test.com  /  🔑 password123');
        $this->command->newLine();
    }
}
