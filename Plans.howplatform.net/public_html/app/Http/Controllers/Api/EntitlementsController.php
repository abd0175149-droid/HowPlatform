<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EntitlementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EntitlementsController extends Controller
{
    public function __construct(private EntitlementService $entitlements) {}

    /** GET /api/v1/entitlements?user_id=123 — الخطّة الفعّالة + المزايا. */
    public function show(Request $request): JsonResponse
    {
        $v = $request->validate(['user_id' => ['required', 'integer', 'min:1']]);

        return response()->json(array_merge(
            ['ok' => true, 'user_id' => (int) $v['user_id']],
            $this->entitlements->forUser((int) $v['user_id'])
        ));
    }
}
