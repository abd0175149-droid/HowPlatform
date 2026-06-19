<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Response;

class LibrarySitemapController extends Controller
{
public function sitemap(): Response
{
    $today = Carbon::now('UTC')->toDateString();

    $urls = [
        [
            'loc'        => 'https://library.howplatform.net/',
            'changefreq' => 'weekly',
            'priority'   => '1.0',
            'lastmod'    => $today,
        ],
    ];

    // توليد الصفوف من 0 إلى 12
    for ($i = 0; $i <= 12; $i++) {
        $urls[] = [
            'loc'        => "https://library.howplatform.net/library/grade-$i",
            'changefreq' => 'weekly',
            'priority'   => '0.8',
            'lastmod'    => $today,
        ];
    }

    $xml = view('sitemaps.urlset', compact('urls'))->render();
    return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
}

}
