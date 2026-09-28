<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Comptes livreurs.
 *
 * Le rôle `livreur` ne porte aucune permission du back-office : un livreur
 * n'entre pas dans l'administration. Il existe pour deux raisons — nommer qui
 * emporte une tournée, et servir de socle au tableau de bord livreur à venir.
 *
 * À ne pas confondre avec le rôle `courier` du module Delivery, qui
 * s'authentifie contre une autre table et ne partage pas l'espace
 * d'identifiants de `users`.
 *
 * Lancement explicite :
 *   php artisan db:seed --class='Database\Seeders\LivreurSeeder'
 *
 * Idempotent : relancé, il met à jour les comptes existants sans les dupliquer
 * ni réinitialiser un mot de passe déjà changé (voir plus bas).
 */
class LivreurSeeder extends Seeder
{
    /**
     * Livreurs de départ. Les renommer ici, ou les créer directement depuis
     * Utilisateurs → Nouvel utilisateur → rôle « Livreur ».
     *
     * L'identifiant se déduit du nom : « Sidik Ouattara » donne « souattara ».
     *
     * @var array<int, array{name: string, username: ?string, phone: ?string}>
     */
    private const LIVREURS = [
        ['name' => 'Livreur 1', 'username' => 'livreur1', 'phone' => null],
        ['name' => 'Livreur 2', 'username' => 'livreur2', 'phone' => null],
    ];

    /**
     * Domaine des adresses de service. La colonne `email` est unique et non
     * nulle ; on la remplit à partir de l'identifiant, et rien n'y transite.
     * `.local` n'est pas routable : aucun message ne peut partir par accident.
     */
    private const DOMAINE_INTERNE = 'boutique.local';

    private const PASSWORD = 'livreur2026!';

    public function run(): void
    {
        Role::firstOrCreate(['name' => 'livreur', 'guard_name' => 'web']);

        $lignes = [];

        foreach (self::LIVREURS as $donnees) {
            // L'identifiant, pas l'adresse, fait la clé : c'est lui que le
            // livreur saisit, et lui qui doit rester stable d'un lancement à
            // l'autre même si l'adresse de service change.
            // Identifiant explicite pour ces comptes de départ : « Livreur 1 »
            // donnerait « l1 », illisible. Les vrais livreurs, créés depuis
            // l'administration sous leur vrai nom, laissent la déduction faire.
            $identifiant = $donnees['username'] ?? User::usernameBase($donnees['name']);
            $existant    = User::where('username', $identifiant)->first();

            // Le mot de passe n'est posé qu'à la création. Relancer les seeders
            // après un déploiement ne doit pas remettre un compte en service
            // avec le mot de passe imprimé dans ce fichier.
            $livreur = User::updateOrCreate(
                ['username' => $identifiant],
                array_filter([
                    'name'     => $donnees['name'],
                    'email'    => $existant?->email ?: $identifiant . '@' . self::DOMAINE_INTERNE,
                    'phone'    => $donnees['phone'],
                    'password' => $existant ? null : bcrypt(self::PASSWORD),
                ], fn ($v) => $v !== null),
            );

            $livreur->syncRoles(['livreur']);

            $lignes[] = [
                $livreur->name,
                $livreur->username,
                $existant ? 'inchangé' : self::PASSWORD,
            ];
        }

        $this->command->newLine();
        $this->command->info('✓ Comptes livreurs synchronisés.');
        $this->command->table(['Nom', 'Identifiant', 'Mot de passe'], $lignes);
        $this->command->warn('⚠  Changez ces mots de passe avant de les confier.');
    }
}
