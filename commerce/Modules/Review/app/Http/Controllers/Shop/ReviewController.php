<?php

namespace Modules\Review\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\JsonResponse;
use Modules\Review\Http\Resources\ReviewResource;
use Modules\Review\Models\Review;
use Modules\Catalog\Models\Product;

class ReviewController extends Controller
{
    /**
     * GET /products/{slug}/reviews
     * Public: list approved reviews for a product.
     */
    public function index(string $slug): ResourceCollection
    {
        $product = Product::where('slug', $slug)->firstOrFail();

        $reviews = Review::with('user')
            ->where('product_id', $product->id)
            ->approved()
            ->latest()
            ->paginate(10);

        return ReviewResource::collection($reviews);
    }

    /**
     * POST /products/{slug}/reviews
     * Auth required: submit a review (one per product).
     */
    public function store(Request $request, string $slug): ReviewResource|JsonResponse
    {
        $product = Product::where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'title'   => 'nullable|string|max:120',
            'comment' => 'nullable|string|max:2000',
        ]);

        // Check for existing review
        $existing = Review::where('product_id', $product->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Vous avez déjà soumis un avis pour ce produit.',
            ], 409);
        }

        $review = Review::create([
            'product_id' => $product->id,
            'user_id'    => $request->user()->id,
            'rating'     => $data['rating'],
            'title'      => $data['title'] ?? null,
            'comment'    => $data['comment'] ?? null,
            'status'     => 'pending',
        ]);

        return new ReviewResource($review->load('user'));
    }

    /**
     * GET /reviews/mine
     * Auth required: list the authenticated user's reviews.
     */
    public function mine(Request $request): ResourceCollection
    {
        $reviews = Review::with('user')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return ReviewResource::collection($reviews);
    }

    /**
     * DELETE /reviews/{review}
     * Auth required: delete own pending review.
     */
    public function destroy(Request $request, Review $review): JsonResponse
    {
        if ($review->user_id !== $request->user()->id) {
            abort(403, 'Forbidden');
        }

        if ($review->status !== 'pending') {
            return response()->json([
                'message' => 'Seuls les avis en attente peuvent être supprimés.',
            ], 422);
        }

        $review->delete();

        return response()->json(['message' => 'Avis supprimé.']);
    }
}
