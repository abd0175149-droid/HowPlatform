<?php

namespace App\Controllers;

class AdsController extends BaseController
{
    public function index()
    {
        $content = "google.com, pub-3572625518315562, DIRECT, f08c47fec0942fa0";

        return $this->response
                    ->setContentType('text/plain')
                    ->setBody($content);
    }
}
