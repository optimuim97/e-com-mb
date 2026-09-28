<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(): ResourceCollection
    {
        return CategoryResource::collection(Category::with('children')->whereNull('parent_id')->orderBy('sort_order')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id'   => 'nullable|exists:categories,id',
            'sort_order'  => 'integer',
            'is_active'   => 'boolean',
        ]);
        $data['slug'] = Str::slug($data['name']);
        return response()->json(new CategoryResource(Category::create($data)), 201);
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category->load('children', 'parent'));
    }

    public function update(Request $request, Category $category): CategoryResource
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'parent_id'   => 'nullable|exists:categories,id',
            'sort_order'  => 'integer',
            'is_active'   => 'boolean',
        ]);
        if (isset($data['name'])) $data['slug'] = Str::slug($data['name']);
        $category->update($data);
        return new CategoryResource($category);
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();
        return response()->json(['message' => 'Catégorie supprimée']);
    }
}
