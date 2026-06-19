<?php
namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class Sitemap extends BaseController
{
    public function index(): ResponseInterface
    {
        $urls = [
            ['loc'=>site_url('/'),                'changefreq'=>'weekly',  'priority'=>'1.0'],
            ['loc'=>site_url('auth/login'),       'changefreq'=>'monthly', 'priority'=>'0.6'],
            ['loc'=>site_url('auth/register'),    'changefreq'=>'monthly', 'priority'=>'0.6'],
            ['loc'=>site_url('auth/faq'),         'changefreq'=>'monthly', 'priority'=>'0.7'],
            ['loc'=>site_url('auth/privacy'),     'changefreq'=>'yearly',  'priority'=>'0.4'],
            ['loc'=>site_url('auth/terms'),       'changefreq'=>'yearly',  'priority'=>'0.4'],
            ['loc'=>site_url('auth/contact'),     'changefreq'=>'monthly', 'priority'=>'0.6']
        ];

        $xml = view('sitemap/xml', ['urls' => $urls]);
        return $this->response->setHeader('Content-Type','application/xml; charset=UTF-8')
                              ->setBody($xml);
    }
}
