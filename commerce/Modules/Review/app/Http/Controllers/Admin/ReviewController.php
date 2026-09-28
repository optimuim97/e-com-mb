<?php

namespace Modules\Review\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\JsonResponse;
use Modules\Review\Http\Resources\ReviewResource;
use Modules\Review\Models\Review;

class ReviewController extends Controller implements \Illuminate\Routing\Controllers\HasMiddleware
{
    public static function middleware(): array
    {
        return \App\Support\ResourcePermissions::build([
            'reviews.view'   => ['index'],
            'reviews.edit'   => ['update'],
            'reviews.delete' => ['destroy'],
        ]);
    }

    /**
     * GET /admin/reviews
     * List all reviews with optional filters.
     */
    public function index(Request $request): ResourceCollection
    {
        $reviews = Review::with(['user', 'product'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('rating'), fn($q) => $q->where('rating', $request->rating))
            ->when($request->filled('search'), fn($q) =>
                $q->whereHas('user', fn($u) => $u->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%'))
                  ->orWhereHas('product', fn($p) => $p->where('name', 'like', '%'.$request->search.'%'))
            )
            ->latest()
            ->paginate(20);

        return ReviewResource::collection($reviews);
    }

    /**
     * PATCH /admin/reviews/{review}
     * Approve, reject, or add admin note.
     */
    public function update(Request $request, Review $review): ReviewResource
    {
        $data = $request->validate([
            'status'     => 'sometimes|in:pending,approved,rejected',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $review->update($data);

        return new ReviewResource($review->load('user'));
    }

    /**
     * DELETE /admin/reviews/{review}
     * Hard-delete a review.
     */
    public function destroy(Review $review): JsonResponse
    {
        $review->delete();

        return response()->json(['message' => 'Avis supprimé.']);
    }
}
