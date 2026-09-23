<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

$pdo = Database::getInstance()->getConnection();

// ---- Estadísticas ----
$stats = [];

// Total citas
$stats['total'] = (int)$pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
// Citas hoy
$stats['today'] = (int)$pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status != 'cancelled'")->execute() ?: 0;
$stToday = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status != 'cancelled'");
$stToday->execute();
$stats['today'] = (int)$stToday->fetchColumn();

// Citas esta semana
$stWeek = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE YEARWEEK(appointment_date, 1) = YEARWEEK(CURDATE(), 1) AND status != 'cancelled'");
$stWeek->execute();
$stats['week'] = (int)$stWeek->fetchColumn();

// Pendientes/confirmadas
$stConfirmed = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE status = 'confirmed'");
$stConfirmed->execute();
$stats['confirmed'] = (int)$stConfirmed->fetchColumn();

// Canceladas
$stCancelled = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE status = 'cancelled'");
$stCancelled->execute();
$stats['cancelled'] = (int)$stCancelled->fetchColumn();

// Total leads de Meta
$stLeads = $pdo->prepare("SELECT COUNT(*) FROM leads");
$stLeads->execute();
$stats['leads'] = (int)$stLeads->fetchColumn();

// ---- Próximas 5 citas ----
$stNext = $pdo->prepare(
    "SELECT * FROM appointments
     WHERE status = 'confirmed'
       AND (appointment_date > CURDATE()
            OR (appointment_date = CURDATE() AND appointment_time >= CURTIME()))
     ORDER BY appointment_date ASC, appointment_time ASC
     LIMIT 5"
);
$stNext->execute();
$upcomingAppointments = $stNext->fetchAll();

// ---- Últimas 5 citas registradas ----
$stRecent = $pdo->prepare(
    "SELECT * FROM appointments ORDER BY created_at DESC LIMIT 5"
);
$stRecent->execute();
$recentAppointments = $stRecent->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – <?= APP_NAME ?> Admin</title>
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
            <h1>Dashboard</h1>
            <p>Bienvenido de nuevo, <strong><?= htmlspecialchars($_SESSION['admin_name']) ?></strong></p>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card kpi-blue">
                <div class="kpi-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
                <div class="kpi-info">
                    <span class="kpi-num"><?= $stats['total'] ?></span>
                    <span class="kpi-label">Total Citas</span>
                </div>
            </div>
            <div class="kpi-card kpi-cyan">
                <div class="kpi-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="kpi-info">
                    <span class="kpi-num"><?= $stats['today'] ?></span>
                    <span class="kpi-label">Citas Hoy</span>
                </div>
            </div>
            <div class="kpi-card kpi-green">
                <div class="kpi-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="kpi-info">
                    <span class="kpi-num"><?= $stats['confirmed'] ?></span>
                    <span class="kpi-label">Confirmadas</span>
                </div>
            </div>
            <div class="kpi-card kpi-orange">
                <div class="kpi-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12"/></svg>
                </div>
                <div class="kpi-info">
                    <span class="kpi-num"><?= $stats['leads'] ?></span>
                    <span class="kpi-label">Leads Meta</span>
                </div>
            </div>
        </div>

        <div class="two-col">
            <!-- Próximas citas -->
            <div class="card">
                <div class="card-header">
                    <h2>📅 Próximas citas</h2>
                    <a href="appointments.php" class="link-small">Ver todas →</a>
                </div>
                <div class="card-body">
                    <?php if (empty($upcomingAppointments)): ?>
                        <p class="empty-msg">No hay citas próximas.</p>
                    <?php else: ?>
                        <table class="mini-table">
                            <thead><tr><th>Cliente</th><th>Fecha</th><th>Hora</th></tr></thead>
                            <tbody>
                            <?php foreach ($upcomingAppointments as $a): ?>
                                <tr>
                                    <td><?= htmlspecialchars($a['full_name']) ?></td>
                                    <td><?= formatDate($a['appointment_date']) ?></td>
                                    <td><?= formatTime($a['appointment_time']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Últimas reservas -->
            <div class="card">
                <div class="card-header">
                    <h2>🕐 Últimas reservas</h2>
                    <a href="appointments.php" class="link-small">Ver todas →</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentAppointments)): ?>
                        <p class="empty-msg">No hay reservas aún.</p>
                    <?php else: ?>
                        <table class="mini-table">
                            <thead><tr><th>Cliente</th><th>Estado</th><th>Creada</th></tr></thead>
                            <tbody>
                            <?php foreach ($recentAppointments as $a): ?>
                                <tr>
                                    <td><?= htmlspecialchars($a['full_name']) ?></td>
                                    <td><span class="badge badge-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
                                    <td><?= formatDate($a['created_at'], 'd/m H:i') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Mini stats semana -->
        <div class="card" style="margin-top:24px">
            <div class="card-header">
                <h2>📊 Resumen semanal</h2>
            </div>
            <div class="card-body">
                <div class="week-stats">
                    <div class="week-stat">
                        <span class="ws-num"><?= $stats['week'] ?></span>
                        <span class="ws-label">Citas esta semana</span>
                    </div>
                    <div class="week-stat">
                        <span class="ws-num"><?= $stats['cancelled'] ?></span>
                        <span class="ws-label">Canceladas total</span>
                    </div>
                    <div class="week-stat">
                        <span class="ws-num"><?= $stats['total'] > 0 ? round((($stats['total'] - $stats['cancelled']) / $stats['total']) * 100) : 0 ?>%</span>
                        <span class="ws-label">Tasa de confirmación</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
