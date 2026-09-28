<?php

namespace App\Support;

use Illuminate\Routing\Controllers\Middleware;

/**
 * Fabrique de middlewares de permission pour les contrôleurs admin.
 *
 * Colocalise le « check » de permission avec chaque action du contrôleur
 * (via l'interface HasMiddleware de Laravel 12), en mappant les méthodes
 * REST standard sur les permissions granulaires `{resource}.{action}`
 * définies par le PermissionsSeeder.
 *
 * Usage dans un contrôleur :
 *
 *   class ProductController extends Controller implements HasMiddleware
 *   {
 *       public static function middleware(): array
 *       {
 *           return ResourcePermissions::crud('products', extra: [
 *               'products.create' => ['uploadImage'],
 *               'products.delete' => ['deleteImage'],
 *           ]);
 *       }
 *   }
 */
final class ResourcePermissions
{
    /**
     * Retourne les middlewares CRUD standard pour une ressource, plus
     * d'éventuelles méthodes supplémentaires rattachées à une permission.
     *
     * @param  string  $resource  préfixe de permission (ex: "products")
     * @param  array<string, array<int, string>>  $extra  permission => [méthodes]
     * @param  array{view?: string[], create?: string[], edit?: string[], delete?: string[]}  $verbs
     *         override des méthodes rattachées à chaque verbe CRUD
     * @return array<int, Middleware>
     */
    public static function crud(string $resource, array $extra = [], array $verbs = []): array
    {
        $map = [
            "{$resource}.view"   => $verbs['view']   ?? ['index', 'show'],
            "{$resource}.create" => $verbs['create'] ?? ['store'],
            "{$resource}.edit"   => $verbs['edit']   ?? ['update'],
            "{$resource}.delete" => $verbs['delete'] ?? ['destroy'],
        ];

        // Fusionne les méthodes supplémentaires sous leur permission
        foreach ($extra as $permission => $methods) {
            $map[$permission] = array_merge($map[$permission] ?? [], $methods);
        }

        return self::build($map);
    }

    /**
     * Construit une liste de middlewares à partir d'un mapping
     * permission => [méthodes] (permet des cas non-CRUD).
     *
     * @param  array<string, array<int, string>>  $map
     * @return array<int, Middleware>
     */
    public static function build(array $map): array
    {
        $middlewares = [];
        foreach ($map as $permission => $methods) {
            if (empty($methods)) {
                continue;
            }
            $middlewares[] = new Middleware("permission:{$permission}", only: $methods);
        }

        return $middlewares;
    }
}
