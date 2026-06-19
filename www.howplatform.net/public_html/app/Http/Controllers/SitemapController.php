<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

class SitemapController extends Controller
{
    /** فهرس الخرائط: يشير للمواقع الفرعية + خريطة الموقع الرئيسي */
    public function sitemapIndex(): Response
    {
        // ملاحظة: استخدم https و www للمجال الرئيسي
        $items = [
            [
                'loc'     => 'https://www.howplatform.net/sitemap-main.xml',
                'lastmod' => Carbon::now('UTC')->toAtomString(),
            ],
            [
                'loc'     => 'https://diploma.howplatform.net/sitemap.xml',
                'lastmod' => Carbon::now('UTC')->toAtomString(),
            ],
            [
                'loc'     => 'https://library.howplatform.net/sitemap.xml',
                'lastmod' => Carbon::now('UTC')->toAtomString(),
            ],
        ];

        $xml = view('sitemaps.index', compact('items'))->render();

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /** خريطة صفحات الموقع الرئيسي */
    public function mainSitemap(): Response
    {
        // حدّث هذه القائمة حسب صفحات موقعك العامة فقط (بدون صفحات تسجيل/دخول)
        $urls = [
            [
                'loc'        => 'https://www.howplatform.net/',
                'changefreq' => 'weekly',
                'priority'   => '1.0',
                'lastmod'    => Carbon::parse('2025-08-24', 'UTC')->toDateString(),
            ],
            [
                'loc'        => 'https://www.howplatform.net/about',
                'changefreq' => 'monthly',
                'priority'   => '0.7',
                'lastmod'    => Carbon::parse('2025-08-24', 'UTC')->toDateString(),
            ],
            [
                'loc'        => 'https://www.howplatform.net/services',
                'changefreq' => 'monthly',
                'priority'   => '0.7',
                'lastmod'    => Carbon::parse('2025-08-24', 'UTC')->toDateString(),
            ],
            [
                'loc'        => 'https://www.howplatform.net/faq',
                'changefreq' => 'monthly',
                'priority'   => '0.6',
                'lastmod'    => Carbon::parse('2025-08-24', 'UTC')->toDateString(),
            ],
            [
                'loc'        => 'https://www.howplatform.net/contact',
                'changefreq' => 'monthly',
                'priority'   => '0.6',
                'lastmod'    => Carbon::parse('2025-08-24', 'UTC')->toDateString(),
            ],
        ];

        $xml = view('sitemaps.urlset', compact('urls'))->render();

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

}
