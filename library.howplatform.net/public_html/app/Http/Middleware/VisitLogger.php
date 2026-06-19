<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // سنستخدم DB::table مباشرة
use Illuminate\Support\Str;

class VisitLogger
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // ✅ سجّل فقط الصفحة الرئيسية: route name = library.index
        $routeName = $request->route()?->getName() ?? '';
        if ($routeName !== 'library.index') {
            return $response;
        }

        // منع الروبوتات الشائعة (اختياري)
        $ua = (string) $request->userAgent();
        $uaL = Str::lower($ua);
        foreach (['bot','crawler','spider','slurp','bingpreview','facebookexternalhit','curl','wget','python-requests','headless','phantom','cf-network'] as $sig) {
            if (Str::contains($uaL, $sig)) {
                return $response;
            }
        }

        // Debounce بسيط: مرّة واحدة في اليوم لكل جلسة
        $sid = $request->session()->getId();               // يحتاج web middleware (موجود تلقائيًا في web.php)
        $sessionKey = 'home_visit_'.date('Ymd');
        if (!$request->session()->has($sessionKey)) {
            try {
                DB::table('visits')->insert([
                    'path'       => '/',                                       // الصفحة الرئيسية فقط
                    'session_id' => $sid,
                    'ip'         => $request->ip(),
                    'referrer'   => substr((string) $request->headers->get('referer'), 0, 300),
                    'user_agent' => substr($ua, 0, 500),                       // انتبه: 0, 500
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $request->session()->put($sessionKey, now()->toIso8601String());
            } catch (\Throwable $e) {
                \Log::warning('VisitLogger insert failed', ['err' => $e->getMessage()]);
            }
        }

        return $response;
    }
}
