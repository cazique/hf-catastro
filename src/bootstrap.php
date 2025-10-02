<?php

declare(strict_types=1);

/**
 * Fichero de arranque de la aplicación.
 *
 * Responsabilidades:
 * - Definir la ruta raíz del proyecto.
 * - Cargar el autoloader de Composer.
 * - Cargar las variables de entorno desde el fichero .env.
 */

// 1. Definir la constante con la ruta raíz del proyecto.
// __DIR__ es el directorio de este fichero (src), así que subimos un nivel.
define('APP_ROOT', dirname(__DIR__));

// 2. Cargar el autoloader de Composer.
// Si no existe, es un error fatal porque la aplicación no puede funcionar.
$autoloader = APP_ROOT . '/vendor/autoload.php';
if (!file_exists($autoloader)) {
    // Emitir un error amigable en un formato que no sea HTML
    header('Content-Type: application/json; charset=utf-8', true, 500);
    echo json_encode([
        'error' => 'FATAL_ERROR',
        'message' => 'Dependencias no encontradas. Por favor, ejecuta "composer install" en la raíz del proyecto.'
    ]);
    exit(1);
}
require_once $autoloader;

// 3. Cargar las variables de entorno desde el fichero .env.
// Dotenv cargará las variables en `$_ENV` y `$_SERVER`.
try {
    $dotenv = Dotenv\Dotenv::createImmutable(APP_ROOT);
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    // No es un error fatal si no hay .env, podríamos funcionar con valores por defecto o variables del servidor.
    // En este caso, lo dejamos pasar silenciosamente. Las clases de configuración se encargarán de los valores por defecto.
}