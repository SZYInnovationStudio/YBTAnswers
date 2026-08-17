<?php

declare(strict_types=1);

final class Crypto
{
    private const CIPHER = 'aes-256-cbc';

    private static function secretKey(): string
    {
        $keyFile = __DIR__ . '/secret.key';
        if (!is_file($keyFile)) {
            throw new RuntimeException('加密密钥文件缺失，无法解密敏感配置。');
        }
        $key = trim((string) file_get_contents($keyFile));
        if (strlen($key) < 32) {
            throw new RuntimeException('加密密钥无效。');
        }
        return hash('sha256', $key, true);
    }

    public static function generateKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $cipher = openssl_encrypt($plain, self::CIPHER, self::secretKey(), OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new RuntimeException('加密失败。');
        }
        return base64_encode($iv . $cipher);
    }

    public static function decrypt(string $payload): string
    {
        if ($payload === '') {
            return '';
        }
        $raw = base64_decode($payload, true);
        if ($raw === false) {
            return '';
        }
        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        if (strlen($raw) <= $ivLen) {
            return '';
        }
        $iv = substr($raw, 0, $ivLen);
        $cipher = substr($raw, $ivLen);
        $plain = openssl_decrypt($cipher, self::CIPHER, self::secretKey(), OPENSSL_RAW_DATA, $iv);
        return $plain === false ? '' : $plain;
    }

    public static function mask(string $value): string
    {
        $len = strlen($value);
        if ($len <= 8) {
            return str_repeat('*', max(4, $len));
        }
        return substr($value, 0, 4) . str_repeat('*', 6) . substr($value, -4);
    }
}
