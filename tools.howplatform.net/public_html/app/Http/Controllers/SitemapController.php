<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\URL;

class SitemapController extends Controller
{
    public function index()
    {
        $urls = [
            ['loc' => URL::to('/'),       'priority' => '1.0'],
            ['loc' => URL::to('/tools'),  'priority' => '0.8'],
        ];

        $xml = view('sitemap.urlset', compact('urls'))->render();
        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
