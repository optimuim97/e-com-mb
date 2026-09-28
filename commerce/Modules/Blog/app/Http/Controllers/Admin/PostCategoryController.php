<?php

namespace Modules\Blog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Str;
use Modules\Blog\Http\Resources\PostCategoryResource;
use Modules\Blog\Models\PostCategory;

class PostCategoryController extends Controller implements \Illuminate\Routing\Controllers\HasMiddleware
{
    public static function middleware(): array
    {
        return \App\Support\ResourcePermissions::crud('blog');
    }

    public function index(): ResourceCollection
    {
        return PostCategoryResource::collection(
            PostCategory::withCount('posts')->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        $data['slug'] = Str::slug($data['name']);

        return response()->json(new PostCategoryResource(PostCategory::create($data)), 201);
    }

    public function update(Request $request, PostCategory $postCategory): PostCategoryResource
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);
        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        $postCategory->update($data);
        return new PostCategoryResource($postCategory);
    }

    public function destroy(PostCategory $postCategory): JsonResponse
    {
        $postCategory->delete();
        return response()->json(['message' => 'Catégorie supprimée']);
    }
}
