<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Auth\Services\PinService;

class PinController extends Controller
{
    public function __construct(private readonly PinService $pins) {}

    public function set(Request $request): JsonResponse
    {
        $request->validate([
            'pin'         => ['required', 'string', 'digits:4'],
            'current_pin' => ['nullable', 'string'],
        ]);

        $this->pins->set($request->user(), $request->pin, $request->current_pin);

        return response()->json(['message' => 'Code PIN défini avec succès.']);
    }

    public function verify(Request $request): JsonResponse
    {
        $request->validate(['pin' => ['required', 'string', 'digits:4']]);

        $verified = $this->pins->verify($request->user(), $request->pin);

        return response()->json(['verified' => $verified]);
    }
}
