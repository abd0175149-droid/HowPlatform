<?php

namespace App\Services;

use Exception;

class CryptoService
{
    /**
     * The 32-byte encryption key (loaded from .env)
     */
    private string $key;

    public function __construct()
    {
        $envKey = getenv('APP_SECRET_KEY');

        if (str_starts_with($envKey, 'base64:')) {
            $envKey = substr($envKey, 7);
        }

        $decoded = base64_decode($envKey, true);

        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new Exception('Invalid encryption key.');
        }

        $this->key = $decoded;
    }

    /**
     * Encrypts the payload and returns nonce + ciphertext (base64 encoded)
     *
     * @param array $payload
     * @return array
     */
    public function encrypt(array $payload): array
    {
        $plaintext = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $nonce     = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES); // 24 bytes

        $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            '',        // Associated Data (AAD)
            $nonce,
            $this->key
        );

        return [
            'ciphertext' => base64_encode($cipher),
            'nonce'      => base64_encode($nonce),
            'tag'        => null  // tag مدمج ضمن التشفير في sodium
        ];
    }

    /**
     * Decrypts the payload using ciphertext and nonce
     *
     * @param array $cipher
     * @return array
     * @throws Exception
     */
    public function decrypt(array $cipher): array
    {
        if (!isset($cipher['ciphertext'], $cipher['nonce'])) {
            throw new Exception('Invalid cipher input.');
        }

        $ciphertext = base64_decode($cipher['ciphertext']);
        $nonce      = base64_decode($cipher['nonce']);

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $ciphertext,
            '',
            $nonce,
            $this->key
        );

        if ($plaintext === false) {
            throw new Exception('Decryption failed.');
        }

        return json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
    }
}
