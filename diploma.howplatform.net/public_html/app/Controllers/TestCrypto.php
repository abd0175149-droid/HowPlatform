<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class TestCrypto extends Controller
{
    public function index()
    {    
        $crypto = service('crypto'); // استدعاء الخدمة سيحمّل المفتاح من Encryption.php
        return $this->response->setJSON([
            'message' => '✅ تم استدعاء خدمة التشفير بنجاح.'
        ]);


    }
public function encryptDemo()
{
    try {
        $crypto = service('crypto');

        $payload = [
            'success'   => true,
            'semesters' => [
                [ 'id' => '12', 'name' => 'الفصل الأول' ],
                [ 'id' => '13', 'name' => 'الفصل الثاني' ],
            ]
        ];

        return $this->response->setJSON($crypto->encrypt($payload));

    } catch (\Throwable $e) {
        return $this->response->setJSON([
            'error'   => true,
            'message' => $e->getMessage()
        ])->setStatusCode(500);
    }
}
public function decryptAndShow()
{
    try {
        echo "🔍 بدأت الدالة<br>";

        $response = file_get_contents(site_url('api/courses/meta'));
        echo "📥 البيانات وصلت: " . htmlspecialchars($response) . "<br>";

        $cipher = json_decode($response, true);
        print_r($cipher);

        $crypto     = service('crypto');
        $decrypted  = $crypto->decrypt($cipher);

        echo '<pre>';
        print_r($decrypted);
        echo '</pre>';

    } catch (\Throwable $e) {
        return $this->response->setJSON([
            'error' => true,
            'message' => $e->getMessage()
        ])->setStatusCode(500);
    }
}


}
