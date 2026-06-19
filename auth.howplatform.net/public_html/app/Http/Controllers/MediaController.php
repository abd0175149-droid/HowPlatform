<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    /**
     * عرض ملف عام من ديسك public عبر مسار موحّد /media/{path}
     * مثال: /media/avatars/abc.webp?v=1699999999
     * بارامتر اختياري: dl=1 لفرض التحميل.
     */
    public function public(Request $request, string $path): StreamedResponse
    {
        // sanitize
        $path = ltrim($path, '/');
        if ($path === '' || Str::contains($path, ['..', '\\'])) {
            abort(404);
        }

        $disk = Storage::disk('public');

        if (!$disk->exists($path)) {
            abort(404);
        }

        $mime    = $disk->mimeType($path) ?: 'application/octet-stream';
        $size    = $disk->size($path) ?: 0;
        $mtime   = $disk->lastModified($path) ?: time();
        $etag    = md5($path.'|'.$size.'|'.$mtime);

        // 304 support
        $ifNoneMatch = $request->headers->get('If-None-Match');
        $ifModified  = $request->headers->get('If-Modified-Since');

        if ($ifNoneMatch === $etag || ($ifModified && strtotime($ifModified) >= $mtime)) {
            return response()->stream(function () {}, 304, [
                'ETag'          => $etag,
                'Last-Modified' => gmdate('D, d M Y H:i:s', $mtime).' GMT',
                'Cache-Control' => 'public, max-age=604800', // 7 أيام
            ]);
        }

        $disposition = $request->boolean('dl')
            ? 'attachment; filename="'.basename($path).'"'
            : 'inline; filename="'.basename($path).'"';

        // stream file
        $stream = $disk->readStream($path);

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) fclose($stream);
        }, 200, [
            'Content-Type'   => $mime,
            'Content-Length' => (string)$size,
            'Content-Disposition' => $disposition,
            'ETag'           => $etag,
            'Last-Modified'  => gmdate('D, d M Y H:i:s', $mtime).' GMT',
            'Cache-Control'  => 'public, max-age=604800',
        ]);
    }
}
