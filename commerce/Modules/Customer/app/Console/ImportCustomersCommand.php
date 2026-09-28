<?php

namespace Modules\Customer\Console;

use App\Models\Address;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Reprend le fichier clientes de la boutique.
 *
 * La caisse crée déjà une fiche à la première commande, mais attendre revient
 * à perdre ce que la gérante sait de ses clientes : qui commande depuis deux
 * ans, qui habite où, qui joindre pour une nouveauté. Ce carnet existe avant
 * le site et doit y entrer avec lui.
 *
 * Le téléphone est la clé de rapprochement, pas l'email : à Abidjan une
 * cliente sur deux n'en donne pas, et deux clientes partagent parfois celui
 * d'un proche. Les numéros sont normalisés sur leurs 8 chiffres locaux, ce
 * qui rend 07 07 84 98 83, +2250707849883 et 0707849883 équivalents.
 *
 * Format attendu (point-virgule, en-tête obligatoire) :
 *   nom;telephone;email;commune;quartier;note
 *
 * Usage :
 *   php artisan shop:import-customers clientes.csv --dry-run
 *   php artisan shop:import-customers clientes.csv
 */
class ImportCustomersCommand extends Command
{
    protected $signature = 'shop:import-customers
        {fichier : CSV du carnet clientes (nom;telephone;email;commune;quartier;note)}
        {--dry-run : Analyser le fichier et afficher le résultat sans rien créer}';

    protected $description = 'Importe le carnet clientes existant de la boutique';

    public function handle(): int
    {
        $path = $this->argument('fichier');
        if (! is_file($path)) {
            $this->error("Fichier introuvable : {$path}");

            return self::FAILURE;
        }

        $rows = $this->readCsv($path);
        if ($rows === null) {
            return self::FAILURE;
        }

        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $aCreer     = [];
        $existantes = [];
        $rejetees   = [];
        $vusDansLot = [];

        foreach ($rows as $numero => $row) {
            $nom       = trim($row['nom'] ?? '');
            $telephone = $this->normalizePhone($row['telephone'] ?? '');

            if ($nom === '') {
                $rejetees[] = [$numero, '(sans nom)', 'Nom manquant'];
                continue;
            }

            if ($telephone === null) {
                $rejetees[] = [$numero, $nom, 'Téléphone absent ou inexploitable'];
                continue;
            }

            // Doublon interne au fichier : deux lignes pour la même personne
            // créeraient deux fiches que plus rien ne rapprocherait ensuite.
            if (isset($vusDansLot[$telephone])) {
                $rejetees[] = [$numero, $nom, 'Doublon dans le fichier (ligne ' . $vusDansLot[$telephone] . ')'];
                continue;
            }
            $vusDansLot[$telephone] = $numero;

            if ($existante = $this->findByPhone($telephone)) {
                $existantes[] = [$numero, $nom, $telephone, $existante->name];
                continue;
            }

            $aCreer[] = [
                'nom'       => $nom,
                'telephone' => $telephone,
                'email'     => $this->cleanEmail($row['email'] ?? ''),
                'commune'   => trim($row['commune'] ?? ''),
                'quartier'  => trim($row['quartier'] ?? ''),
                'note'      => trim($row['note'] ?? ''),
            ];
        }

        $this->report($rows, $aCreer, $existantes, $rejetees);

        if (empty($aCreer)) {
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('Simulation (--dry-run) : aucune fiche créée.');

            return self::SUCCESS;
        }

        if (! $this->confirm(sprintf('Créer ces %d fiche(s) cliente ?', count($aCreer)), true)) {
            $this->line('Abandon — rien n\'a été créé.');

            return self::SUCCESS;
        }

        $this->createCustomers($aCreer);

        return self::SUCCESS;
    }

    // ── Création ──────────────────────────────────────────────────────────────

    /** @param array<int, array<string, string|null>> $clientes */
    private function createCustomers(array $clientes): void
    {
        $barre = $this->output->createProgressBar(count($clientes));
        $barre->start();

        DB::transaction(function () use ($clientes, $barre) {
            foreach ($clientes as $c) {
                $cliente = User::create([
                    'name'     => $c['nom'],
                    'phone'    => $c['telephone'],
                    // L'email est obligatoire en base et sert d'identifiant de
                    // connexion. Sans email connu, on en fabrique un inerte
                    // sur `.local` : jamais routable, donc aucun envoi ne peut
                    // partir vers un inconnu par erreur.
                    'email'    => $c['email'] ?? 'cliente-' . $c['telephone'] . '@boutique.local',
                    // Mot de passe aléatoire : la cliente passera par « mot de
                    // passe oublié » ou par un code à usage unique. Un mot de
                    // passe commun connu de tous serait pire que pas de compte.
                    'password' => Hash::make(Str::random(32)),
                ]);

                $cliente->syncRoles(['customer']);

                if ($c['quartier'] || $c['commune']) {
                    [$prenom, $nom] = $this->splitName($c['nom']);

                    Address::create([
                        'user_id'       => $cliente->id,
                        'first_name'    => $prenom,
                        'last_name'     => $nom,
                        'phone'         => $c['telephone'],
                        'address_line1' => $c['quartier'] ?: $c['commune'],
                        'address_line2' => $c['quartier'] ? $c['commune'] : null,
                        'city'          => 'Abidjan',
                        'state'         => $c['commune'] ?: null,
                        'country'       => 'CI',
                        'is_default'    => true,
                    ]);
                }

                $barre->advance();
            }
        });

        $barre->finish();
        $this->newLine(2);
        $this->info('✓ ' . count($clientes) . ' fiche(s) cliente créée(s).');
        $this->line('   Elles apparaissent dans Back-office → Clients.');
    }

    // ── Restitution ───────────────────────────────────────────────────────────

    /**
     * @param  array<int, array<string, string>>       $rows
     * @param  array<int, array<string, string|null>>  $aCreer
     * @param  array<int, array<int, string>>          $existantes
     * @param  array<int, array<int, string>>          $rejetees
     */
    private function report(array $rows, array $aCreer, array $existantes, array $rejetees): void
    {
        $this->newLine();
        $this->info('👤 Carnet clientes — ' . count($rows) . ' ligne(s) lue(s)');
        $this->line('   ' . count($aCreer) . ' à créer · ' . count($existantes) . ' déjà en base · ' . count($rejetees) . ' écartée(s)');

        if ($rejetees) {
            $this->newLine();
            $this->warn('Lignes écartées :');
            $this->table(['Ligne', 'Nom', 'Motif'], $rejetees);
        }

        if ($existantes) {
            $this->newLine();
            $this->line('Déjà connues (aucune modification) :');
            $this->table(['Ligne', 'Nom du fichier', 'Téléphone', 'Nom en base'], array_slice($existantes, 0, 20));
            if (count($existantes) > 20) {
                $this->line('   … et ' . (count($existantes) - 20) . ' autre(s).');
            }
        }

        if ($aCreer) {
            $this->newLine();
            $this->table(
                ['Nom', 'Téléphone', 'Email', 'Commune'],
                array_map(fn ($c) => [
                    $c['nom'],
                    $c['telephone'],
                    $c['email'] ?? '(généré)',
                    $c['commune'] ?: '—',
                ], array_slice($aCreer, 0, 25))
            );
            if (count($aCreer) > 25) {
                $this->line('   … et ' . (count($aCreer) - 25) . ' autre(s).');
            }
        }
    }

    // ── Lecture ───────────────────────────────────────────────────────────────

    /** @return array<int, array<string, string>>|null */
    private function readCsv(string $path): ?array
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            $this->error("Lecture impossible : {$path}");

            return null;
        }

        $separator = $this->detectSeparator($path);
        $headers   = null;
        $rows      = [];
        $numero    = 0;

        while (($cells = fgetcsv($handle, 0, $separator, '"', '')) !== false) {
            $numero++;

            if ($cells === [null] || $cells === [] || $cells === [false]) {
                continue;
            }

            if ($headers === null) {
                $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cells[0]);
                $headers  = array_map(fn ($h) => Str::slug(trim((string) $h), '_'), $cells);

                foreach (['nom', 'telephone'] as $requis) {
                    if (! in_array($requis, $headers, true)) {
                        fclose($handle);
                        $this->error("Colonne « {$requis} » absente. Attendu : nom;telephone;email;commune;quartier;note");

                        return null;
                    }
                }

                continue;
            }

            $cells = array_pad($cells, count($headers), '');
            $rows[$numero] = array_combine($headers, array_slice($cells, 0, count($headers)));
        }

        fclose($handle);

        if ($headers === null) {
            $this->error('Fichier vide.');

            return null;
        }

        return $rows;
    }

    private function detectSeparator(string $path): string
    {
        $probe = fopen($path, 'r');
        $first = (string) fgets($probe);
        fclose($probe);

        return substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
    }

    // ── Normalisation ─────────────────────────────────────────────────────────

    /**
     * Ramène un numéro ivoirien à ses 10 chiffres locaux (07…, 05…, 01…).
     *
     * Depuis le passage à 10 chiffres, les carnets contiennent encore des
     * numéros à 8 chiffres. On les laisse tels quels plutôt que de deviner
     * un préfixe : une fiche avec un numéro faux vaut moins que pas de fiche.
     */
    private function normalizePhone(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw);

        if ($digits === '') {
            return null;
        }

        // Indicatif pays, avec ou sans 00.
        $digits = preg_replace('/^(00)?225/', '', $digits);

        return strlen($digits) >= 8 ? $digits : null;
    }

    /** Rapprochement sur les 8 derniers chiffres, insensible au formatage. */
    private function findByPhone(string $phone): ?User
    {
        $local = substr($phone, -8);

        return User::whereRaw(
            "REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?",
            ["%{$local}"]
        )->orderBy('id')->first();
    }

    private function cleanEmail(string $raw): ?string
    {
        $email = strtolower(trim($raw));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        // Un email déjà pris appartient à une autre fiche : le réutiliser
        // ferait échouer l'insertion sur la contrainte d'unicité.
        return User::where('email', $email)->exists() ? null : $email;
    }

    /** @return array{0: string, 1: string} */
    private function splitName(string $name): array
    {
        $parts = explode(' ', trim($name), 2);

        return [$parts[0], $parts[1] ?? ''];
    }
}
