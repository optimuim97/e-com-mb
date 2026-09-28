<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Compte du propriétaire de la boutique (marque blanche).
 *
 * Identité lue dans le .env de l'instance :
 *   OWNER_EMAIL=gerant@maboutique.com
 *   OWNER_NAME="Prénom Nom"
 *   OWNER_PASSWORD=...            (facultatif : généré et affiché une fois)
 *
 * Rôle `admin` et non `manager` : le propriétaire doit pouvoir créer et
 * révoquer les accès de son équipe sans passer par l'intégrateur. Le rôle
 * `manager` du PermissionsSeeder exclut volontairement la gestion des comptes
 * et `settings.edit`.
 *
 * Aucun compte ni mot de passe n'est écrit dans le code : une instance
 * livrée à un client ne doit ouvrir aucune porte connue d'un autre.
 *
 * Idempotent : rejouer le seeder remet le rôle, sans écraser un mot de passe
 * déjà changé par le propriétaire.
 */
class OwnerAccountSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) env('OWNER_EMAIL', ''));

        if ($email === '') {
            $this->command?->warn('OWNER_EMAIL absent du .env : aucun compte propriétaire créé.');
            $this->command?->line('   Renseignez OWNER_EMAIL (et OWNER_NAME) puis relancez :');
            $this->command?->line('   php artisan db:seed --class=OwnerAccountSeeder');

            return;
        }

        // Le rôle doit exister même si PermissionsSeeder n'a pas encore tourné.
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $existing = User::where('email', $email)->first();
        $password = (string) env('OWNER_PASSWORD', '') ?: Str::password(14, symbols: false);

        $owner = User::updateOrCreate(
            ['email' => $email],
            [
                'name'              => env('OWNER_NAME', 'Propriétaire'),
                'email_verified_at' => now(),
                // Le mot de passe n'est posé qu'à la création : rejouer le
                // seeder ne doit pas réinitialiser en douce celui qu'il a choisi.
                ...($existing ? [] : ['password' => Hash::make($password)]),
            ]
        );

        $owner->syncRoles(['admin']);

        $this->command?->newLine();
        $this->command?->info('Compte propriétaire (tous les accès)');
        $this->command?->table(
            ['Nom', 'Email', 'Rôle', 'Mot de passe'],
            [[
                $owner->name,
                $email,
                'admin',
                $existing ? '(inchangé — compte déjà existant)' : $password,
            ]]
        );

        if (! $existing) {
            $this->command?->warn('⚠  À transmettre au propriétaire, puis à changer dès la première connexion.');
        }
    }
}
