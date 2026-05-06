<?php

// ============================================================
//  BookingApp - Configuración global
//  Copia este archivo como config.php y ajusta los valores.
//  NO subas este archivo a control de versiones con datos reales.
// ============================================================

// ------------------------------------------------------------
//  Base de datos (MySQL / MariaDB)
// ------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'booking_app');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);

// ------------------------------------------------------------
//  Aplicación
// ------------------------------------------------------------
define('APP_NAME',     'BookingApp');
define('APP_URL',      'http://localhost/booking-app');
define('APP_TIMEZONE', 'Europe/Madrid');

// ------------------------------------------------------------
//  Meta / Facebook Ads Webhooks
// ------------------------------------------------------------
define('META_VERIFY_TOKEN',      'tu_token_de_verificacion_aqui');
define('META_APP_SECRET',        'tu_app_secret_aqui');
define('META_PAGE_ACCESS_TOKEN', 'tu_page_access_token_aqui');

// ------------------------------------------------------------
//  Twilio - WhatsApp
// ------------------------------------------------------------
define('TWILIO_ACCOUNT_SID',    'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('TWILIO_AUTH_TOKEN',     'tu_auth_token_aqui');
define('TWILIO_WHATSAPP_FROM',  'whatsapp:+14155238886'); // Twilio Sandbox

// ------------------------------------------------------------
//  Google Calendar / Meet API (OAuth 2.0)
// ------------------------------------------------------------
define('GOOGLE_CLIENT_ID',      'tu_client_id.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET',  'tu_client_secret');
define('GOOGLE_REDIRECT_URI',   APP_URL . '/admin/google_callback.php');
define('GOOGLE_CALENDAR_ID',    'primary');
define('GOOGLE_TOKENS_FILE',    __DIR__ . '/google_tokens.json');

// ------------------------------------------------------------
//  Información del negocio
// ------------------------------------------------------------
define('BUSINESS_NAME',    'Tu Empresa');
define('BUSINESS_PHONE',   '+34600000000');
define('ADMIN_WHATSAPP',   'whatsapp:+34600000000'); // Recibe copias de notificaciones

// ------------------------------------------------------------
//  Sesión
// ------------------------------------------------------------
define('SESSION_NAME', 'booking_admin_session');

// ------------------------------------------------------------
//  Zona horaria global
// ------------------------------------------------------------
date_default_timezone_set(APP_TIMEZONE);
