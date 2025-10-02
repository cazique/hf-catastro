<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use HogarFamiliar\SistemaCatastral\Core\ErrorHandler;
use HogarFamiliar\SistemaCatastral\Catastro\HttpClient;
use HogarFamiliar\SistemaCatastral\Catastro\CatastroParser;
use HogarFamiliar\SistemaCatastral\Catastro\CatastroService;
use HogarFamiliar\SistemaCatastral\Cache\CacheService;
use HogarFamiliar\SistemaCatastral\Pdf\PdfGenerator;

// Inicializar manejador de errores para HTML
ErrorHandler::init(false);

// --- Seguridad y Validación ---

// 1. Solo permitir peticiones GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die('<h1>Error 405: Método no permitido</h1><p>Use GET para solicitar el PDF.</p>');
}

// 2. Obtener y sanitizar la Referencia Catastral
$rc = $_GET['rc'] ?? '';
$rc = trim(strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $rc)));

if (empty($rc)) {
    http_response_code(400);
    die('<h1>Error 400: Petición incorrecta</h1><p>La Referencia Catastral (rc) es obligatoria.</p>');
}
if (strlen($rc) < 14 || strlen($rc) > 20) {
    http_response_code(400);
    die('<h1>Error 400: Petición incorrecta</h1><p>La Referencia Catastral proporcionada no es válida.</p>');
}

// --- Lógica de la Aplicación ---

try {
    // Verificar si Dompdf está disponible
    if (!class_exists('Dompdf\Dompdf')) {
        http_response_code(501);
        die('<h1>Error 501: Funcionalidad no disponible</h1><p>La librería para generar PDF (Dompdf) no está instalada. Por favor, ejecute <code>composer install</code>.</p><p>Como alternativa, puede ver los datos en la <a href="../frontend/mapa.php">interfaz principal</a>.</p>');
    }

    // Instanciar dependencias
    $httpClient = new HttpClient();
    $parser = new CatastroParser();
    $cache = new CacheService();
    $catastroService = new CatastroService($httpClient, $parser, $cache);

    // Realizar la consulta
    $resultado = $catastroService->consultarPorRC($rc);

    // Generar el PDF si hay éxito
    if ($resultado['success'] && !empty($resultado['data'])) {
        // Usamos el primer inmueble encontrado para el informe
        $datosInmueble = $resultado['data'][0];

        $pdfGenerator = new PdfGenerator($datosInmueble, $rc);
        $pdfGenerator->streamPdf();
        exit;
    } else {
        // Mostrar un error amigable si no se encontraron datos
        http_response_code(404);
        $errorMsg = htmlspecialchars($resultado['error'] ?? 'No se encontraron datos para la referencia catastral proporcionada.');
        die("<h1>Error 404: No se encontraron datos</h1><p>No se pudo generar el PDF porque no se encontraron datos para la RC <strong>{$rc}</strong>.</p><p>Motivo: {$errorMsg}</p>");
    }

} catch (\Exception $e) {
    // El manejador de errores global se encargará de loguear el error.
    // Mostramos un mensaje genérico al usuario.
    http_response_code(500);
    die('<h1>Error 500: Error del servidor</h1><p>Ha ocurrido un error inesperado al intentar generar el PDF.</p>');
}