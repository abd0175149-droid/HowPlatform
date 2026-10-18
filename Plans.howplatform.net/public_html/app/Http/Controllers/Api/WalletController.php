<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WalletService;
use App\Models\WalletLedger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WalletController extends Controller
{
    public function __construct(private WalletService $wallet) {}

    /** GET /api/v1/wallet?user_id=123 */
    public function balance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json([
            'ok'      => true,
            'user_id' => (int) $data['user_id'],
            'balance' => $this->wallet->balance((int) $data['user_id']),
        ]);
    }

    /** GET /api/v1/wallet/transactions?user_id=123&limit=20&offset=0 — سجلّ الحركات. */
    public function transactions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
            'limit'   => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset'  => ['nullable', 'integer', 'min:0'],
        ]);

        $userId = (int) $data['user_id'];
        $limit  = (int) ($data['limit'] ?? 20);
        $offset = (int) ($data['offset'] ?? 0);

        $base  = WalletLedger::where('user_id', $userId);
        $total = (clone $base)->count();

        $rows = $base->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get(['points', 'balance_after', 'kind', 'event_type', 'description', 'created_at']);

        return response()->json([
            'ok'           => true,
            'user_id'      => $userId,
            'balance'      => $this->wallet->balance($userId),
            'total'        => $total,
            'transactions' => $rows,
            'items'        => $rows, // مرادف متوافق مع الدبلوما
        ]);
    }

    /** POST /api/v1/wallet/earn */
    public function earn(Request $request): JsonResponse
    {
        return $this->mutate($request, 'earn');
    }

    /** POST /api/v1/wallet/spend */
    public function spend(Request $request): JsonResponse
    {
        return $this->mutate($request, 'spend');
    }

    /** POST /api/v1/wallet/refund */
    public function refund(Request $request): JsonResponse
    {
        return $this->mutate($request, 'refund');
    }

    private function mutate(Request $request, string $kind): JsonResponse
    {
        $data = $request->validate([
            'user_id'        => ['required', 'integer', 'min:1'],
            'points'         => ['required', 'integer', 'min:1', 'max:100000000'],
            'event_type'     => ['nullable', 'string', 'max:100'],
            'event_ref_type' => ['nullable', 'string', 'max:40'],
            'event_ref_id'   => ['nullable', 'string', 'max:64'],
            'description'    => ['nullable', 'string', 'max:255'],
        ]);

        // مفتاح منع الازدواج: من الترويسة (مفضّل) أو الجسم. إلزامي.
        $key = (string) ($request->header('Idempotency-Key') ?: $request->input('idempotency_key', ''));
        if ($key === '' || mb_strlen($key) > 191) {
            return response()->json(['ok' => false, 'error' => 'invalid_idempotency_key'], 422);
        }

        $client = (string) $request->attributes->get('service_client', 'unknown');

        $meta = [
            'service_client' => $client,
            'idempotency_key' => $key,
            'endpoint'       => $kind,
            'event_type'     => $data['event_type'] ?? null,
            'event_ref_type' => $data['event_ref_type'] ?? null,
            'event_ref_id'   => $data['event_ref_id'] ?? null,
            'description'    => $data['description'] ?? null,
        ];

        $result = $this->wallet->{$kind}((int) $data['user_id'], (int) $data['points'], $meta);

        return response()->json($result['body'], $result['code']);
    }
}
