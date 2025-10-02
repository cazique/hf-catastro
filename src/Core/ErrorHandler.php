<?php

declare(strict_types=1);

namespace HogarFamiliar\SistemaCatastral\Core;

/**
 * Gestiona los errores y las respuestas de la aplicación.
 */
class ErrorHandler
{
    /**
     * Inicializa el manejador de errores y cabeceras de seguridad.
     *
     * @param bool $isApi Indica si la respuesta debe ser JSON (API) o HTML.
     */
    public static function init(bool $isApi = true): void
    {
        // No mostrar errores de PHP en producción, pero sí registrarlos.
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        // Establecer el manejador de excepciones global.
        set_exception_handler(function (\Throwable $exception) use ($isApi) {
            // Log del error real
            error_log($exception->getMessage() . "\n" . $exception->getTraceAsString());

            // Enviar respuesta genérica
            $message = 'Ha ocurrido un error inesperado en el servidor.';
            if ($isApi) {
                self::sendJsonErrorResponse(500, 'SERVER_ERROR', $message);
            } else {
                // En un futuro, podría mostrar una página de error HTML amigable.
                http_response_code(500);
                echo "<h1>Error del Servidor</h1><p>{$message}</p>";
            }
        });

        self::setSecurityHeaders();
    }

    /**
     * Envía una respuesta de error en formato JSON y termina la ejecución.
     */
    public static function sendJsonErrorResponse(int $httpCode, string $errorCode, string $errorMessage): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => $errorCode,
            'message' => $errorMessage,
        ]);
        exit;
    }

    /**
     * Envía una respuesta de éxito en formato JSON.
     *
     * @param mixed $data
     */
    public static function sendJsonSuccessResponse($data): void
    {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    /**
     * Establece cabeceras de seguridad mínimas.
     */
    private static function setSecurityHeaders(): void
    {
        // Prevenir clickjacking
        header('X-Frame-Options: DENY');
        // Forzar el uso del tipo MIME declarado
        header('X-Content-Type-Options: nosniff');
        // Controlar la información de referer enviada
        header('Referrer-Policy: strict-origin-when-cross-origin');
        // Pequeña protección contra XSS
        header("X-XSS-Protection: 1; mode=block");
    }
}