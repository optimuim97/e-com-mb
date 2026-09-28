<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Clientes de test pour la recette avant lancement.
 *
 * ⚠ NE PAS appeler depuis DatabaseSeeder : ces comptes ne doivent jamais
 * partir en production par inadvertance. Lancement explicite uniquement :
 *   php artisan db:seed --class='Database\Seeders\TestCustomersSeeder'
 *
 * Trois garde-fous rendent ces comptes reconnaissables et inoffensifs :
 *   · le domaine `@boutique.test` — le TLD `.test` est réservé par l'IANA,
 *     aucun email ne peut donc partir vers une vraie personne si un envoi
 *     échappe au mode « log » ;
 *   · le préfixe `[TEST]` dans le nom, visible partout dans le back-office ;
 *   · des numéros en 07 00 00 00 xx, jamais attribués.
 *
 * Purge avant go-live (les commandes d'abord, la contrainte user_id les
 * retiendrait sinon) :
 *   $ids = App\Models\User::where('email','like','%@boutique.test')->pluck('id');
 *   Modules\Order\Models\Order::whereIn('user_id',$ids)->forceDelete();
 *   App\Models\User::whereIn('id',$ids)->delete();
 */
class TestCustomersSeeder extends Seeder
{
    /** Mot de passe commun : ces comptes sont manipulés par plusieurs testeurs. */
    private const PASSWORD = 'test1234';

    /**
     * Chaque profil couvre un cas d'usage réel du lancement, pas seulement
     * un nom de plus dans la table.
     *
     * @var array<int, array{name: string, phone: string, commune: string, quartier: string, cas: string}>
     */
    private const CUSTOMERS = [
        ['name' => 'Awa Koffi',        'phone' => '0700000001', 'commune' => 'Cocody',       'quartier' => 'Deux Plateaux, Vallon',      'cas' => 'Commande site + paiement Wave'],
        ['name' => 'Fatou Konaté',     'phone' => '0700000002', 'commune' => 'Yopougon',     'quartier' => 'Niangon Sud',                'cas' => 'Commande site + paiement à la livraison'],
        ['name' => 'Aminata Traoré',   'phone' => '0700000003', 'commune' => 'Marcory',      'quartier' => 'Zone 4, rue Pierre & Marie', 'cas' => 'Commande WhatsApp convertie au POS'],
        ['name' => 'Adjoua Kouamé',    'phone' => '0700000004', 'commune' => 'Abobo',        'quartier' => 'Abobo Baoulé',               'cas' => 'Zone de livraison éloignée (frais majorés)'],
        ['name' => 'Mariam Cissé',     'phone' => '0700000005', 'commune' => 'Treichville',  'quartier' => 'Avenue 16',                  'cas' => 'Retrait en boutique (pas de livraison)'],
        ['name' => 'Nadège Assi',      'phone' => '0700000006', 'commune' => 'Plateau',      'quartier' => 'Rue du Commerce',            'cas' => 'Cliente fidèle — points du club fidélité'],
        ['name' => 'Salimata Diarra',  'phone' => '0700000007', 'commune' => 'Bingerville',  'quartier' => 'Cité Belle Vue',             'cas' => 'Hors Abidjan intra-muros'],
        ['name' => 'Chantal N\'Guessan', 'phone' => '0700000008', 'commune' => 'Riviera',    'quartier' => 'Riviera Palmeraie',          'cas' => 'Commande annulée puis remboursée'],
    ];

    public function run(): void
    {
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $lignes = [];

        foreach (self::CUSTOMERS as $data) {
            $email = $this->emailFor($data['name']);

            $customer = User::updateOrCreate(
                ['email' => $email],
                [
                    'name'              => '[TEST] ' . $data['name'],
                    'phone'             => $data['phone'],
                    'password'          => Hash::make(self::PASSWORD),
                    'email_verified_at' => now(),
                ]
            );

            $customer->syncRoles(['customer']);

            [$prenom, $nom] = $this->splitName($data['name']);

            // Une adresse par défaut évite de la ressaisir à chaque passage
            // en caisse pendant la recette.
            Address::updateOrCreate(
                ['user_id' => $customer->id, 'is_default' => true],
                [
                    'first_name'    => $prenom,
                    'last_name'     => $nom,
                    'phone'         => $data['phone'],
                    'address_line1' => $data['quartier'],
                    'address_line2' => $data['commune'],
                    'city'          => 'Abidjan',
                    'state'         => $data['commune'],
                    'country'       => 'CI',
                ]
            );

            $lignes[] = [$customer->name, $email, $data['phone'], $data['commune'], $data['cas']];
        }

        $this->command->newLine();
        $this->command->info('╔══════════════════════════════════════════════════════════╗');
        $this->command->info('║   CLIENTES DE TEST — recette avant lancement             ║');
        $this->command->info('╚══════════════════════════════════════════════════════════╝');
        $this->command->table(['Nom', 'Email', 'Téléphone', 'Commune', 'Cas couvert'], $lignes);
        $this->command->line('   Mot de passe commun : ' . self::PASSWORD);
        $this->command->warn('⚠  À purger avant le go-live — voir le bloc de commentaires du seeder.');
    }

    /** awa-koffi → awa.koffi@boutique.test */
    private function emailFor(string $name): string
    {
        return str_replace('-', '.', \Illuminate\Support\Str::slug($name)) . '@boutique.test';
    }

    /** @return array{0: string, 1: string} */
    private function splitName(string $name): array
    {
        $parts = explode(' ', trim($name), 2);

        return [$parts[0], $parts[1] ?? ''];
    }
}
