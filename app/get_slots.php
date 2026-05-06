<?php
/**
 * get_slots.php
 * Endpoint AJAX para consultar disponibilidad.
 *
 * GET ?date=YYYY-MM      → {"available_days": ["2025-07-01", ...]}
 * GET ?date=YYYY-MM-DD   → {"slots": ["09:00", "09:30", ...]}
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$date = trim($_GET['date'] ?? '');

if (empty($date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetro date requerido']);
    exit;
}

$pdo = Database::getInstance()->getConnection();

// -------------------------------------------------------
// Detectar si es vista mensual (YYYY-MM) o diaria (YYYY-MM-DD)
// -------------------------------------------------------
if (preg_match('/^\d{4}-\d{2}$/', $date)) {
    // ===================================================
    // VISTA MENSUAL: retorna días disponibles del mes
    // ===================================================
    [$year, $month] = explode('-', $date);
    $year  = (int)$year;
    $month = (int)$month;

    $daysInMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
    $today       = date('Y-m-d');
    $availableDays = [];

    for ($day = 1; $day <= $daysInMonth; $day++) {
        $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);

        // No mostrar días pasados
        if ($dateStr < $today) continue;

        $slots = getAvailableSlots($pdo, $dateStr);
        if (!empty($slots)) {
            $availableDays[] = $dateStr;
        }
    }

    echo json_encode(['available_days' => $availableDays]);
    exit;

} elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    // ===================================================
    // VISTA DIARIA: retorna horarios disponibles
    // ===================================================
    $today = date('Y-m-d');
    if ($date < $today) {
        echo json_encode(['slots' => []]);
        exit;
    }

    $slots = getAvailableSlots($pdo, $date);
    echo json_encode(['slots' => $slots]);
    exit;

} else {
    http_response_code(400);
    echo json_encode(['error' => 'Formato de fecha inválido. Use YYYY-MM o YYYY-MM-DD']);
    exit;
}

// -------------------------------------------------------
// Función: genera slots disponibles para una fecha dada
// -------------------------------------------------------
function getAvailableSlots(PDO $pdo, string $dateStr): array
{
    // day_of_week: 0=Dom, 1=Lun ... 6=Sáb (igual que date('w'))
    $dayOfWeek = (int)date('w', strtotime($dateStr));

    // 1. Verificar excepción puntual (día bloqueado)
    $stmtEx = $pdo->prepare(
        "SELECT is_blocked FROM availability_exceptions
         WHERE exception_date = :d LIMIT 1"
    );
    $stmtEx->execute([':d' => $dateStr]);
    $exception = $stmtEx->fetch();

    if ($exception && (int)$exception['is_blocked'] === 1) {
        return []; // Día bloqueado explícitamente
    }

    // 2. Obtener schedule del día de la semana
    $stmtSch = $pdo->prepare(
        "SELECT start_time, end_time, slot_duration_minutes
         FROM availability_schedule
         WHERE day_of_week = :dow AND is_active = 1
         LIMIT 1"
    );
    $stmtSch->execute([':dow' => $dayOfWeek]);
    $schedule = $stmtSch->fetch();

    // Si no hay schedule ni excepción que lo habilite, no hay slots
    if (!$schedule) {
        // Verificar si hay excepción que lo habilita (is_blocked=0)
        if (!$exception || (int)$exception['is_blocked'] !== 0) {
            return [];
        }
        // Día extra: usar horario por defecto 09:00-18:00 / 30 min
        $schedule = [
            'start_time'             => '09:00:00',
            'end_time'               => '18:00:00',
            'slot_duration_minutes'  => 30,
        ];
    }

    // 3. Generar todos los slots del rango horario
    $allSlots  = generateTimeSlots(
        $schedule['start_time'],
        $schedule['end_time'],
        (int)$schedule['slot_duration_minutes']
    );

    if (empty($allSlots)) return [];

    // 4. Obtener slots ya ocupados ese día
    $stmtOcc = $pdo->prepare(
        "SELECT TIME_FORMAT(appointment_time, '%H:%i') AS t
         FROM appointments
         WHERE appointment_date = :d
           AND status != 'cancelled'"
    );
    $stmtOcc->execute([':d' => $dateStr]);
    $occupied = array_column($stmtOcc->fetchAll(), 't');

    // 5. Filtrar pasado si es hoy
    $today = date('Y-m-d');
    $nowTime = ($dateStr === $today) ? date('H:i') : null;

    $available = [];
    foreach ($allSlots as $slot) {
        if (in_array($slot, $occupied, true)) continue;
        if ($nowTime !== null && $slot <= $nowTime) continue;
        $available[] = $slot;
    }

    return $available;
}

// -------------------------------------------------------
// Función: genera array de horarios entre start y end
// -------------------------------------------------------
function generateTimeSlots(string $start, string $end, int $durationMinutes): array
{
    $slots   = [];
    $current = strtotime($start);
    $endTs   = strtotime($end);

    while ($current < $endTs) {
        $slots[]  = date('H:i', $current);
        $current += $durationMinutes * 60;
    }

    return $slots;
}
