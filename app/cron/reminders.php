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
 *   2. Recordatorio 1 hora antes  (entre 55min y 65min antes de la cita)
 *   3. Recordatorio 15 minutos antes (entre 10min y 20min antes de la cita)
 */

// Evitar ejecución desde el navegador
if (PHP_SAPI !== "cli" && !isset($_GET["cron_key"])) {
    http_response_code(403);
    exit("Acceso no permitido.");
}
if (isset($_GET["cron_key"])) {
    // Leer clave desde BD si está disponible, si no desde config
    // (Settings se carga después de la BD, aquí hacemos un mini-check directo)
    $expectedKey = "CAMBIA_ESTA_CLAVE";
    try {
        require_once __DIR__ . "/../config.php";
        require_once __DIR__ . "/../db.php";
        $tmpPdo = Database::getInstance()->getConnection();
        $tmpStmt = $tmpPdo->prepare(
            "SELECT value FROM app_settings WHERE `key` = 'cron_secret_key' LIMIT 1",
        );
        $tmpStmt->execute();
        $tmpRow = $tmpStmt->fetch(PDO::FETCH_ASSOC);
        if ($tmpRow && !empty($tmpRow["value"])) {
            $expectedKey = $tmpRow["value"];
        }
    } catch (Exception $e) {
        /* fallback */
    }

    if ($_GET["cron_key"] !== $expectedKey) {
        http_response_code(403);
        exit("Clave incorrecta.");
    }
}

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../includes/helpers.php";
require_once __DIR__ . "/../includes/whatsapp.php";
require_once __DIR__ . "/../includes/settings.php";

$pdo = Database::getInstance()->getConnection();
Settings::load($pdo);
$now = new DateTime("now", new DateTimeZone(APP_TIMEZONE));
$nowTs = $now->getTimestamp();

cLog("--- Inicio de ejecución: " . $now->format("Y-m-d H:i:s") . " ---");

$sent1Day = 0;
$sent2Hours = 0;
$sent15Min = 0;
$errors = 0;

// -------------------------------------------------------
// 1. Recordatorio 1 DÍA ANTES
//    Ventana: entre 23h45m y 24h15m antes de la cita
// -------------------------------------------------------
$win1Start = date("Y-m-d H:i:s", $nowTs + (23 * 3600 + 45 * 60));
$win1End = date("Y-m-d H:i:s", $nowTs + (24 * 3600 + 15 * 60));

$stmt1 = $pdo->prepare(
    "SELECT a.*
     FROM appointments a
     WHERE a.status = 'confirmed'
       AND a.reminder_1day_sent = 0
       AND CONCAT(a.appointment_date, ' ', a.appointment_time) >= :win_start
       AND CONCAT(a.appointment_date, ' ', a.appointment_time) <= :win_end",
);
$stmt1->execute([":win_start" => $win1Start, ":win_end" => $win1End]);
$appointments1day = $stmt1->fetchAll();

cLog(
    "Recordatorio 1 día antes - Citas encontradas: " . count($appointments1day),
);

foreach ($appointments1day as $appt) {
    $result = sendReminder($pdo, $appt, "reminder_1day");
    if ($result) {
        $sent1Day++;
    } else {
        $errors++;
    }
}

// -------------------------------------------------------
// 2. Recordatorio 1 HORA ANTES
//    Ventana: entre 55min y 65min antes de la cita
// -------------------------------------------------------
$win2Start = date("Y-m-d H:i:s", $nowTs + 55 * 60);
$win2End = date("Y-m-d H:i:s", $nowTs + 65 * 60);

$stmt2 = $pdo->prepare(
    "SELECT a.*
     FROM appointments a
     WHERE a.status = 'confirmed'
       AND a.reminder_2hours_sent = 0
       AND CONCAT(a.appointment_date, ' ', a.appointment_time) >= :win_start
       AND CONCAT(a.appointment_date, ' ', a.appointment_time) <= :win_end",
);
$stmt2->execute([":win_start" => $win2Start, ":win_end" => $win2End]);
$appointments2h = $stmt2->fetchAll();

cLog(
    "Recordatorio 1 hora antes - Citas encontradas: " . count($appointments2h),
);

foreach ($appointments2h as $appt) {
    $result = sendReminder($pdo, $appt, "reminder_2hours");
    if ($result) {
        $sent2Hours++;
    } else {
        $errors++;
    }
}

// -------------------------------------------------------
// 3. Recordatorio 15 MINUTOS ANTES
//    Ventana: entre 10min y 20min antes de la cita
// -------------------------------------------------------
$win3Start = date("Y-m-d H:i:s", $nowTs + 10 * 60);
$win3End = date("Y-m-d H:i:s", $nowTs + 20 * 60);

$stmt3 = $pdo->prepare(
    "SELECT a.*
     FROM appointments a
     WHERE a.status = 'confirmed'
       AND a.reminder_15min_sent = 0
       AND CONCAT(a.appointment_date, ' ', a.appointment_time) >= :win_start
       AND CONCAT(a.appointment_date, ' ', a.appointment_time) <= :win_end",
);
$stmt3->execute([":win_start" => $win3Start, ":win_end" => $win3End]);
$appointments15min = $stmt3->fetchAll();

cLog(
    "Recordatorio 15 min antes - Citas encontradas: " .
        count($appointments15min),
);

foreach ($appointments15min as $appt) {
    $result = sendReminder($pdo, $appt, "reminder_15min");
    if ($result) {
        $sent15Min++;
    } else {
        $errors++;
    }
}

cLog(
    "Resumen: 1día={$sent1Day} enviados | 2h={$sent2Hours} enviados | 15min={$sent15Min} enviados | errores={$errors}",
);
cLog("--- Fin de ejecución ---");

// -------------------------------------------------------
// Función: envía el recordatorio y actualiza BD
// -------------------------------------------------------
function sendReminder(PDO $pdo, array $appt, string $type): bool
{
    $phoneForWa = formatPhoneForWhatsApp($appt["phone"]);
    $name = $appt["full_name"];
    $date = $appt["appointment_date"];
    $time = formatTime($appt["appointment_time"]);
    $meetLink = $appt["meet_link"] ?? "";
    $business = BUSINESS_NAME;

    if ($type === "reminder_1day") {
        $result = WhatsApp::sendReminder1Day(
            $phoneForWa,
            $name,
            $date,
            $time,
            $business,
        );
        $flag = "reminder_1day_sent";
    } elseif ($type === "reminder_15min") {
        $result = WhatsApp::sendReminder15Min(
            $phoneForWa,
            $name,
            $time,
            $business,
            $meetLink,
        );
        $flag = "reminder_15min_sent";
    } else {
        $result = WhatsApp::sendReminder1Hour(
            $phoneForWa,
            $name,
            $date,
            $time,
            $business,
            $meetLink,
        );
        $flag = "reminder_2hours_sent";
    }

    // Registrar en notification_logs
    $stmt = $pdo->prepare(
        "INSERT INTO notification_logs
            (appointment_id, type, whatsapp_to, message_sid, status, error_message, sent_at)
         VALUES
            (:appt_id, :type, :to, :sid, :status, :error, :sent_at)",
    );
    $stmt->execute([
        ":appt_id" => $appt["id"],
        ":type" => $type,
        ":to" => $phoneForWa,
        ":sid" => $result["sid"] ?? null,
        ":status" => $result["success"] ? "sent" : "failed",
        ":error" => $result["error"] ?? null,
        ":sent_at" => $result["success"] ? date("Y-m-d H:i:s") : null,
    ]);

    if ($result["success"]) {
        // Marcar como enviado en appointments
        $pdo->prepare(
            "UPDATE appointments SET {$flag} = 1 WHERE id = :id",
        )->execute([":id" => $appt["id"]]);

        cLog(
            "✓ [{$type}] Enviado a {$phoneForWa} para cita #{$appt["id"]} ({$date} {$time})",
        );

        // ---- Notificar también al administrador ----
        sendAdminNotification($appt, $type, $time, $meetLink);

        return true;
    } else {
        cLog(
            "✗ [{$type}] Error al enviar a {$phoneForWa} para cita #{$appt["id"]}: " .
                ($result["error"] ?? "desconocido"),
        );
        return false;
    }
}

// -------------------------------------------------------
// Función: notifica al administrador por WhatsApp
// -------------------------------------------------------
function sendAdminNotification(
    array $appt,
    string $type,
    string $time,
    string $meetLink,
): void {
    // El número del admin se configura en config.php como ADMIN_WHATSAPP
    // Formato esperado: "whatsapp:+34600000000"
    if (!defined("ADMIN_WHATSAPP") || empty(ADMIN_WHATSAPP)) {
        cLog(
            "⚠ [admin] ADMIN_WHATSAPP no configurado, se omite notificación al admin.",
        );
        return;
    }

    $name = $appt["full_name"];
    $email = $appt["email"];
    $phone = $appt["phone"];
    $date = $appt["appointment_date"];
    $id = $appt["id"];

    if ($type === "reminder_1day") {
        $message =
            "📋 *Recordatorio de agenda – mañana*\n\n" .
            "Tienes una reunión programada para *mañana*. 📆\n\n" .
            "👤 *Cliente:* {$name}\n" .
            "📞 *Teléfono:* {$phone}\n" .
            "📧 *Email:* {$email}\n" .
            "📅 *Fecha:* {$date}\n" .
            "🕐 *Hora:* {$time}\n" .
            "🔢 *Cita #:* {$id}\n\n" .
            "Recuerda preparar el enlace de Google Meet para enviarle al cliente mañana. ✅";
    } elseif ($type === "reminder_15min") {
        $meetInfo = !empty($meetLink)
            ? "🎥 *Enlace Meet:*\n{$meetLink}"
            : "⚠️ Sin enlace de Meet.";

        $message =
            "🔴 *¡AHORA! Reunión en 15 minutos*\n\n" .
            "👤 *Cliente:* {$name}\n" .
            "📞 *Teléfono:* {$phone}\n" .
            "📅 *Fecha:* {$date}\n" .
            "🕐 *Hora:* {$time}\n" .
            "🔢 *Cita #:* {$id}\n\n" .
            $meetInfo;
    } else {
        // reminder_2hours (ahora 1h)
        $meetInfo = !empty($meetLink)
            ? "🎥 *Enlace Meet:*\n{$meetLink}"
            : "⚠️ No hay enlace de Meet generado para esta cita.";

        $message =
            "⏰ *Reunión en 1 hora*\n\n" .
            "Tienes una reunión en aproximadamente *1 hora*. 🚀\n\n" .
            "👤 *Cliente:* {$name}\n" .
            "📞 *Teléfono:* {$phone}\n" .
            "📧 *Email:* {$email}\n" .
            "📅 *Fecha:* {$date}\n" .
            "🕐 *Hora:* {$time}\n" .
            "🔢 *Cita #:* {$id}\n\n" .
            $meetInfo .
            "\n\n" .
            "¡Éxito en la reunión! 💼";
    }

    $result = WhatsApp::send(ADMIN_WHATSAPP, $message);

    if ($result["success"]) {
        cLog(
            "✓ [admin] Notificación enviada al admin para cita #{$id} ({$type})",
        );
    } else {
        cLog(
            "✗ [admin] Error al notificar al admin para cita #{$id}: " .
                ($result["error"] ?? "desconocido"),
        );
    }
}

// -------------------------------------------------------
// Logger de cron
// -------------------------------------------------------
function cLog(string $msg): void
{
    echo "[" . date("Y-m-d H:i:s") . "] " . $msg . PHP_EOL;
}
