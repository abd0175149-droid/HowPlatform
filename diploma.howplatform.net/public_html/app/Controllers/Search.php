<?php
namespace App\Controllers;

class Search extends BaseController
{
    public function index()
    {
        $q = trim((string) $this->request->getGet('q'));
        if ($q === '') {
            return redirect()->to(site_url('/'));
        }
        // مبدئيًا: إعادة توجيه إلى بحث جوجل المقيّد بالموقع
        return redirect()->to('https://www.google.com/search?q=site:' . parse_url(site_url('/'), PHP_URL_HOST) . '+' . urlencode($q));
    }
}
