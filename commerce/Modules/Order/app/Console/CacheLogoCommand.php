<?php

namespace Modules\Order\Console;

use Illuminate\Console\Command;
use Modules\Admin\Models\Setting;

class CacheLogoCommand extends Command
{
    protected $signature   = 'logo:cache {--force : Retélécharger même si le cache existe}';
    protected $description = 'Télécharge le logo de la boutique (réglage shop_logo_url) et le met en cache pour la génération PDF.';

    public function handle(): int
    {
        $url = trim((string) Setting::get('shop_logo_url', ''));

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            $this->warn('Aucun logo réglé (Paramètres → Boutique → URL du logo) : les factures afficheront le nom de la boutique.');
            return self::SUCCESS;
        }

        // Même clé que InvoiceService : un fichier par URL de logo.
        $cachePath = storage_path('app/logo-pdf-' . md5($url) . '.cache');

        if (file_exists($cachePath) && !$this->option('force')) {
            $this->info('Cache logo déjà présent (' . round(filesize($cachePath) / 1024, 1) . ' ko). Utilisez --force pour le recharger.');
            return self::SUCCESS;
        }

        $this->info("Téléchargement du logo depuis {$url}…");

        $ctx  = stream_context_create([
            'http' => ['timeout' => 15, 'user_agent' => 'Mozilla/5.0 (compatible; ShopInvoice/1.0)'],
            'ssl'  => ['verify_peer' => false],
        ]);
        $data = @file_get_contents($url, false, $ctx);

        if ($data === false || strlen($data) < 100) {
            $this->error('Échec du téléchargement. Vérifiez la connectivité du serveur.');
            return self::FAILURE;
        }

        file_put_contents($cachePath, $data);
        $this->info('Logo mis en cache (' . round(strlen($data) / 1024, 1) . ' ko) → ' . $cachePath);

        return self::SUCCESS;
    }
}
