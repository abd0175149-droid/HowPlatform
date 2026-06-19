<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Response;

class ToolsSitemapController extends Controller
{
public function sitemap(): Response
{
    $today = Carbon::now('UTC')->toDateString();

    $urls = [
        [
            'loc'        => 'https://tools.howplatform.net/',
            'changefreq' => 'weekly',
            'priority'   => '1.0',
            'lastmod'    => $today,
        ],
    ];

    $xml = view('sitemaps.urlset', compact('urls'))->render();
    return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
}

}
