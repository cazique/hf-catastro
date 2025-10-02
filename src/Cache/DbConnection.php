<?php

declare(strict_types=1);

namespace HogarFamiliar\SistemaCatastral\Cache;

use HogarFamiliar\SistemaCatastral\Core\Config;
use PDO;
use PDOException;

/**
 * Gestiona la conexión a la base de datos (Singleton).
 *
 * Se encarga de crear y mantener una única instancia de la conexión PDO
 * para evitar múltiples conexiones durante una misma petición.
 */
class DbConnection
{
    private static ?PDO $instance = null;

    /**
     * El constructor es privado para prevenir la instanciación directa.
     */
    private function __construct() {}

    /**
     * Previene la clonación de la instancia.
     */
    private function __clone() {}

    /**
     * Previene la deserialización de la instancia.
     */
    public function __wakeup() {}

    /**
     * Obtiene la instancia única de la conexión PDO.
     *
     * @return PDO|null Retorna la instancia de PDO o null si la conexión falla.
     */
    public static function getInstance(): ?PDO
    {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    Config::dbHost(),
                    Config::dbPort(),
                    Config::dbName(),
                    Config::dbCharset()
                );

                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];

                self::$instance = new PDO($dsn, Config::dbUser(), Config::dbPassword(), $options);
            } catch (PDOException $e) {
                // En un entorno real, esto debería ser registrado en un log de errores.
                error_log('Error de conexión a la base de datos: ' . $e->getMessage());
                return null;
            }
        }
        return self::$instance;
    }
}