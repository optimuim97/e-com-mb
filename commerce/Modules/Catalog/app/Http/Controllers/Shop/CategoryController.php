<?php

namespace Modules\Catalog\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Modules\Catalog\Http\Resources\CategoryResource;
use Modules\Catalog\Models\Category;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CategoryController extends Controller
{
    public function index(): ResourceCollection
    {
        return CategoryResource::collection(
            Category::where('is_active', true)
                ->whereNull('parent_id')
                ->with('children')
                ->orderBy('sort_order')
                ->get()
        );
    }

    public function show(string $slug): CategoryResource
    {
        return new CategoryResource(
            Category::where('slug', $slug)->where('is_active', true)->with('children')->firstOrFail()
        );
    }
}
