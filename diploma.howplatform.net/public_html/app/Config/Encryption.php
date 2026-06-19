<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Encryption extends BaseConfig
{
    public string $key    = '';
    public string $driver = 'Sodium';
    public string $cipher = 'XChaCha20-Poly1305';

    public int    $blockSize       = 16;
    public string $digest          = 'SHA512';
    public bool   $rawData         = true;
    public string $encryptKeyInfo  = '';
    public string $authKeyInfo     = '';

    public function __construct()
    {
        parent::__construct();

        $envKey = getenv('APP_SECRET_KEY');

        if (str_starts_with($envKey, 'base64:')) {
            $envKey = substr($envKey, 7);
        }

        $decoded = base64_decode($envKey, true);

        if ($decoded !== false && strlen($decoded) === 32) {
            $this->key = $decoded;
        }
    }
}
