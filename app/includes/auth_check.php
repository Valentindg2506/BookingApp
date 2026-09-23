<?php

/**
 * auth_check.php
 *
 * Incluir al inicio de cualquier página protegida del panel admin.
 * Inicia la sesión con el nombre definido en SESSION_NAME y redirige
 * al login si el usuario no está autenticado.
 *
 * Funciona correctamente tanto si se incluye desde /admin/ como desde
 * cualquier otro subdirectorio del proyecto.
 */

// Cargar configuración si aún no está cargada
if (!defined('SESSION_NAME')) {
    require_once __DIR__ . '/../config.php';
}

// Configurar y arrancar la sesión (solo si no está ya activa)
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Verificar autenticación
if (empty($_SESSION['admin_id'])) {
    // Construir la ruta al login de forma relativa a la raíz del proyecto
    // __DIR__ apunta a /…/Proyecto Nuevo/includes
    $loginPath = rtrim(dirname(__DIR__), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'index.php';

    // Determinar la URL de redirección en función de APP_URL
    if (defined('APP_URL')) {
        $loginUrl = rtrim(APP_URL, '/') . '/admin/index.php';
    } else {
        // Fallback: ruta relativa si APP_URL no está disponible
        $depth   = substr_count(str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']), '/');
        $loginUrl = '../admin/index.php';
    }

    header('Location: ' . $loginUrl);
    exit;
}
