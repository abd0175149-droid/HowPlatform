<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;

class ProfileHub
{
    public function __construct(
        private array $config = []
    ){
        $this->config = config('howplatform');
    }

    /** يجلب سكيمات الأقسام من كل المنصات المتاحة بالتوازي */
    public function fetchSections(?string $jwt): array
    {
        $platforms = $this->config['platforms'] ?? [];
        if (empty($platforms)) return [];

        $timeout = $this->config['timeout'] ?? 8;

        $responses = Http::pool(function (Pool $pool) use ($platforms, $jwt, $timeout) {
            $reqs = [];
            foreach ($platforms as $key => $p) {
                $url = rtrim($p['base_url'], '/') . $p['schema'];
                $reqs[$key] = $pool->withToken($jwt)->timeout($timeout)->acceptJson()->get($url);
            }
            return $reqs;
        });

        $sections = [];
        foreach ($platforms as $key => $p) {
            $resp = $responses[$key] ?? null;
            if (!$resp || !$resp->ok()) continue;

            $data = $resp->json();
            if (!is_array($data) || empty($data['fields'])) continue;

            $sections[] = [
                'key'    => $key,
                'title'  => $data['title'] ?? ($p['name'] ?? ucfirst($key)),
                'fields' => $data['fields'],
                'save'   => rtrim($p['base_url'], '/') . ($data['save_endpoint'] ?? $p['save']),
            ];
        }
        return $sections;
    }

    /** يمرّر الحفظ لمنصة معيّنة */
    public function forwardSave(string $platformKey, array $payload, ?string $jwt): array
    {
        $p = Arr::get($this->config, "platforms.$platformKey");
        if (!$p) return ['success'=>false,'message'=>'Unknown platform'];

        $url = rtrim($p['base_url'], '/') . ($p['save'] ?? '/api/profile');
        $resp = Http::withToken($jwt)
            ->timeout($this->config['timeout'] ?? 8)
            ->acceptJson()
            ->put($url, $payload);

        if ($resp->successful()) {
            return ['success'=>true,'data'=>$resp->json()];
        }

        return [
            'success'=>false,
            'status' =>$resp->status(),
            'message'=>$resp->json('message') ?? 'Remote save failed',
            'errors' =>$resp->json('errors') ?? null,
        ];
    }
}
