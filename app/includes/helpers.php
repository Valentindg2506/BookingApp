<?php

/**
 * helpers.php
 *
 * Colección de funciones de utilidad reutilizables en toda la aplicación.
 */

if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config.php';
}

// ============================================================
//  Sanitización y validación
// ============================================================

/**
 * Limpia un string eliminando espacios y escapando HTML.
 *
 * @param  mixed $input
 * @return string
 */
function sanitize(mixed $input): string
{
    return htmlspecialchars(trim((string) $input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Valida un número de teléfono de forma básica (solo dígitos, +, espacios y guiones).
 *
 * @param  string $phone
 * @return bool
 */
function isValidPhone(string $phone): bool
{
    // Acepta formato internacional (+34 600 000 000) y nacional (600000000)
    return (bool) preg_match('/^\+?[\d\s\-]{7,20}$/', trim($phone));
}

/**
 * Valida una dirección de correo electrónico.
 *
 * @param  string $email
 * @return bool
 */
function isValidEmail(string $email): bool
{
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
}

// ============================================================
//  Formato de fechas y horas
// ============================================================

/**
 * Formatea una fecha en el formato indicado.
 *
 * @param  string $date   Fecha en formato Y-m-d u otro reconocible por strtotime.
 * @param  string $format Formato de salida (por defecto d/m/Y).
 * @return string
 */
function formatDate(string $date, string $format = 'd/m/Y'): string
{
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }
    return date($format, $timestamp);
}

/**
 * Formatea una hora en formato H:i.
 *
 * @param  string $time Hora en formato H:i:s o H:i.
 * @return string
 */
function formatTime(string $time): string
{
    $timestamp = strtotime($time);
    if ($timestamp === false) {
        return $time;
    }
    return date('H:i', $timestamp);
}

/**
 * Combina y formatea una fecha y una hora.
 *
 * @param  string $date Fecha en formato Y-m-d.
 * @param  string $time Hora en formato H:i:s o H:i.
 * @return string  Ejemplo: "15/06/2025 a las 10:30"
 */
function formatDateTime(string $date, string $time): string
{
    return formatDate($date) . ' a las ' . formatTime($time);
}

// ============================================================
//  Nombres de días y meses en español
// ============================================================

/**
 * Retorna el nombre del día de la semana en español.
 *
 * @param  int $dayNumber  0 = Domingo … 6 = Sábado (convención date('w')).
 * @return string
 */
function getDayName(int $dayNumber): string
{
    $days = [
        0 => 'Domingo',
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
    ];

    return $days[$dayNumber] ?? '';
}

/**
 * Retorna el nombre del mes en español.
 *
 * @param  int $monthNumber  1 = Enero … 12 = Diciembre.
 * @return string
 */
function getMonthName(int $monthNumber): string
{
    $months = [
        1  => 'Enero',
        2  => 'Febrero',
        3  => 'Marzo',
        4  => 'Abril',
        5  => 'Mayo',
        6  => 'Junio',
        7  => 'Julio',
        8  => 'Agosto',
        9  => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    return $months[$monthNumber] ?? '';
}

// ============================================================
//  Teléfono / WhatsApp
// ============================================================

/**
 * Normaliza un número de teléfono al formato internacional para WhatsApp.
 *
 * Reglas aplicadas (España):
 *   - Si empieza por 6 o 7 (móvil español, 9 dígitos) → añade +34
 *   - Si empieza por 9 (fijo español, 9 dígitos)       → añade +34
 *   - Si ya lleva + delante                             → lo deja tal cual
 *   - Si empieza por 0034                               → reemplaza por +34
 *
 * @param  string $phone
 * @return string  Número en formato +XXXXXXXXXXX
 */
function formatPhoneForWhatsApp(string $phone): string
{
    // Eliminar espacios, guiones y paréntesis
    $phone = preg_replace('/[\s\-\(\)]/', '', trim($phone));

    // Ya tiene prefijo internacional correcto
    if (str_starts_with($phone, '+')) {
        return $phone;
    }

    // Prefijo 0034 (España) → +34
    if (str_starts_with($phone, '0034')) {
        return '+34' . substr($phone, 4);
    }

    // Número español sin prefijo (9 dígitos empezando por 6, 7 o 9)
    if (preg_match('/^[679]\d{8}$/', $phone)) {
        return '+34' . $phone;
    }

    // Por defecto, añadir + (asume que ya incluye código de país sin el +)
    return '+' . $phone;
}

// ============================================================
//  Generación de tokens
// ============================================================

/**
 * Genera un token hexadecimal aleatorio criptográficamente seguro.
 *
 * @param  int $length Longitud en bytes (el hex resultante tendrá el doble de caracteres).
 * @return string
 */
function generateToken(int $length = 32): string
{
    return bin2hex(random_bytes($length));
}

// ============================================================
//  CSRF
// ============================================================

/**
 * Retorna el token CSRF de la sesión actual, generándolo si no existe.
 * Requiere que la sesión esté activa.
 *
 * @return string
 */
function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(defined('SESSION_NAME') ? SESSION_NAME : 'booking_session');
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken(32);
    }

    return $_SESSION['csrf_token'];
}

/**
 * Verifica que el token CSRF recibido coincida con el de la sesión.
 *
 * @param  string|null $token Token enviado por el formulario.
 * @return bool
 */
function csrf_verify(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================================
//  HTTP helpers
// ============================================================

/**
 * Envía una respuesta JSON con el código HTTP indicado y finaliza la ejecución.
 *
 * @param  mixed $data       Datos a serializar como JSON.
 * @param  int   $statusCode Código de estado HTTP (por defecto 200).
 * @return never
 */
function jsonResponse(mixed $data, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Redirige a la URL indicada y finaliza la ejecución.
 *
 * @param  string $url URL de destino (absoluta o relativa).
 * @return never
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}
