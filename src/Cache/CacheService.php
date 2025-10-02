<?php

declare(strict_types=1);

namespace HogarFamiliar\SistemaCatastral\Cache;

use HogarFamiliar\SistemaCatastral\Core\Config;
use PDO;

/**
 * Gestiona la lógica de caché en la base de datos para las consultas al Catastro.
 */
class CacheService
{
    private ?PDO $pdo;
    private bool $enabled;
    private int $ttl;

    public function __construct()
    {
        $this->enabled = Config::isCacheEnabled();
        if (!$this->enabled) {
            $this->pdo = null;
            return;
        }

        $this->pdo = DbConnection::getInstance();
        $this->ttl = Config::cacheTtl();
    }

    /**
     * Comprueba si el servicio de caché está activo y funcional.
     */
    public function isEnabled(): bool
    {
        return $this->enabled && $this->pdo !== null;
    }

    /**
     * Obtiene un resultado de la caché por Referencia Catastral.
     *
     * @param string $rc
     * @return array|null Retorna los datos decodificados o null si no se encuentra o ha expirado.
     */
    public function get(string $rc): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $sql = "SELECT datos_json, fecha_actualizacion FROM consultas_catastro WHERE referencia_catastral = ?";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$rc]);
            $result = $stmt->fetch();

            if ($result) {
                $fechaActualizacion = new \DateTime($result['fecha_actualizacion']);
                $ahora = new \DateTime();

                if ($ahora->getTimestamp() - $fechaActualizacion->getTimestamp() < $this->ttl) {
                    // La caché es válida, decodificar y devolver
                    return json_decode($result['datos_json'], true);
                }
            }
        } catch (\PDOException $e) {
            error_log("Error al obtener caché para RC {$rc}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Guarda o actualiza un resultado en la caché.
     *
     * @param string $rc
     * @param array $jsonData El array de datos de la consulta.
     * @param string $xmlData El XML original (para referencia futura).
     */
    public function save(string $rc, array $jsonData, string $xmlData): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        // Asegurar que el JSON es un string UTF-8 válido
        $jsonString = json_encode($jsonData, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
        if ($jsonString === false) {
             error_log("Error al codificar JSON para RC {$rc}: " . json_last_error_msg());
             return;
        }

        $lat = $jsonData['data'][0]['lat'] ?? null;
        $lon = $jsonData['data'][0]['lon'] ?? null;

        $sql = <<<SQL
            INSERT INTO consultas_catastro (referencia_catastral, datos_xml, datos_json, lat, lon, fecha_consulta, fecha_actualizacion)
            VALUES (:rc, :xml, :json, :lat, :lon, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                datos_xml = VALUES(datos_xml),
                datos_json = VALUES(datos_json),
                lat = VALUES(lat),
                lon = VALUES(lon),
                fecha_actualizacion = NOW();
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':rc' => $rc,
                ':xml' => $xmlData,
                ':json' => $jsonString,
                ':lat' => $lat,
                ':lon' => $lon,
            ]);
        } catch (\PDOException $e) {
            error_log("Error al guardar caché para RC {$rc}: " . $e->getMessage());
        }
    }
}