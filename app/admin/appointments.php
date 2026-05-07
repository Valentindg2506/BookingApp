<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../includes/helpers.php";
require_once __DIR__ . "/../includes/auth_check.php";
require_once __DIR__ . "/../includes/whatsapp.php";

$pdo = Database::getInstance()->getConnection();
$msg = "";
$msgType = "success";

// ---- Acción: Cancelar cita ----
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "cancel"
) {
    $cancelId = (int) ($_POST["appointment_id"] ?? 0);
    if ($cancelId > 0) {
        // Recuperar datos de la cita antes de cancelar para poder notificar
        $stFetch = $pdo->prepare(
            "SELECT * FROM appointments WHERE id = :id AND status != 'cancelled' LIMIT 1",
        );
        $stFetch->execute([":id" => $cancelId]);
        $apptToCancel = $stFetch->fetch();

        $st = $pdo->prepare(
            "UPDATE appointments SET status = 'cancelled', updated_at = NOW() WHERE id = :id AND status != 'cancelled'",
        );
        $st->execute([":id" => $cancelId]);

        if ($st->rowCount()) {
            $msg = "Cita #" . $cancelId . " cancelada correctamente.";
            $msgType = "success";

            // Notificar al cliente por WhatsApp
            if ($apptToCancel) {
                $waPhone = formatPhoneForWhatsApp($apptToCancel["phone"]);
                $waDate = formatDate($apptToCancel["appointment_date"]);
                $waTime = formatTime($apptToCancel["appointment_time"]);

                WhatsApp::sendCancellation(
                    $waPhone,
                    $apptToCancel["full_name"],
                    $waDate,
                    $waTime,
                    BUSINESS_NAME,
                    BUSINESS_PHONE,
                );
            }
        } else {
            $msg = "No se pudo cancelar (ya estaba cancelada o no existe).";
            $msgType = "error";
        }
    }
}

// ---- Acción: Marcar como completada ----
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "complete"
) {
    $completeId = (int) ($_POST["appointment_id"] ?? 0);
    if ($completeId > 0) {
        $st = $pdo->prepare(
            "UPDATE appointments SET status = 'completed', updated_at = NOW() WHERE id = :id AND status = 'confirmed'",
        );
        $st->execute([":id" => $completeId]);
        $msg = $st->rowCount()
            ? "Cita #" . $completeId . " marcada como completada."
            : "No se pudo actualizar.";
        $msgType = $st->rowCount() ? "success" : "error";
    }
}

// ---- Filtros ----
$filterStatus = $_GET["status"] ?? "";
$filterDate = $_GET["date"] ?? "";
$search = sanitize($_GET["q"] ?? "");

// ---- Paginación ----
$perPage = 20;
$currentPage = max(1, (int) ($_GET["page"] ?? 1));
$offset = ($currentPage - 1) * $perPage;

// ---- Query principal ----
$where = ["1=1"];
$params = [];

if ($filterStatus) {
    $where[] = "status = :status";
    $params[":status"] = $filterStatus;
}
if ($filterDate) {
    $where[] = "appointment_date = :fdate";
    $params[":fdate"] = $filterDate;
}
if ($search) {
    $where[] = "(full_name LIKE :q OR email LIKE :q OR phone LIKE :q)";
    $params[":q"] = "%" . $search . "%";
}

$whereStr = implode(" AND ", $where);

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM appointments WHERE {$whereStr}",
);
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = (int) ceil($totalRows / $perPage);

$listStmt = $pdo->prepare(
    "SELECT * FROM appointments WHERE {$whereStr}
     ORDER BY appointment_date DESC, appointment_time DESC
     LIMIT {$perPage} OFFSET {$offset}",
);
$listStmt->execute($params);
$appointments = $listStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citas – <?= APP_NAME ?> Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . "/partials/admin_styles.php"; ?>
</head>
<body>
<?php include __DIR__ . "/partials/sidebar.php"; ?>

<div class="main-content">
    <?php include __DIR__ . "/partials/topbar.php"; ?>

    <div class="page-body">
        <div class="page-header">
            <h1>Gestión de Citas</h1>
            <p><?= $totalRows ?> cita<?= $totalRows !== 1
     ? "s"
     : "" ?> encontrada<?= $totalRows !== 1 ? "s" : "" ?></p>
        </div>

        <?php if ($msg): ?>
        <div class="alert-<?= $msgType ?>"><?= $msgType === "success"
    ? "✅"
    : "⚠️" ?> <?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="card" style="margin-bottom:20px">
            <div class="card-body">
                <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
                    <div class="form-group" style="margin:0;flex:1;min-width:160px">
                        <label>Buscar</label>
                        <input type="text" name="q" placeholder="Nombre, email, teléfono…" value="<?= htmlspecialchars(
                            $search,
                        ) ?>">
                    </div>
                    <div class="form-group" style="margin:0;min-width:140px">
                        <label>Estado</label>
                        <select name="status">
                            <option value="">Todos</option>
                            <option value="confirmed"  <?= $filterStatus ===
                            "confirmed"
                                ? "selected"
                                : "" ?>>Confirmadas</option>
                            <option value="completed"  <?= $filterStatus ===
                            "completed"
                                ? "selected"
                                : "" ?>>Completadas</option>
                            <option value="cancelled"  <?= $filterStatus ===
                            "cancelled"
                                ? "selected"
                                : "" ?>>Canceladas</option>
                            <option value="pending"    <?= $filterStatus ===
                            "pending"
                                ? "selected"
                                : "" ?>>Pendientes</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;min-width:140px">
                        <label>Fecha</label>
                        <input type="date" name="date" value="<?= htmlspecialchars(
                            $filterDate,
                        ) ?>">
                    </div>
                    <div style="display:flex;gap:8px">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <a href="appointments.php" class="btn btn-secondary">Limpiar</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de citas -->
        <div class="card">
            <div class="card-body" style="padding:0;overflow-x:auto">
                <?php if (empty($appointments)): ?>
                    <p class="empty-msg" style="padding:40px">No se encontraron citas con los filtros aplicados.</p>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Teléfono</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Estado</th>
                            <th>WhatsApp</th>
                            <th>Meet</th>
                            <th class="col-actions">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($appointments as $a): ?>
                        <tr>
                            <td style="color:var(--muted);font-size:.8rem">#<?= $a[
                                "id"
                            ] ?></td>
                            <td>
                                <strong><?= htmlspecialchars(
                                    $a["full_name"],
                                ) ?></strong>
                                <div style="font-size:.78rem;color:var(--muted)"><?= htmlspecialchars(
                                    $a["email"],
                                ) ?></div>
                            </td>
                            <td><?= htmlspecialchars($a["phone"]) ?></td>
                            <td><?= formatDate($a["appointment_date"]) ?></td>
                            <td><strong><?= formatTime(
                                $a["appointment_time"],
                            ) ?></strong></td>
                            <td><span class="badge badge-<?= $a[
                                "status"
                            ] ?>"><?= ucfirst($a["status"]) ?></span></td>
                            <td>
                                <?php if ($a["confirmation_sent"]): ?>
                                    <span class="badge badge-sent" title="Confirmación enviada">✓ Conf.</span>
                                <?php endif; ?>
                                <?php if ($a["reminder_1day_sent"]): ?>
                                    <span class="badge badge-sent" title="Recordatorio 1 día">✓ 1d</span>
                                <?php endif; ?>
                                <?php if ($a["reminder_2hours_sent"]): ?>
                                    <span class="badge badge-sent" title="Recordatorio 2h">✓ 2h</span>
                                <?php endif; ?>
                                <?php if (
                                    !$a["confirmation_sent"] &&
                                    !$a["reminder_1day_sent"] &&
                                    !$a["reminder_2hours_sent"]
                                ): ?>
                                    <span style="color:var(--muted);font-size:.78rem">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $isPlaceholder =
                                    !empty($a["meet_link"]) &&
                                    preg_match(
                                        '#^https://meet\.google\.com/[a-z]{3}-[a-z]{4}-[a-z]{3}$#',
                                        $a["meet_link"],
                                    );
                                $isReal =
                                    !empty($a["meet_link"]) && !$isPlaceholder;
                                ?>
                                <?php if ($isReal): ?>
                                    <a href="<?= htmlspecialchars(
                                        $a["meet_link"],
                                    ) ?>" target="_blank" class="btn btn-sm btn-secondary" title="<?= htmlspecialchars(
    $a["meet_link"],
) ?>">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="15" height="10" rx="2"/><polygon points="17 9 22 5 22 19 17 15"/></svg>
                                        Meet
                                    </a>
                                <?php elseif ($isPlaceholder): ?>
                                    <span class="badge badge-pending" title="El enlace se enviará al cliente por WhatsApp el día de la cita">📅 Pendiente</span>
                                <?php else: ?>
                                    <span style="color:var(--muted);font-size:.78rem">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-actions">
                                <?php if ($a["status"] === "confirmed"): ?>
                                <form method="POST" style="display:inline" onsubmit="return confirm('¿Marcar como completada?')">
                                    <input type="hidden" name="action" value="complete">
                                    <input type="hidden" name="appointment_id" value="<?= $a[
                                        "id"
                                    ] ?>">
                                    <button type="submit" class="btn btn-sm btn-success">✓ Hecha</button>
                                </form>
                                <form method="POST" style="display:inline;margin-left:4px" onsubmit="return confirm('¿Cancelar esta cita? Se notificará al sistema.')">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="appointment_id" value="<?= $a[
                                        "id"
                                    ] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">✕ Cancelar</button>
                                </form>
                                <?php else: ?>
                                    <span style="color:var(--muted);font-size:.78rem"><?= ucfirst(
                                        $a["status"],
                                    ) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Paginación -->
            <?php if ($totalPages > 1): ?>
            <div class="card-body" style="border-top:1px solid var(--border)">
                <div class="pagination">
                    <?php
                    $baseUrl =
                        "?" .
                        http_build_query(
                            array_filter([
                                "q" => $search,
                                "status" => $filterStatus,
                                "date" => $filterDate,
                            ]),
                        );
                    for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a href="<?= $baseUrl ?>&page=<?= $p ?>" class="page-btn <?= $p ===
$currentPage
    ? "active"
    : "" ?>"><?= $p ?></a>
                    <?php endfor;
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
