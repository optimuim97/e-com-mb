<?php

namespace Modules\Catalog\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Modules\Catalog\Console\ExportStockCommand;
use Modules\Catalog\Console\ImportStockCommand;
use Modules\Catalog\Console\ResetStockCommand;
use Modules\Catalog\Events\StockAlertRaised;
use Modules\Catalog\Listeners\NotifyAdminsOnStockAlert;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Services\OnlineAvailability;
use Modules\Catalog\Support\HomeStatsCache;
use Modules\Promo\Models\Promotion;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped : les allocations sont lues une fois par requête, et oubliées
        // entre deux requêtes ou deux tâches de file. Voir OnlineAvailability.
        $this->app->scoped(OnlineAvailability::class);
    }

    public function boot(): void
    {
        Route::middleware('api')->prefix('api')->group(__DIR__ . '/../../routes/api.php');

        // Rupture ou stock bas : prévenir avant que la cliente ne le découvre.
        Event::listen(StockAlertRaised::class, NotifyAdminsOnStockAlert::class);

        $this->invalidateHomeStats();

        // Cycle d'inventaire : export de la feuille de comptage, puis réalignement.
        if ($this->app->runningInConsole()) {
            $this->commands([
                ExportStockCommand::class,
                ImportStockCommand::class,
                ResetStockCommand::class,
            ]);
        }
    }

    /**
     * Périme la page d'accueil dès qu'une modification l'y rend visible.
     *
     * Seules les écritures d'administration sont écoutées : article et
     * opération promotionnelle. Les avis, les commandes et les comptes
     * alimentent aussi la page, mais ils arrivent en continu — les écouter
     * reviendrait à supprimer le cache, alors qu'un compteur d'avis vieux de
     * cinq minutes ne gêne personne. Une promotion qui n'apparaît pas, si.
     */
    private function invalidateHomeStats(): void
    {
        foreach ([Product::class, Promotion::class] as $model) {
            $model::saved(fn () => HomeStatsCache::forget());
            $model::deleted(fn () => HomeStatsCache::forget());
        }
    }
}
