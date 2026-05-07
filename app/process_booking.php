<?php
/**
 * process_booking.php
 * Procesa el formulario de reserva: valida, inserta en BD,
 * crea sala de Meet y envía confirmación por WhatsApp.
 */

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/includes/helpers.php";
require_once __DIR__ . "/includes/whatsapp.php";
require_once __DIR__ . "/includes/google_meet.php";

session_name(SESSION_NAME);
session_start();

// Solo POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect(APP_URL . "/index.php");
}

// -------------------------------------------------------
// 1. Validar CSRF
// -------------------------------------------------------
if (!csrf_verify($_POST["csrf_token"] ?? "")) {
    redirect(APP_URL . "/index.php?error=csrf");
}

// -------------------------------------------------------
// 2. Recoger y validar campos
// -------------------------------------------------------
$fullName = sanitize($_POST["full_name"] ?? "");
$email = sanitize($_POST["email"] ?? "");
$phone = sanitize($_POST["phone"] ?? "");
$selDate = sanitize($_POST["selected_date"] ?? "");
$selTime = sanitize($_POST["selected_time"] ?? "");
$metaLeadId = sanitize($_POST["meta_lead_id"] ?? "");

$errors = [];

if (empty($fullName)) {
    $errors[] = "Nombre requerido";
}
if (!isValidEmail($email)) {
    $errors[] = "Email inválido";
}
if (!isValidPhone($phone)) {
    $errors[] = "Teléfono inválido";
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selDate)) {
    $errors[] = "Fecha inválida";
}
if (!preg_match('/^\d{2}:\d{2}$/', $selTime)) {
    $errors[] = "Hora inválida";
}

if (!empty($errors)) {
    redirect(APP_URL . "/index.php?error=validation");
}

// No permitir fechas pasadas
if ($selDate < date("Y-m-d")) {
    redirect(APP_URL . "/index.php?error=date_past");
}

$pdo = Database::getInstance()->getConnection();

// -------------------------------------------------------
// 3. Verificar que el slot sigue disponible (race condition)
// -------------------------------------------------------
$stmtCheck = $pdo->prepare(
    "SELECT COUNT(*) FROM appointments
     WHERE appointment_date = :d
       AND appointment_time = :t
       AND status != 'cancelled'",
);
$stmtCheck->execute([":d" => $selDate, ":t" => $selTime . ":00"]);
if ((int) $stmtCheck->fetchColumn() > 0) {
    redirect(APP_URL . "/index.php?error=slot_taken");
}

// -------------------------------------------------------
// 4. Buscar lead de Meta si existe
// -------------------------------------------------------
$leadId = null;
if (!empty($metaLeadId)) {
    $stmtLead = $pdo->prepare(
        "SELECT id FROM leads WHERE meta_lead_id = :lid LIMIT 1",
    );
    $stmtLead->execute([":lid" => $metaLeadId]);
    $lead = $stmtLead->fetch();
    if ($lead) {
        $leadId = (int) $lead["id"];
    }
}

// -------------------------------------------------------
// 5. Crear sala de Google Meet
// -------------------------------------------------------
$meetingDateTime = $selDate . "T" . $selTime . ":00";
$googleMeet = new GoogleMeet();
$meetLink = $googleMeet->createMeeting(
    "Reunión con " . $fullName,
    "Cita agendada desde " . BUSINESS_NAME,
    $meetingDateTime,
    60,
    $email,
);

// -------------------------------------------------------
// 6. Insertar cita en BD
// -------------------------------------------------------
try {
    $stmtIns = $pdo->prepare(
        "INSERT INTO appointments
            (lead_id, full_name, email, phone, appointment_date, appointment_time,
             meet_link, status, confirmation_sent)
         VALUES
            (:lead_id, :full_name, :email, :phone, :date, :time,
             :meet_link, 'confirmed', 0)",
    );
    $stmtIns->execute([
        ":lead_id" => $leadId,
        ":full_name" => $fullName,
        ":email" => $email,
        ":phone" => $phone,
        ":date" => $selDate,
        ":time" => $selTime . ":00",
        ":meet_link" => $meetLink,
    ]);
    $appointmentId = (int) $pdo->lastInsertId();
} catch (PDOException $e) {
    error_log("[process_booking] Error insertando cita: " . $e->getMessage());
    redirect(APP_URL . "/index.php?error=db");
}

// -------------------------------------------------------
// 7. Enviar confirmación por WhatsApp
// -------------------------------------------------------
$phoneForWa = formatPhoneForWhatsApp($phone);
$waResult = WhatsApp::sendConfirmation(
    $phoneForWa,
    $fullName,
    $selDate,
    $selTime,
    BUSINESS_NAME,
);

// Registrar en notification_logs
$stmtLog = $pdo->prepare(
    "INSERT INTO notification_logs
        (appointment_id, type, whatsapp_to, message_sid, status, error_message, sent_at)
     VALUES
        (:appt_id, 'confirmation', :to, :sid, :status, :error, :sent_at)",
);
$stmtLog->execute([
    ":appt_id" => $appointmentId,
    ":to" => $phoneForWa,
    ":sid" => $waResult["sid"] ?? null,
    ":status" => $waResult["success"] ? "sent" : "failed",
    ":error" => $waResult["error"] ?? null,
    ":sent_at" => $waResult["success"] ? date("Y-m-d H:i:s") : null,
]);

// Actualizar flag en appointments si se envió
if ($waResult["success"]) {
    $pdo->prepare(
        "UPDATE appointments SET confirmation_sent = 1 WHERE id = :id",
    )->execute([":id" => $appointmentId]);
}

// -------------------------------------------------------
// 8. Guardar token en sesión y redirigir a confirmación
// -------------------------------------------------------
$token = generateToken(16);
$_SESSION["booking_" . $appointmentId] = $token;

redirect(APP_URL . "/confirm.php?id=" . $appointmentId . "&token=" . $token);
