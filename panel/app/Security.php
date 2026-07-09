<?php

final class Security
{
    public static function siteName(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $value) === 1;
    }

    public static function domain(string $value): bool
    {
        return preg_match('/^([A-Za-z0-9-]+\.)+[A-Za-z]{2,}$/', $value) === 1;
    }

    public static function ip(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    public static function publicIp(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    public static function domainOrPublicIp(string $value): bool
    {
        if (self::ip($value)) {
            return self::publicIp($value);
        }
        return self::domain($value);
    }

    public static function dbName(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9_]{1,64}$/', $value) === 1;
    }

    public static function dbPassword(string $value): bool
    {
        return strlen($value) >= 8 && strlen($value) <= 128 && !preg_match('/[\'"`\\\r\n]/', $value);
    }

    public static function port(int|string $value): bool
    {
        if (!preg_match('/^[0-9]+$/', (string)$value)) {
            return false;
        }
        $port = (int)$value;
        return $port >= 1 && $port <= 65535;
    }

    public static function rewriteMode(string $value): bool
    {
        return in_array($value, ['preset', 'custom'], true);
    }

    public static function customRewrite(string $value): bool
    {
        if (strlen($value) > 16 * 1024) {
            return false;
        }
        if (preg_match('/[{}]/', $value)) {
            return false;
        }
        return !preg_match('/\b(server|listen|location|root|alias|include|ssl_certificate|ssl_certificate_key)\b/i', $value);
    }
}
