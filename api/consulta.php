<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use HogarFamiliar\SistemaCatastral\Core\ErrorHandler;
use HogarFamiliar\SistemaCatastral\Core\Config;
use HogarFamiliar\SistemaCatastral\Catastro\HttpClient;
use HogarFamiliar\SistemaCatastral\Catastro\CatastroParser;
use HogarFamiliar\SistemaCatastral\Catastro\CatastroService;
use HogarFamiliar\SistemaCatastral\Cache\CacheService;

// Inicializar el manejador de errores para respuestas API (JSON)
ErrorHandler::init(true);

// --- Seguridad y Validación ---

// 1. Solo permitir peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ErrorHandler::sendJsonErrorResponse(405, 'METHOD_NOT_ALLOWED', 'Método no permitido. Use POST.');
}

// 2. Rate Limiting por IP
if (Config::isRateLimitEnabled()) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $rateLimitDir = APP_ROOT . '/cache/ratelimit';
    if (!is_dir($rateLimitDir)) {
        mkdir($rateLimitDir, 0755, true);
    }

    $ipFile = $rateLimitDir . '/' . md5($ip);
    $limitData = ['count' => 1, 'timestamp' => time()];

    if (file_exists($ipFile)) {
        $data = json_decode(file_get_contents($ipFile), true);
        if ($data && (time() - $data['timestamp']) < Config::rateLimitPeriod()) {
            if ($data['count'] >= Config::rateLimitMaxRequests()) {
                ErrorHandler::sendJsonErrorResponse(429, 'TOO_MANY_REQUESTS', 'Límite de peticiones excedido. Inténtelo más tarde.');
            }
            $limitData['count'] = $data['count'] + 1;
        }
    }
    file_put_contents($ipFile, json_encode($limitData));
}


// 3. Obtener y sanitizar la Referencia Catastral
$input = json_decode(file_get_contents('php://input'), true);
$rc = $input['rc'] ?? '';
$rc = trim(strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $rc)));

if (empty($rc)) {
    ErrorHandler::sendJsonErrorResponse(400, 'INVALID_INPUT', 'La Referencia Catastral (rc) es obligatoria.');
}
if (strlen($rc) < 14 || strlen($rc) > 20) {
    ErrorHandler::sendJsonErrorResponse(400, 'INVALID_INPUT', 'La Referencia Catastral debe tener entre 14 y 20 caracteres.');
}

// --- Lógica de la Aplicación ---

try {
    // Instanciar dependencias
    $httpClient = new HttpClient();
    $parser = new CatastroParser();
    $cache = new CacheService();
    $catastroService = new CatastroService($httpClient, $parser, $cache);

    // Realizar la consulta
    $resultado = $catastroService->consultarPorRC($rc);

    // Enviar la respuesta
    if ($resultado['success']) {
        ErrorHandler::sendJsonSuccessResponse($resultado);
    } else {
        ErrorHandler::sendJsonErrorResponse(404, 'NOT_FOUND', $resultado['error'] ?? 'No se encontraron datos para la referencia catastral proporcionada.');
    }

} catch (\Exception $e) {
    // Error inesperado capturado por el manejador de excepciones global, pero por si acaso.
    ErrorHandler::sendJsonErrorResponse(500, 'SERVER_ERROR', 'Ha ocurrido un error inesperado en el servidor.');
}