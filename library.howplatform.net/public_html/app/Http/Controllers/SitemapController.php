<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    // 🔹 فهرس خرائط يشير إلى خريطة Laravel وخريطة CI4
    public function index(): Response
    {
        $items = Cache::remember('sitemap:index', 300, function () {
            $now = now()->toRfc3339String();

            return [
                [
                    'loc'     => url('/sitemap-main.xml'),
                    'lastmod' => $now,
                ],
                [
                    'loc'     => 'https://diploma.howplatform.net/sitemap.xml',
                    // يمكنك جلب lastmod من HEAD أو اتركه الآن
                    'lastmod' => $now,
                ],
            ];
        });

        $xml = view('sitemap.index', compact('items'))->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    // 🔹 خريطة Laravel نفسها (صفحات/مقالات... عدّل وفق جداولك)
    public function main(): Response
    {
        $urls = Cache::remember('sitemap:main', 300, function () {
            $base = rtrim(url('/'), '/');
            $now  = now()->toRfc3339String();

            $items = [
                ['loc' => $base . '/',          'lastmod' => $now, 'changefreq' => 'weekly',  'priority' => '1.0'],
                ['loc' => $base . '/about',     'lastmod' => $now, 'changefreq' => 'monthly', 'priority' => '0.8'],
                ['loc' => $base . '/services',  'lastmod' => $now, 'changefreq' => 'monthly', 'priority' => '0.8'],
                ['loc' => $base . '/faq',       'lastmod' => $now, 'changefreq' => 'weekly',  'priority' => '0.7'],
                ['loc' => $base . '/contact',   'lastmod' => $now, 'changefreq' => 'yearly',  'priority' => '0.4'],
                ['loc' => $base . '/platforms', 'lastmod' => $now, 'changefreq' => 'monthly', 'priority' => '0.6'],
            ];

            // TODO: أضف روابط ديناميكية (مثال مقالات/دروس) بناءً على Eloquent
            // Post::published()->select('slug','updated_at')->chunk(100, function($chunk) use (&$items, $base) { ... });

            return $items;
        });

        $xml = view('sitemap.main', compact('urls'))->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
