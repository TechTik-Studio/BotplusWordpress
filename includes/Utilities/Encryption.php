<?php

namespace BotPlus\Plugin\Utilities;


// Prevent direct script execution
if (!defined('ABSPATH')) {
    exit;
}

class Encryption
{
    protected static function getKey(): string
    {
        // Use WordPress secret keys as encryption key base
        $salt = defined('AUTH_KEY') ? AUTH_KEY : 'botplus-default-salt';
        return hash('sha256', $salt . 'botplus-encryption', true);
    }

    protected static function getIv(): string
    {
        return substr(hash('sha256', defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'botplus-iv-salt', true), 0, 16);
    }

    public static function encrypt(string $data): string
    {
        if (empty($data)) {
            return '';
        }

        $key = self::getKey();
        $iv = function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16);

        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new \Exception('Encryption failed');
        }

        // Prepend IV for storage (IV doesn't need to be secret)
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt(string $encryptedData): string
    {
        if (empty($encryptedData)) {
            return '';
        }

        $key = self::getKey();
        $data = base64_decode($encryptedData);

        if ($data === false || strlen($data) < 16) {
            return '';
        }

        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            return '';
        }

        return $decrypted;
    }
}
