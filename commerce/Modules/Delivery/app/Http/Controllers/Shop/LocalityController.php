<?php

namespace Modules\Delivery\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Delivery\Models\Locality;

/**
 * Cities and communes offered at checkout.
 *
 * Public on purpose: the address selector is shown before any account exists.
 * Only active localities are returned, and the payload stays small — a name
 * and its communes, nothing the customer does not choose from.
 */
class LocalityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $country = strtoupper((string) $request->query('country', 'CI'));

        // Two letters or nothing: the cache key must not follow arbitrary input.
        if (! preg_match('/^[A-Z]{2}$/', $country)) {
            $country = 'CI';
        }

        return response()->json(['data' => Locality::publicTree($country)]);
    }
}
