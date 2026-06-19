<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    // عرض صورة/ملف من sales_points عبر HTTP (inline)
    public function salesPoint(Request $request, string $filename)
    {
        // امنع الاختراق عبر المسارات
        $safe = basename($filename);
        $path = "sales_points/{$safe}";

        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }

        // كاش فعّال
        $last = Storage::disk('public')->lastModified($path);
        $size = Storage::disk('public')->size($path);
        $etag = md5($path.'|'.$last.'|'.$size);

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304, [
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        return Storage::disk('public')->response($path, null, [
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
    public function downloadSalesPoint(string $filename)
    {
    $safe = basename($filename);
    $path = "sales_points/{$safe}";
    abort_unless(Storage::disk('public')->exists($path), 404);

    return Storage::disk('public')->download($path);
}

}
