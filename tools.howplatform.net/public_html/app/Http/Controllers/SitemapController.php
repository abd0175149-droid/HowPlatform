<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Tool;
use App\Models\Category;

class SitemapController extends Controller
{
    public function index()
    {
        $today = Carbon::now('UTC')->toDateString();

        $urls = [
            ['loc' => route('tools.index'), 'lastmod' => $today, 'changefreq' => 'daily',  'priority' => '1.0'],
        ];

        // صفحات تفاصيل كل أداة مفعّلة
        Tool::where('is_active', 1)
            ->orderByDesc('created_at')
            ->get(['slug', 'updated_at'])
            ->each(function ($t) use (&$urls, $today) {
                $urls[] = [
                    'loc'        => route('tools.show', $t->slug),
                    'lastmod'    => optional($t->updated_at)->toDateString() ?: $today,
                    'changefreq' => 'weekly',
                    'priority'   => '0.8',
                ];
            });

        // صفحات التصنيفات (تبويبات)
        Category::orderBy('name')->get(['slug'])->each(function ($c) use (&$urls, $today) {
            $urls[] = [
                'loc'        => route('tools.index', ['tab' => $c->slug]),
                'lastmod'    => $today,
                'changefreq' => 'weekly',
                'priority'   => '0.6',
            ];
        });

        $xml = view('sitemaps.urlset', compact('urls'))->render();
        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
