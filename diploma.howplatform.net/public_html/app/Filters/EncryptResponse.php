<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class EncryptResponse implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // لا شيء هنا
    }

public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
{
    file_put_contents(WRITEPATH . 'logs/response_filter_test.txt', '✅ EncryptResponse executed at ' . date('Y-m-d H:i:s'));

    if (stripos($response->getHeaderLine('Content-Type'), 'json') === false) {
        return $response;
    }

    $data = json_decode($response->getBody(), true);

    if (is_array($data)) {
        $crypto    = service('crypto');
        $encrypted = $crypto->encrypt($data);
        return $response->setJSON($encrypted);
    }

    return $response;
}

}
