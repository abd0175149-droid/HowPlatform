<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserLookupController extends Controller
{
    /** اسم واحد حسب Path Param */

public function showName(int $id)
{
    try {
        $name = Cache::remember("user_name:{$id}", 600, function () use ($id) {
            return User::query()->whereKey($id)->value('name');
        });
    } catch (\Throwable $e) {
        Log::error('UserLookup failed', [
            'id' => $id,
            'err' => $e->getMessage(),
            'code' => $e->getCode(),
        ]);
        return response()->json([
            'success' => false,
            'message' => 'Server error.',
        ], 500);
    }

    if ($name === null) {
        return response()->json(['success'=>false,'message'=>'User not found.'], 404);
    }

    return response()->json(['success'=>true,'id'=>$id,'name'=>$name]);
}


    /** عدّة معرّفات عبر GET ids[]=.. أو POST {ids:[..]} */
    public function namesByIds(Request $request)
    {
        $ids = $request->input('ids');

        // السماح أيضا بـ id مفردة ?id=123 كاختصار
        if (!$ids && $request->filled('id')) {
            $ids = [(int) $request->input('id')];
        }

        if (!$ids) {
            throw ValidationException::withMessages([
                'ids' => ['Provide ids[] in query/body, or id.'],
            ]);
        }

        $validated = $request->validate([
            'ids'   => 'nullable|array|max:200',
            'ids.*' => 'integer|min:1|distinct',
            'id'    => 'nullable|integer|min:1',
        ]);

        $ids = array_values(array_unique(array_map('intval', $ids)));

        // جلب من الكاش أولاً
        $result = [];
        $misses = [];
        foreach ($ids as $id) {
            $cacheKey = "user_name:{$id}";
            if (Cache::has($cacheKey)) {
                $result[$id] = Cache::get($cacheKey);
            } else {
                $misses[] = $id;
            }
        }

        if (!empty($misses)) {
            $names = User::query()
                ->whereIn('id', $misses)
                ->pluck('name', 'id')
                ->all();

            // خزّن المفقود بالكاش
            foreach ($names as $id => $name) {
                Cache::put("user_name:{$id}", $name, 600);
            }

            // ادمج النتائج (غير الموجود سيبقى null)
            foreach ($misses as $id) {
                $result[$id] = $names[$id] ?? null;
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $result, // شكل: { "123":"Rawan Afaneh", "124": null }
        ]);
    }
}
