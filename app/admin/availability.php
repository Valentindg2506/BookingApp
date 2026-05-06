<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

$pdo = Database::getInstance()->getConnection();
$msg = '';
$msgType = 'success';

// ---- Guardar horario semanal ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_schedule') {
    // Eliminar todos los horarios actuales y reinsertar
    $pdo->exec("DELETE FROM availability_schedule");

    $days = $_POST['day'] ?? [];
    foreach ($days as $dow => $dayData) {
        $dow       = (int)$dow;
        $isActive  = isset($dayData['active']) ? 1 : 0;
        $startTime = $dayData['start'] ?? '09:00';
        $endTime   = $dayData['end']   ?? '18:00';
        $slotDur   = (int)($dayData['slot'] ?? 30);

        if ($isActive && $startTime && $endTime && $startTime < $endTime) {
            $st = $pdo->prepare(
                "INSERT INTO availability_schedule (day_of_week, start_time, end_time, slot_duration_minutes, is_active)
                 VALUES (:dow, :start, :end, :slot, 1)"
            );
            $st->execute([':dow' => $dow, ':start' => $startTime, ':end' => $endTime, ':slot' => $slotDur]);
        }
    }
    $msg = 'Horario guardado correctamente.';
}

// ---- Añadir excepción (bloqueo de día) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_exception') {
    $exDate    = sanitize($_POST['exception_date'] ?? '');
    $isBlocked = (int)($_POST['is_blocked'] ?? 1);
    $reason    = sanitize($_POST['reason'] ?? '');

    if ($exDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $exDate)) {
        $st = $pdo->prepare(
            "INSERT INTO availability_exceptions (exception_date, is_blocked, reason)
             VALUES (:d, :b, :r)
             ON DUPLICATE KEY UPDATE is_blocked = :b2, reason = :r2"
        );
        $st->execute([':d' => $exDate, ':b' => $isBlocked, ':r' => $reason, ':b2' => $isBlocked, ':r2' => $reason]);
        $msg = 'Excepción guardada para ' . formatDate($exDate) . '.';
    } else {
        $msg     = 'Fecha inválida.';
        $msgType = 'error';
    }
}

// ---- Eliminar excepción ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_exception') {
    $exId = (int)($_POST['exception_id'] ?? 0);
    if ($exId > 0) {
        $pdo->prepare("DELETE FROM availability_exceptions WHERE id = :id")->execute([':id' => $exId]);
        $msg = 'Excepción eliminada.';
    }
}

// ---- Cargar horario actual ----
$scheduleStmt = $pdo->query("SELECT * FROM availability_schedule ORDER BY day_of_week");
$scheduleRows = $scheduleStmt->fetchAll();

// Indexar por day_of_week para fácil acceso
$scheduleByDay = [];
foreach ($scheduleRows as $row) {
    $scheduleByDay[(int)$row['day_of_week']] = $row;
}

// ---- Cargar excepciones futuras ----
$exStmt = $pdo->query(
    "SELECT * FROM availability_exceptions
     WHERE exception_date >= CURDATE()
     ORDER BY exception_date ASC
     LIMIT 30"
);
$exceptions = $exStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disponibilidad – <?= APP_NAME ?> Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/partials/admin_styles.php'; ?>
</head>
<body>
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div class="main-content">
    <?php include __DIR__ . '/partials/topbar.php'; ?>

    <div class="page-body">
        <div class="page-header">
            <h1>Gestión de Disponibilidad</h1>
            <p>Configura tu horario semanal y bloquea días específicos.</p>
        </div>

        <?php if ($msg): ?>
        <div class="alert-<?= $msgType ?>"><?= $msgType === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <div class="two-col" style="gap:24px">

            <!-- Horario semanal -->
            <div class="card">
                <div class="card-header">
                    <h2>📅 Horario semanal</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_schedule">
                        <?php
                        $dayNames = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
                        $defaultSchedule = ['start' => '09:00', 'end' => '18:00', 'slot_duration_minutes' => 30, 'is_active' => 0];

                        for ($dow = 0; $dow <= 6; $dow++):
                            $sch = $scheduleByDay[$dow] ?? $defaultSchedule;
                            $isActive = (int)($sch['is_active'] ?? 0);
                        ?>
                        <div class="avail-card <?= $isActive ? 'active-day' : '' ?>" style="margin-bottom:12px" id="dayCard<?= $dow ?>">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                                <span class="avail-day-name"><?= $dayNames[$dow] ?></span>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="day[<?= $dow ?>][active]" value="1"
                                           <?= $isActive ? 'checked' : '' ?>
                                           onchange="toggleDayCard(<?= $dow ?>, this.checked)">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div id="dayForm<?= $dow ?>" style="<?= !$isActive ? 'display:none' : '' ?>">
                                <div class="form-row">
                                    <div class="form-group" style="margin:0">
                                        <label>Inicio</label>
                                        <input type="time" name="day[<?= $dow ?>][start]" value="<?= htmlspecialchars($sch['start_time'] ?? '09:00') ?>">
                                    </div>
                                    <div class="form-group" style="margin:0">
                                        <label>Fin</label>
                                        <input type="time" name="day[<?= $dow ?>][end]" value="<?= htmlspecialchars($sch['end_time'] ?? '18:00') ?>">
                                    </div>
                                </div>
                                <div class="form-group" style="margin-top:8px;margin-bottom:0">
                                    <label>Duración del slot (min)</label>
                                    <select name="day[<?= $dow ?>][slot]">
                                        <?php foreach ([15, 20, 30, 45, 60] as $mins): ?>
                                        <option value="<?= $mins ?>" <?= (int)($sch['slot_duration_minutes'] ?? 30) === $mins ? 'selected' : '' ?>><?= $mins ?> min</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <?php endfor; ?>

                        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px">
                            💾 Guardar horario
                        </button>
                    </form>
                </div>
            </div>

            <!-- Excepciones / Días bloqueados -->
            <div>
                <!-- Añadir excepción -->
                <div class="card" style="margin-bottom:20px">
                    <div class="card-header">
                        <h2>🚫 Bloquear / Habilitar día</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_exception">
                            <div class="form-group">
                                <label>Fecha</label>
                                <input type="date" name="exception_date" min="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Tipo</label>
                                <select name="is_blocked">
                                    <option value="1">🚫 Bloquear este día (vacaciones, festivo…)</option>
                                    <option value="0">✅ Habilitar este día (extra fuera de horario)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Motivo (opcional)</label>
                                <input type="text" name="reason" placeholder="Ej: Festivo nacional, Vacaciones…">
                            </div>
                            <button type="submit" class="btn btn-primary" style="width:100%">Añadir excepción</button>
                        </form>
                    </div>
                </div>

                <!-- Lista de excepciones próximas -->
                <div class="card">
                    <div class="card-header">
                        <h2>📋 Excepciones próximas</h2>
                    </div>
                    <div class="card-body" style="padding:0">
                        <?php if (empty($exceptions)): ?>
                            <p class="empty-msg" style="padding:20px">No hay excepciones configuradas.</p>
                        <?php else: ?>
                        <table class="data-table">
                            <thead><tr><th>Fecha</th><th>Tipo</th><th>Motivo</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($exceptions as $ex): ?>
                            <tr>
                                <td><?= formatDate($ex['exception_date']) ?></td>
                                <td>
                                    <?php if ($ex['is_blocked']): ?>
                                        <span class="badge badge-cancelled">Bloqueado</span>
                                    <?php else: ?>
                                        <span class="badge badge-confirmed">Habilitado</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color:var(--muted);font-size:.82rem"><?= htmlspecialchars($ex['reason'] ?: '—') ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('¿Eliminar esta excepción?')">
                                        <input type="hidden" name="action" value="delete_exception">
                                        <input type="hidden" name="exception_id" value="<?= $ex['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">✕</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleDayCard(dow, isActive) {
    const form = document.getElementById('dayForm' + dow);
    const card = document.getElementById('dayCard' + dow);
    form.style.display = isActive ? '' : 'none';
    card.classList.toggle('active-day', isActive);
}
</script>
</body>
</html>
