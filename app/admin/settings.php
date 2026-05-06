<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

$pdo = Database::getInstance()->getConnection();
$msg = '';
$msgType = 'success';

// ---- Cambiar contraseña ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $currentPw  = $_POST['current_password']  ?? '';
    $newPw      = $_POST['new_password']       ?? '';
    $confirmPw  = $_POST['confirm_password']   ?? '';

    // Verificar contraseña actual
    $st = $pdo->prepare("SELECT password FROM admin_users WHERE id = :id");
    $st->execute([':id' => $_SESSION['admin_id']]);
    $user = $st->fetch();

    if (!$user || !password_verify($currentPw, $user['password'])) {
        $msg = 'La contraseña actual es incorrecta.';
        $msgType = 'error';
    } elseif (strlen($newPw) < 8) {
        $msg = 'La nueva contraseña debe tener al menos 8 caracteres.';
        $msgType = 'error';
    } elseif ($newPw !== $confirmPw) {
        $msg = 'Las contraseñas nuevas no coinciden.';
        $msgType = 'error';
    } else {
        $hash = password_hash($newPw, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE admin_users SET password = :p WHERE id = :id")
            ->execute([':p' => $hash, ':id' => $_SESSION['admin_id']]);
        $msg = 'Contraseña actualizada correctamente.';
    }
}

// ---- Obtener logs de notificaciones recientes ----
$logStmt = $pdo->prepare(
    "SELECT nl.*, a.full_name, a.appointment_date, a.appointment_time
     FROM notification_logs nl
     JOIN appointments a ON a.id = nl.appointment_id
     ORDER BY nl.created_at DESC
     LIMIT 20"
);
$logStmt->execute();
$notifLogs = $logStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración – <?= APP_NAME ?> Admin</title>
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
            <h1>Configuración</h1>
            <p>Seguridad y monitoreo del sistema.</p>
        </div>

        <?php if ($msg): ?>
        <div class="alert-<?= $msgType ?>"><?= $msgType === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <div class="two-col" style="gap:24px;align-items:start">

            <!-- Cambiar contraseña -->
            <div class="card">
                <div class="card-header">
                    <h2>🔐 Cambiar contraseña</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="form-group">
                            <label>Contraseña actual</label>
                            <input type="password" name="current_password" required autocomplete="current-password">
                        </div>
                        <div class="form-group">
                            <label>Nueva contraseña</label>
                            <input type="password" name="new_password" required minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
                        </div>
                        <div class="form-group">
                            <label>Confirmar nueva contraseña</label>
                            <input type="password" name="confirm_password" required autocomplete="new-password">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%">Actualizar contraseña</button>
                    </form>
                </div>
            </div>

            <!-- Info del sistema -->
            <div class="card">
                <div class="card-header">
                    <h2>ℹ️ Información del sistema</h2>
                </div>
                <div class="card-body">
                    <table class="mini-table">
                        <tr><td style="color:var(--muted);width:50%">Aplicación</td><td><strong><?= APP_NAME ?></strong></td></tr>
                        <tr><td style="color:var(--muted)">URL</td><td><?= APP_URL ?></td></tr>
                        <tr><td style="color:var(--muted)">Negocio</td><td><?= BUSINESS_NAME ?></td></tr>
                        <tr><td style="color:var(--muted)">Teléfono</td><td><?= BUSINESS_PHONE ?></td></tr>
                        <tr><td style="color:var(--muted)">Zona horaria</td><td><?= APP_TIMEZONE ?></td></tr>
                        <tr><td style="color:var(--muted)">PHP Version</td><td><?= phpversion() ?></td></tr>
                        <tr><td style="color:var(--muted)">Servidor</td><td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' ?></td></tr>
                    </table>

                    <div style="margin-top:20px">
                        <p style="font-size:.82rem;color:var(--muted);margin-bottom:8px;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Cron Job (recordatorios)</p>
                        <code style="display:block;background:#f0f4ff;padding:10px 14px;border-radius:8px;font-size:.78rem;word-break:break-all">*/15 * * * * php <?= __DIR__ ?>/../cron/reminders.php >> /tmp/booking_cron.log 2>&1</code>
                    </div>

                    <div style="margin-top:12px">
                        <p style="font-size:.82rem;color:var(--muted);margin-bottom:8px;font-weight:600;text-transform:uppercase;letter-spacing:.04em">URL Webhook Meta</p>
                        <code style="display:block;background:#f0f4ff;padding:10px 14px;border-radius:8px;font-size:.78rem;word-break:break-all"><?= APP_URL ?>/webhook.php</code>
                    </div>
                </div>
            </div>
        </div>

        <!-- Log de notificaciones -->
        <div class="card" style="margin-top:24px">
            <div class="card-header">
                <h2>📨 Log de notificaciones WhatsApp (últimas 20)</h2>
            </div>
            <div class="card-body" style="padding:0;overflow-x:auto">
                <?php if (empty($notifLogs)): ?>
                    <p class="empty-msg" style="padding:20px">No hay notificaciones registradas aún.</p>
                <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>#Cita</th><th>Cliente</th><th>Tipo</th><th>Teléfono</th><th>Estado</th><th>Enviado</th><th>Error</th></tr></thead>
                    <tbody>
                    <?php foreach ($notifLogs as $log): ?>
                        <tr>
                            <td>#<?= $log['appointment_id'] ?></td>
                            <td>
                                <?= htmlspecialchars($log['full_name']) ?>
                                <div style="font-size:.78rem;color:var(--muted)"><?= formatDate($log['appointment_date']) ?> <?= formatTime($log['appointment_time']) ?></div>
                            </td>
                            <td>
                                <?php
                                $typeLabels = ['confirmation' => '✅ Confirmación', 'reminder_1day' => '📅 1 día antes', 'reminder_2hours' => '⏰ 2h antes'];
                                echo $typeLabels[$log['type']] ?? $log['type'];
                                ?>
                            </td>
                            <td style="font-size:.82rem"><?= htmlspecialchars($log['whatsapp_to']) ?></td>
                            <td><span class="badge badge-<?= $log['status'] ?>"><?= ucfirst($log['status']) ?></span></td>
                            <td style="font-size:.78rem;color:var(--muted)"><?= $log['sent_at'] ? formatDate($log['sent_at'], 'd/m H:i') : '—' ?></td>
                            <td style="font-size:.78rem;color:#e53935;max-width:200px"><?= htmlspecialchars($log['error_message'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
