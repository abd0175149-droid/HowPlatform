<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AppReleaseController extends Controller
{
    /**
     * ===============================================================
     * GET /api/mobile/app/latest
     * ---------------------------------------------------------------
     * Query params:
     *   platform: android|ios|windows|mac|linux
     *   channel: stable|beta (افتراضي stable)
     *   current_version: نص مثل 1.0.5
     *   current_code: رقم build (int)
     * ---------------------------------------------------------------
     * يرجع تفاصيل آخر إصدار + حالة التحديث
     * ===============================================================
     */
    public function latest(Request $request)
    {
        $data = $request->validate([
            'platform'        => ['required', 'string', 'in:android,ios,windows,mac,linux'],
            'channel'         => ['nullable', 'string', 'in:stable,beta'],
            'current_version' => ['nullable', 'string', 'max:50'],
            'current_code'    => ['nullable', 'integer', 'min:0'],
        ]);

        $platform   = $data['platform'];
        $channel    = $data['channel'] ?? 'stable';
        $currName   = $data['current_version'] ?? null;
        $currCode   = $data['current_code'] ?? null;

        // 🔹 جلب آخر إصدار فعّال
        $latest = DB::table('app_releases')
            ->where('platform', $platform)
            ->where('channel', $channel)
            ->where('is_active', 1)
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->orderByRaw('(version_code IS NULL), version_code DESC, published_at DESC, id DESC')
            ->first();

        if (!$latest) {
            return response()->json([
                'platform'         => $platform,
                'channel'          => $channel,
                'latest'           => null,
                'update_available' => false,
                'update_required'  => false,
            ]);
        }

        // 🔹 منطق المقارنة لتحديد وجود تحديث
        $updateAvailable = false;
        if (!is_null($currCode) && !is_null($latest->version_code)) {
            $updateAvailable = ((int)$latest->version_code > (int)$currCode);
        } elseif ($currName) {
            $updateAvailable = version_compare($latest->version_name, $currName, '>');
        } else {
            $updateAvailable = true;
        }

        // 🔹 روابط التحميل
        $downloadUrl = url("/api/mobile/app/download/{$latest->id}");
        $publicUrl   = Storage::disk('public')->url($latest->file_path);

        return response()->json([
            'platform'         => $platform,
            'channel'          => $channel,
            'latest'           => [
                'id'              => (int) $latest->id,
                'version_name'    => $latest->version_name,
                'version_code'    => $latest->version_code ? (int)$latest->version_code : null,
                'is_mandatory'    => (int)$latest->is_mandatory,
                'published_at'    => $latest->published_at,
                'file_size_bytes' => $latest->file_size_bytes ? (int)$latest->file_size_bytes : null,
                'release_notes'   => $latest->release_notes,
                'download_url'    => $downloadUrl, // يسجل التحميل
                'storage_url'     => $publicUrl,   // رابط مباشر عبر /storage/
            ],
            'update_available' => (bool) $updateAvailable,
            'update_required'  => $updateAvailable && (int)$latest->is_mandatory === 1,
        ]);
    }

    /**
     * ===============================================================
     * GET /api/mobile/app/download/{id}
     * ---------------------------------------------------------------
     * يسجّل التحميل في قاعدة البيانات ويعيد الملف للتنزيل
     * ===============================================================
     */
public function download(Request $request, int $id)
{
    $rel = DB::table('app_releases')->where('id', $id)->first();
    if (!$rel || (int)$rel->is_active !== 1) {
        return response()->json(['message' => 'Release not found'], 404);
    }

    // تأكد أن المسار نسبي ضمن قرص public
    $relative = ltrim((string)$rel->file_path, '/'); // ex: releases/android/how_diploma_v1.0.6+106.apk
    $absPath  = Storage::disk('public')->path($relative);

    if (!is_file($absPath)) {
        return response()->json([
            'message'           => 'File not found on server',
            'checked_path'      => $absPath,
            'expected_relative' => $relative,
        ], 410);
    }

    // جهّز اسم ملف وتيترات
    $ext      = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
    $suffix   = $rel->version_code ? '+' . (int)$rel->version_code : '';
    $filename = sprintf('how_diploma_v%s%s%s', $rel->version_name, $suffix, $ext ? '.'.$ext : '');
    $size     = @filesize($absPath) ?: null;

    // سجّل التحميل أولاً (حتى لو المتصفح فتح في نافذة جديدة)
    DB::transaction(function () use ($rel, $request) {
        DB::table('app_release_downloads')->insert([
            'release_id'     => $rel->id,
            'user_id'        => optional($request->user())->id,
            'ip'             => $request->ip(),
            'user_agent'     => substr((string)$request->userAgent(), 0, 255),
            'referer'        => substr((string)$request->headers->get('Referer'), 0, 255),
            'client_version' => substr((string)$request->query('client_version'), 0, 50),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
        DB::table('app_releases')->where('id', $rel->id)->update([
            'download_count' => DB::raw('download_count + 1'),
            'updated_at'     => now(),
        ]);
    });

    // تنزيل الملف
    $headers = [
        'X-Content-Type-Options' => 'nosniff',
        'Cache-Control'          => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma'                 => 'no-cache',
        'Expires'                => '0',
    ];
    if (!is_null($size)) {
        $headers['Content-Length'] = (string)$size;
    }

    return response()->download($absPath, $filename, $headers);
}


    /**
     * استخرج الامتداد من المسار
     */
    private function extFromPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return $ext ? ('.' . $ext) : '';
    }
}
