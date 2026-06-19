<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class CheckSodium extends Controller
{
    public function index()
    {
        $available = extension_loaded('sodium');

        return $this->response->setJSON([
            'sodium_installed' => $available,
            'message' => $available
                ? '✅ مكتبة sodium مفعّلة وجاهزة.'
                : '❌ مكتبة sodium غير مفعّلة في PHP.'
        ]);
    }
}
