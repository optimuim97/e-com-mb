<?php

namespace Modules\Blog\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Modules\Blog\Http\Resources\PostCategoryResource;
use Modules\Blog\Http\Resources\PostResource;
use Modules\Blog\Models\Post;
use Modules\Blog\Models\PostCategory;

class PostController extends Controller
{
    public function index(Request $request): ResourceCollection
    {
        $posts = Post::with(['category', 'author'])
            ->published()
            ->when($request->filled('category'), fn($q) => $q->whereHas('category', fn($c) => $c->where('slug', $request->category)))
            ->when($request->filled('search'),   fn($q) => $q->where('title', 'like', '%' . $request->search . '%'))
            ->latest('published_at')
            ->paginate(12);

        return PostResource::collection($posts);
    }

    public function show(string $slug): PostResource
    {
        $post = Post::with(['category', 'author'])
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return new PostResource($post);
    }

    public function categories(): ResourceCollection
    {
        return PostCategoryResource::collection(
            PostCategory::whereHas('posts', fn($q) => $q->published())
                ->where('is_active', true)
                ->withCount(['posts' => fn($q) => $q->published()])
                ->orderBy('name')
                ->get()
        );
    }
}
