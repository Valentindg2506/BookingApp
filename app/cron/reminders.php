<?php
/**
 * cron/reminders.php
 * Envía recordatorios de WhatsApp automáticamente.
 *
 * Ejecutar cada 15 minutos con cron:
 *   * /15 * * * * php /ruta/al/proyecto/cron/reminders.php >> /ruta/cron.log 2>&1
 *
 * O con cron clásico:
 *   0 * * * * php /ruta/al/proyecto/cron/reminders.php
 *
 * Envía:
 *   1. Recordatorio 1 día antes  (entre 23:45h y 00:15h antes de la cita)
 *   2. Recordatorio 2 horas antes (entre 1h55m y 2h05m antes de la cita)
 */

// Evitar ejecución desde el navegador
if (PHP_SAPI !== 'cli' && !isset($_GET['cron_key'])) {
    http_response_code(403);
    exit('Acceso no permitido.');
}
if (isset($_GET['cron_key']) && $_GET['cron_key'] !== 'CAMBIA_ESTA_CLAVE_SECRETA') {
    http_response_code(403);
    exit('Clave incorrecta.');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/whatsapp.php';

$pdo = Database::getInstance()->getConnection();
$now = new DateTime('now', new DateTimeZone(APP_TIMEZONE));
$nowTs = $now->getTimestamp();

cLog('--- Inicio de ejecución: ' . $now->format('Y-m-d H:i:s') . ' ---');

$sent1Day   = 0;
$sent2Hours = 0;
$errors     = 0;

// -------------------------------------------------------
// 1. Recordatorio 1 DÍA ANTES
//    Ventana: entre 23h45m y 24h15m antes de la cita
// -------------------------------------------------------
$win1Start = date('Y-m-d H:i:s', $nowTs + (23 * 3600 + 45 * 60));
$win1End   = date('Y-m-d H:i:s', $nowTs + (24 * 3600 + 15 * 60));

$stmt1 = $pdo->prepare(
    "SELECT a.*
     FROM appointments a
     WHERE a.status = 'confirmed'
       AND a.reminder_1day_sent = 0
       AND CONCAT(a.appointment_date, ' ', a.appointment_time) >= :win_start
       AND CONCAT(a.appointment_date, ' ', a.appointment_time) <= :win_end"
);
$stmt1->execute([':win_start' => $win1Start, ':win_end' => $win1End]);
$appointments1day = $stmt1->fetchAll();

cLog('Recordatorio 1 día antes - Citas encontradas: ' . count($appointments1day));

foreach ($appointments1day as $appt) {
    $result = sendReminder($pdo, $appt, 'reminder_1day');
    if ($result) $sent1Day++;
    else         $errors++;
}

// -------------------------------------------------------
// 2. Recordatorio 2 HORAS ANTES
//    Ventana: entre 1h55m y 2h05m antes de la cita
// -------------------------------------------------------
$win2Start = date('Y-m-d H:i:s', $nowTs + (1 * 3600 + 55 * 60));
$win2End   = date('Y-m-d H:i:s', $nowTs + (2 * 3600 + 5 * 60));

$stmt2 = $pdo->prepare(
    "SELECT a.*
     FROM appointments a
     WHERE a.status = 'confirmed'
       AND a.reminder_2hours_sent = 0
       AND CONCAT(a.appointment_date, ' ', a.appointment_time) >= :win_start
       AND CONCAT(a.appointment_date, ' ', a.appointment_time) <= :win_end"
);
$stmt2->execute([':win_start' => $win2Start, ':win_end' => $win2End]);
$appointments2h = $stmt2->fetchAll();

cLog('Recordatorio 2 horas antes - Citas encontradas: ' . count($appointments2h));

foreach ($appointments2h as $appt) {
    $result = sendReminder($pdo, $appt, 'reminder_2hours');
    if ($result) $sent2Hours++;
    else         $errors++;
}

cLog("Resumen: 1día={$sent1Day} enviados | 2h={$sent2Hours} enviados | errores={$errors}");
cLog('--- Fin de ejecución ---');

// -------------------------------------------------------
// Función: envía el recordatorio y actualiza BD
// -------------------------------------------------------
function sendReminder(PDO $pdo, array $appt, string $type): bool
{
    $phoneForWa = formatPhoneForWhatsApp($appt['phone']);
    $name       = $appt['full_name'];
    $date       = $appt['appointment_date'];
    $time       = formatTime($appt['appointment_time']);
    $meetLink   = $appt['meet_link'] ?? '';
    $business   = BUSINESS_NAME;

    if ($type === 'reminder_1day') {
        $result = WhatsApp::sendReminder1Day($phoneForWa, $name, $date, $time, $business);
        $flag   = 'reminder_1day_sent';
    } else {
        $result = WhatsApp::sendReminder2Hours($phoneForWa, $name, $date, $time, $business, $meetLink);
        $flag   = 'reminder_2hours_sent';
    }

    // Registrar en notification_logs
    $stmt = $pdo->prepare(
        "INSERT INTO notification_logs
            (appointment_id, type, whatsapp_to, message_sid, status, error_message, sent_at)
         VALUES
            (:appt_id, :type, :to, :sid, :status, :error, :sent_at)"
    );
    $stmt->execute([
        ':appt_id'  => $appt['id'],
        ':type'     => $type,
        ':to'       => $phoneForWa,
        ':sid'      => $result['sid']   ?? null,
        ':status'   => $result['success'] ? 'sent' : 'failed',
        ':error'    => $result['error'] ?? null,
        ':sent_at'  => $result['success'] ? date('Y-m-d H:i:s') : null,
    ]);

    if ($result['success']) {
        // Marcar como enviado en appointments
        $pdo->prepare("UPDATE appointments SET {$flag} = 1 WHERE id = :id")
            ->execute([':id' => $appt['id']]);

        cLog("✓ [{$type}] Enviado a {$phoneForWa} para cita #{$appt['id']} ({$date} {$time})");
        return true;
    } else {
        cLog("✗ [{$type}] Error al enviar a {$phoneForWa} para cita #{$appt['id']}: " . ($result['error'] ?? 'desconocido'));
        return false;
    }
}

// -------------------------------------------------------
// Logger de cron
// -------------------------------------------------------
function cLog(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}
