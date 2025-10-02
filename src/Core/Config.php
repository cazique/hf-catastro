<?php

declare(strict_types=1);

namespace HogarFamiliar\SistemaCatastral\Core;

/**
 * Gestiona la configuración de la aplicación.
 *
 * Lee valores de las variables de entorno (`.env`) y proporciona
 * valores por defecto seguros si no están definidas.
 */
class Config
{
    /**
     * Obtiene un valor de configuración de tipo string.
     */
    public static function get(string $key, string $default = ''): string
    {
        return $_ENV[$key] ?? $default;
    }

    /**
     * Obtiene un valor de configuración de tipo booleano.
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = strtolower(self::get($key));
        return in_array($value, ['true', '1', 'on', 'yes'], true) ? true : $default;
    }

    /**
     * Obtiene un valor de configuración de tipo entero.
     */
    public static function getInt(string $key, int $default = 0): int
    {
        $value = self::get($key);
        return filter_var($value, FILTER_VALIDATE_INT) ?: $default;
    }

    // --- Métodos específicos para la aplicación ---

    public static function appUrl(): string
    {
        return self::get('APP_URL', 'http://localhost');
    }

    public static function httpUserAgent(): string
    {
        return self::get('HTTP_USER_AGENT', 'SistemaCatastralHogarFamiliar/1.0');
    }

    public static function httpConnectTimeout(): int
    {
        return self::getInt('HTTP_CONNECT_TIMEOUT', 10);
    }

    public static function httpTimeout(): int
    {
        return self::getInt('HTTP_TIMEOUT', 30);
    }

    public static function httpVerifySsl(): bool
    {
        // Por seguridad, el valor por defecto siempre debe ser true.
        return self::getBool('HTTP_VERIFY_SSL', true);
    }

    public static function isCacheEnabled(): bool
    {
        return self::getBool('CACHE_DB_ENABLED', false);
    }

    public static function cacheTtl(): int
    {
        return self::getInt('CACHE_TTL_SECONDS', 2592000); // 30 días
    }

    public static function dbHost(): string
    {
        return self::get('DB_HOST', '127.0.0.1');
    }

    public static function dbPort(): int
    {
        return self::getInt('DB_PORT', 3306);
    }

    public static function dbName(): string
    {
        return self::get('DB_DATABASE', 'catastro_cache');
    }

    public static function dbUser(): string
    {
        return self::get('DB_USERNAME', 'root');
    }

    public static function dbPassword(): string
    {
        return self::get('DB_PASSWORD', '');
    }

    public static function dbCharset(): string
    {
        return self::get('DB_CHARSET', 'utf8mb4');
    }

    public static function isRateLimitEnabled(): bool
    {
        return self::getBool('RATE_LIMIT_ENABLED', true);
    }

    public static function rateLimitMaxRequests(): int
    {
        return self::getInt('RATE_LIMIT_MAX_REQUESTS', 100);
    }

    public static function rateLimitPeriod(): int
    {
        return self::getInt('RATE_LIMIT_PERIOD_SECONDS', 3600);
    }
}