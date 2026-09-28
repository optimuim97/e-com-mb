<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    public function index(): ResourceCollection
    {
        $categories = Cache::remember('shop.categories', 300, fn() =>
            Category::where('is_active', true)
                ->whereNull('parent_id')
                ->with('children')
                ->orderBy('sort_order')
                ->get()
        );

        return CategoryResource::collection($categories)
            ->response()
            ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60');
    }

    public function show(string $slug): CategoryResource
    {
        return new CategoryResource(
            Category::where('slug', $slug)->where('is_active', true)->with('children')->firstOrFail()
        );
    }
}
