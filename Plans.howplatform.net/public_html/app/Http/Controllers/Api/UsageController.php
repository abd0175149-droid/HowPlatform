<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UsageService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * عدّاد استهلاك المزايا المحدودة بحصّة (rate-limited features).
 * تستهلكها المنصّات: diploma (رسائل فَطين اليومية)، library (تحميلات يومية).
 */
class UsageController extends Controller
{
    public function __construct(private UsageService $usage) {}

    /** GET /api/v1/usage?user_id=&feature_key=&default_limit=&period=day — قراءة فقط. */
    public function show(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        return response()->json($this->usage->snapshot(
            (int) $data['user_id'],
            $data['feature_key'],
            $data['default_limit'],
            $data['period']
        ));
    }

    /**
     * POST /api/v1/usage/consume {user_id, feature_key, amount?=1, default_limit?, period?=day}
     * استهلاك ذرّي check-and-increment. يُعيد 200 دائمًا مع allowed=true|false
     * (يفشل مغلقًا عند تجاوز الحصّة دون زيادة العدّاد). المنصّة تترجم allowed=false لـ 403/429 عندها.
     */
    public function consume(Request $request): JsonResponse
    {
        $data = $this->validated($request, true);

        return response()->json($this->usage->consume(
            (int) $data['user_id'],
            $data['feature_key'],
            (int) ($data['amount'] ?? 1),
            $data['default_limit'],
            $data['period']
        ));
    }

    /** تحقّق موحّد للطلبين. */
    private function validated(Request $request, bool $withAmount = false): array
    {
        $rules = [
            'user_id'       => ['required', 'integer', 'min:1'],
            'feature_key'   => ['required', 'string', 'max:100'],
            'default_limit' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'period'        => ['nullable', 'in:day,month'],
        ];
        if ($withAmount) {
            $rules['amount'] = ['nullable', 'integer', 'min:1', 'max:1000000'];
        }

        $v = $request->validate($rules);

        return [
            'user_id'       => $v['user_id'],
            'feature_key'   => $v['feature_key'],
            'default_limit' => array_key_exists('default_limit', $v) && $v['default_limit'] !== null
                ? (int) $v['default_limit']
                : null,
            'period'        => $v['period'] ?? 'day',
            'amount'        => $v['amount'] ?? 1,
        ];
    }
}
