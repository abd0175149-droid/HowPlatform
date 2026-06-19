<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MobileFileProxyController extends Controller
{
    /**
     * عنوان واجهة ملفات الدبلوم (CI)
     */
    private string $diplomaApiBase = 'https://diploma.howplatform.net/api/public-files';

    /**
     * POST /api/mobile/files/{id}/check
     * يتحقق من إمكانية عرض أو تحميل الملف بناءً على سياسة FileAccessPolicy
     */
    public function check(Request $request, int $id)
    {
        $user = $request->user();

        $resp = Http::withHeaders(['X-Auth-Gateway' => 'how-mobile'])
            ->asForm()
            ->post($this->diplomaApiBase . '/access-check', [
                'user_id' => $user->id,
                'file_id' => $id,
                'action'  => $request->query('action', 'view'),
            ]);

        return response($resp->body(), $resp->status())
            ->withHeaders(['Content-Type' => $resp->header('Content-Type')]);
    }

    /**
     * GET /api/mobile/files/{id}/view
     * عرض مباشر للملف (بث/مشاهدة) بعد تحقق FileAccessPolicy في الدبلوم
     */
    public function view(Request $request, int $id)
    {
        $user = $request->user();

        // تحقق من السياسة أولًا
        $policyResp = Http::withHeaders(['X-Auth-Gateway' => 'how-mobile'])
            ->asForm()
            ->post($this->diplomaApiBase . '/access-check', [
                'user_id' => $user->id,
                'file_id' => $id,
                'action'  => 'view',
            ]);

        $data = $policyResp->json();
        if ($policyResp->ok() && ($data['allowed'] ?? false)) {
            // نرسل الطلب للعرض من دبلوم مع نفس الهيدر الآمن
            $stream = Http::withHeaders(['X-Auth-Gateway' => 'how-mobile'])
                ->get($this->diplomaApiBase . "/{$id}/view");

            return response($stream->body(), $stream->status())
                ->withHeaders($this->filterHeaders($stream->headers()));
        }

        return response()->json([
            'status' => 'fail',
            'message' => 'عرض الملف غير مسموح',
            'details' => $data ?? [],
        ], 403);
    }

    /**
     * GET /api/mobile/files/{id}/download
     * تنزيل الملف بعد تحقق FileAccessPolicy في الدبلوم
     */
    public function download(Request $request, int $id)
    {
        $user = $request->user();

        // تحقق من السياسة أولًا
        $policyResp = Http::withHeaders(['X-Auth-Gateway' => 'how-mobile'])
            ->asForm()
            ->post($this->diplomaApiBase . '/access-check', [
                'user_id' => $user->id,
                'file_id' => $id,
                'action'  => 'download',
            ]);

        $data = $policyResp->json();
        if ($policyResp->ok() && ($data['allowed'] ?? false)) {
            // تحميل فعلي من منصة الدبلوم مع الهيدر الآمن
            $stream = Http::withHeaders(['X-Auth-Gateway' => 'how-mobile'])
                ->get($this->diplomaApiBase . "/{$id}/download");

            return response($stream->body(), $stream->status())
                ->withHeaders($this->filterHeaders($stream->headers()));
        }

        return response()->json([
            'status' => 'fail',
            'message' => 'تنزيل الملف غير مسموح',
            'details' => $data ?? [],
        ], 403);
    }

    /**
     * فلترة الهيدرز من ردّ دبلوم (نُعيد فقط المفيد منها)
     */
    private function filterHeaders(array $headers): array
    {
        $allowed = ['Content-Type', 'Content-Disposition', 'Accept-Ranges', 'Content-Length', 'Content-Range'];
        $clean = [];
        foreach ($headers as $key => $values) {
            if (in_array($key, $allowed)) {
                $clean[$key] = is_array($values) ? $values[0] : $values;
            }
        }
        return $clean;
    }
}
