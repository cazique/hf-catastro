<?php

declare(strict_types=1);

namespace HogarFamiliar\SistemaCatastral\Catastro;

use HogarFamiliar\SistemaCatastral\Core\Config;

/**
 * Cliente HTTP robusto para realizar peticiones cURL a los servicios del Catastro.
 */
class HttpClient
{
    private string $userAgent;
    private int $connectTimeout;
    private int $timeout;
    private bool $verifySsl;

    public function __construct()
    {
        $this->userAgent = Config::httpUserAgent();
        $this->connectTimeout = Config::httpConnectTimeout();
        $this->timeout = Config::httpTimeout();
        $this->verifySsl = Config::httpVerifySsl();
    }

    /**
     * Realiza una petición GET con reintentos.
     *
     * @param string $url
     * @param int $maxRetries Número máximo de reintentos para errores 5xx o 429.
     * @return array{int, string} Retorna un array con el código de estado HTTP y el cuerpo de la respuesta.
     */
    public function getWithRetries(string $url, int $maxRetries = 2): array
    {
        $attempt = 0;
        $backoff = 1; // segundos

        while ($attempt <= $maxRetries) {
            $attempt++;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySsl);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySsl ? 2 : 0);

            $responseBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                // Error de cURL (e.g., no se pudo resolver el host, timeout)
                // Lo tratamos como un error 503 para reintentar si es posible
                if ($attempt > $maxRetries) {
                    return [503, "<error><message>Error de cURL: {$curlError}</message></error>"];
                }
            } else {
                 // Petición exitosa o con código de error HTTP
                if ($httpCode < 400 || $httpCode === 404) { // 2xx, 3xx son éxito, 404 es error de datos, no de servidor
                    return [(int)$httpCode, $responseBody ?: ''];
                }
            }

            // Si es un error que justifica reintento (429, 5xx) y no hemos superado los intentos
            if ($attempt <= $maxRetries && ($httpCode === 429 || $httpCode >= 500)) {
                sleep($backoff);
                $backoff *= 2; // Backoff exponencial
                continue;
            }

            // Para otros errores (4xx no 429/404) o si se superaron los reintentos
            return [(int)$httpCode, $responseBody ?: ''];
        }

        // Esto solo se alcanzaría si algo va muy mal en el bucle
        return [500, "<error><message>Error inesperado en el cliente HTTP.</message></error>"];
    }
}