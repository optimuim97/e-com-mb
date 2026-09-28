<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Catalogue de démonstration, neutre.
 *
 * Sert aux démos commerciales et au jeu de commandes fictives
 * (`php artisan shop:demo-data`). Jamais lancé en production par défaut :
 * voir SEED_DEMO dans DatabaseSeeder.
 *
 * Non destructif : les articles sont posés par leur slug, un catalogue réel
 * déjà saisi n'est ni vidé ni modifié.
 */
class DemoCatalogSeeder extends Seeder
{
    private const CATEGORIES = [
        'nouveautes'  => ['Nouveautés', 'Les derniers articles arrivés en boutique.'],
        'essentiels'  => ['Essentiels', 'Les incontournables du quotidien.'],
        'accessoires' => ['Accessoires', 'Pour compléter votre commande.'],
    ];

    /** [catégorie, nom, prix, stock, mis en avant] */
    private const PRODUCTS = [
        ['nouveautes',  'Article démo — Édition limitée', 15000, 20, true],
        ['nouveautes',  'Article démo — Nouvelle collection', 12000, 35, true],
        ['essentiels',  'Article démo — Classique', 8000, 50, true],
        ['essentiels',  'Article démo — Format familial', 18000, 25, false],
        ['essentiels',  'Article démo — Petit format', 4500, 80, false],
        ['accessoires', 'Article démo — Étui', 3500, 60, false],
        ['accessoires', 'Article démo — Coffret cadeau', 22000, 15, true],
        ['accessoires', 'Article démo — Recharge', 2500, 100, false],
    ];

    public function run(): void
    {
        $categories = [];
        foreach (self::CATEGORIES as $slug => [$name, $description]) {
            $categories[$slug] = Category::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $description],
            );
        }

        foreach (self::PRODUCTS as $i => [$category, $name, $price, $stock, $featured]) {
            $slug = 'demo-' . \Illuminate\Support\Str::slug($name);

            Product::firstOrCreate(
                ['slug' => $slug],
                [
                    'category_id'       => $categories[$category]->id,
                    'name'              => $name,
                    'description'       => "Produit de démonstration. Remplacez-le par vos propres articles depuis l'administration (Catalogue → Produits).",
                    'short_description' => 'Produit de démonstration',
                    'price'             => $price,
                    'compare_price'     => null,
                    'sku'               => sprintf('DEMO-%03d', $i + 1),
                    'stock'             => $stock,
                    'is_featured'       => $featured,
                    'is_active'         => true,
                ],
            );
        }

        $this->command?->info('Catalogue de démonstration : ' . count(self::PRODUCTS) . ' articles.');
    }
}
