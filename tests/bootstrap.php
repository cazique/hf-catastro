<?php

/**
 * Fichero de arranque para el entorno de pruebas (PHPUnit).
 */

// Establecer una variable de entorno para indicar que estamos en modo de prueba.
// Esto puede ser útil para desactivar ciertas funcionalidades (como la caché real) en los tests.
$_ENV['APP_ENV'] = 'testing';

// Cargar el bootstrap principal de la aplicación.
// Sube dos niveles (de /tests a la raíz) para encontrarlo.
$bootstrap_path = dirname(__DIR__) . '/src/bootstrap.php';

if (!file_exists($bootstrap_path)) {
    echo "Error: No se encuentra el fichero de arranque en {$bootstrap_path}. Asegúrate de que las dependencias están instaladas.\n";
    exit(1);
}

require_once $bootstrap_path;