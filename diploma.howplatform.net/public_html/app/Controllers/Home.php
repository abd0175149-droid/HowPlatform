<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        return view('partials/header')
             . view('home')
             . view('partials/footer');
    }
}
