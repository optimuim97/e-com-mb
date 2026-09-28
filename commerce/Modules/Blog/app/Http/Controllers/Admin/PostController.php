<?php

namespace Modules\Blog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Str;
use Modules\Blog\Http\Resources\PostResource;
use Modules\Blog\Models\Post;

class PostController extends Controller implements \Illuminate\Routing\Controllers\HasMiddleware
{
    public static function middleware(): array
    {
        return \App\Support\ResourcePermissions::crud('blog', extra: ['blog.edit' => ['uploadCover']]);
    }

    public function index(Request $request): ResourceCollection
    {
        $posts = Post::with(['category', 'author'])
            ->when($request->filled('status'),   fn($q) => $q->where('status', $request->status))
            ->when($request->filled('category'), fn($q) => $q->where('post_category_id', $request->category))
            ->when($request->filled('search'),   fn($q) => $q->where('title', 'like', '%' . $request->search . '%'))
            ->latest()
            ->paginate(20);

        return PostResource::collection($posts);
    }

    public function show(Post $post): PostResource
    {
        return new PostResource($post->load(['category', 'author']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['author_id']    = $request->user()->id;
        $data['slug']         = $this->uniqueSlug($data['slug'] ?? Str::slug($data['title']));
        $data['reading_time'] = Post::computeReadingTime($data['content'] ?? '');

        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $post = Post::create($data);
        return response()->json(new PostResource($post->load(['category', 'author'])), 201);
    }

    public function update(Request $request, Post $post): PostResource
    {
        $data = $this->validated($request, update: true);

        if (isset($data['slug'])) {
            $data['slug'] = $this->uniqueSlug($data['slug'], $post->id);
        } elseif (isset($data['title'])) {
            $data['slug'] = $this->uniqueSlug(Str::slug($data['title']), $post->id);
        }

        if (isset($data['content'])) {
            $data['reading_time'] = Post::computeReadingTime($data['content']);
        }

        if (
            isset($data['status']) &&
            $data['status'] === 'published' &&
            $post->status !== 'published' &&
            empty($data['published_at'])
        ) {
            $data['published_at'] = now();
        }

        $post->update($data);
        return new PostResource($post->load(['category', 'author']));
    }

    public function destroy(Post $post): JsonResponse
    {
        $post->delete();
        return response()->json(['message' => 'Article supprimé']);
    }

    public function uploadCover(Request $request, Post $post): JsonResponse
    {
        $request->validate(['image' => 'required|image|max:5120']);
        $path = $request->file('image')->store('blog', 'public');
        $post->update(['cover_image' => $path]);
        return response()->json(['cover_image' => asset('storage/' . $path)]);
    }

    /* ── Helpers ── */
    private function validated(Request $request, bool $update = false): array
    {
        $required = $update ? 'sometimes' : 'required';
        return $request->validate([
            'title'            => "$required|string|max:255",
            'slug'             => 'nullable|string|max:255',
            'excerpt'          => 'nullable|string|max:500',
            'content'          => 'nullable|string',
            'post_category_id' => 'nullable|exists:post_categories,id',
            'status'           => 'sometimes|in:draft,published',
            'published_at'     => 'nullable|date',
            'meta_title'       => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:160',
        ]);
    }

    private function uniqueSlug(string $base, ?int $exceptId = null): string
    {
        $slug  = $base;
        $count = 1;
        $query = Post::where('slug', $slug);
        if ($exceptId) $query->where('id', '!=', $exceptId);

        while ($query->exists()) {
            $slug  = $base . '-' . $count++;
            $query = Post::where('slug', $slug);
            if ($exceptId) $query->where('id', '!=', $exceptId);
        }
        return $slug;
    }
}
