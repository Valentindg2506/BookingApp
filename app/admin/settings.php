<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../includes/helpers.php";
require_once __DIR__ . "/../includes/auth_check.php";
require_once __DIR__ . "/../includes/settings.php";

$pdo = Database::getInstance()->getConnection();
Settings::load($pdo);

$msg = "";
$msgType = "success";
$activeTab = $_GET["tab"] ?? "business";

// ---- Guardar settings por grupo ----
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "save_business") {
        Settings::save($pdo, [
            "app_name" => sanitize($_POST["app_name"] ?? ""),
            "business_name" => sanitize($_POST["business_name"] ?? ""),
            "business_phone" => sanitize($_POST["business_phone"] ?? ""),
            "business_email" => sanitize($_POST["business_email"] ?? ""),
            "app_url" => sanitize($_POST["app_url"] ?? ""),
        ]);
        $msg = "Configuración del negocio guardada.";
        $activeTab = "business";
    } elseif ($action === "save_meta") {
        Settings::save($pdo, [
            "meta_verify_token" => sanitize($_POST["meta_verify_token"] ?? ""),
            "meta_app_secret" => sanitize($_POST["meta_app_secret"] ?? ""),
            "meta_page_access_token" => sanitize(
                $_POST["meta_page_access_token"] ?? "",
            ),
        ]);
        $msg = "Configuración de Meta guardada.";
        $activeTab = "meta";
    } elseif ($action === "save_google") {
        Settings::save($pdo, [
            "google_client_id" => sanitize($_POST["google_client_id"] ?? ""),
            "google_client_secret" => sanitize(
                $_POST["google_client_secret"] ?? "",
            ),
            "google_calendar_id" => sanitize(
                $_POST["google_calendar_id"] ?? "",
            ),
        ]);
        $msg = "Configuración de Google guardada.";
        $activeTab = "google";
    } elseif ($action === "save_twilio") {
        Settings::save($pdo, [
            "twilio_account_sid" => sanitize(
                $_POST["twilio_account_sid"] ?? "",
            ),
            "twilio_auth_token" => sanitize($_POST["twilio_auth_token"] ?? ""),
            "twilio_whatsapp_from" => sanitize(
                $_POST["twilio_whatsapp_from"] ?? "",
            ),
            "admin_whatsapp" => sanitize($_POST["admin_whatsapp"] ?? ""),
            "cron_secret_key" => sanitize($_POST["cron_secret_key"] ?? ""),
        ]);
        $msg = "Configuración de Twilio/WhatsApp guardada.";
        $activeTab = "twilio";
    } elseif ($action === "change_password") {
        $currentPw = $_POST["current_password"] ?? "";
        $newPw = $_POST["new_password"] ?? "";
        $confirmPw = $_POST["confirm_password"] ?? "";
        $st = $pdo->prepare("SELECT password FROM admin_users WHERE id = :id");
        $st->execute([":id" => $_SESSION["admin_id"]]);
        $user = $st->fetch();
        if (!$user || !password_verify($currentPw, $user["password"])) {
            $msg = "La contraseña actual es incorrecta.";
            $msgType = "error";
        } elseif (strlen($newPw) < 8) {
            $msg = "La nueva contraseña debe tener al menos 8 caracteres.";
            $msgType = "error";
        } elseif ($newPw !== $confirmPw) {
            $msg = "Las contraseñas no coinciden.";
            $msgType = "error";
        } else {
            $pdo->prepare(
                "UPDATE admin_users SET password = :p WHERE id = :id",
            )->execute([
                ":p" => password_hash($newPw, PASSWORD_BCRYPT),
                ":id" => $_SESSION["admin_id"],
            ]);
            $msg = "Contraseña actualizada correctamente.";
        }
        $activeTab = "security";
    }
}

// Cargar valores actuales para cada grupo
$biz = Settings::getGroup($pdo, "business");
$meta = Settings::getGroup($pdo, "meta");
$google = Settings::getGroup($pdo, "google");
$twilio = Settings::getGroup($pdo, "twilio");
$system = Settings::getGroup($pdo, "system");

// Logs de notificaciones (últimas 30 con paginación)
$logPage = max(1, (int) ($_GET["logpage"] ?? 1));
$logPer = 15;
$logOffset = ($logPage - 1) * $logPer;
$logTotal = (int) $pdo
    ->query("SELECT COUNT(*) FROM notification_logs")
    ->fetchColumn();
$logPages = (int) ceil($logTotal / $logPer);
$logStmt = $pdo->prepare(
    "SELECT nl.*, a.full_name, a.appointment_date, a.appointment_time
     FROM notification_logs nl
     JOIN appointments a ON a.id = nl.appointment_id
     ORDER BY nl.created_at DESC
     LIMIT {$logPer} OFFSET {$logOffset}",
);
$logStmt->execute();
$notifLogs = $logStmt->fetchAll();

$tabs = [
    "business" => ["icon" => "🏢", "label" => "Negocio"],
    "meta" => ["icon" => "📘", "label" => "Meta / Facebook"],
    "google" => ["icon" => "📅", "label" => "Google"],
    "twilio" => ["icon" => "💬", "label" => "Twilio / WhatsApp"],
    "security" => ["icon" => "🔐", "label" => "Seguridad"],
    "logs" => ["icon" => "📨", "label" => "Logs"],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración – <?= htmlspecialchars(
        Settings::get("app_name", APP_NAME),
    ) ?> Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . "/partials/admin_styles.php"; ?>
    <style>
        .tabs-nav { display:flex; gap:4px; flex-wrap:wrap; margin-bottom:24px; background:#fff; padding:6px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,.06); }
        .tab-btn { display:flex; align-items:center; gap:6px; padding:9px 16px; border:none; background:none; border-radius:8px; font-size:.875rem; font-weight:500; font-family:inherit; cursor:pointer; color:var(--muted); transition:all .2s; }
        .tab-btn:hover { background:var(--bg); color:var(--text); }
        .tab-btn.active { background:var(--blue); color:#fff; font-weight:600; }
        .tab-content { display:none; }
        .tab-content.active { display:block; }
        .settings-info { background:#fdf8ec; border-left:4px solid var(--cyan); border-radius:8px; padding:12px 16px; font-size:.83rem; color:#7a5c1e; margin-bottom:20px; }
        .field-hint { font-size:.75rem; color:var(--muted); margin-top:4px; }
        .secret-field { position:relative; }
        .secret-field input { padding-right:44px; }
        .secret-toggle { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--muted); padding:4px; }
        .copy-btn { display:inline-flex; align-items:center; gap:4px; font-size:.75rem; color:var(--blue); background:none; border:none; cursor:pointer; padding:2px 6px; border-radius:4px; }
        .copy-btn:hover { background:var(--bg); }
        code.url-box { display:block; background:var(--bg); padding:10px 14px; border-radius:8px; font-size:.78rem; word-break:break-all; margin-top:6px; }
    </style>
</head>
<body>
<?php include __DIR__ . "/partials/sidebar.php"; ?>
<div class="main-content">
    <?php include __DIR__ . "/partials/topbar.php"; ?>
    <div class="page-body">
        <div class="page-header">
            <h1>Configuración</h1>
            <p>Gestiona todas las integraciones y ajustes de la aplicación.</p>
        </div>

        <?php if ($msg): ?>
        <div class="alert-<?= $msgType ?>" style="margin-bottom:20px"><?= $msgType ===
"success"
    ? "✅"
    : "⚠️" ?> <?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <!-- Tabs nav -->
        <div class="tabs-nav">
            <?php foreach ($tabs as $key => $tab): ?>
            <button type="button" class="tab-btn <?= $activeTab === $key
                ? "active"
                : "" ?>" onclick="showTab('<?= $key ?>')">
                <?= $tab["icon"] ?> <?= $tab["label"] ?>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- TAB: Negocio -->
        <div class="tab-content <?= $activeTab === "business"
            ? "active"
            : "" ?>" id="tab-business">
            <div class="card">
                <div class="card-header"><h2>🏢 Datos del negocio</h2></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_business">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nombre de la app</label>
                                <input type="text" name="app_name" value="<?= htmlspecialchars(
                                    $biz["app_name"] ??
                                        Settings::get("app_name", APP_NAME),
                                ) ?>">
                            </div>
                            <div class="form-group">
                                <label>Nombre del negocio</label>
                                <input type="text" name="business_name" value="<?= htmlspecialchars(
                                    $biz["business_name"] ??
                                        Settings::get(
                                            "business_name",
                                            BUSINESS_NAME,
                                        ),
                                ) ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Teléfono de contacto</label>
                                <input type="text" name="business_phone" value="<?= htmlspecialchars(
                                    $biz["business_phone"] ??
                                        Settings::get(
                                            "business_phone",
                                            BUSINESS_PHONE,
                                        ),
                                ) ?>" placeholder="+34600000000">
                            </div>
                            <div class="form-group">
                                <label>Email de contacto</label>
                                <input type="email" name="business_email" value="<?= htmlspecialchars(
                                    $biz["business_email"] ??
                                        Settings::get("business_email", ""),
                                ) ?>" placeholder="info@tudominio.com">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>URL de la aplicación</label>
                            <input type="text" name="app_url" value="<?= htmlspecialchars(
                                $biz["app_url"] ??
                                    Settings::get("app_url", APP_URL),
                            ) ?>" placeholder="https://tudominio.com/app">
                            <p class="field-hint">URL base sin barra final. Afecta a los redirects y enlaces.</p>
                        </div>
                        <button type="submit" class="btn btn-primary">💾 Guardar datos del negocio</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB: Meta -->
        <div class="tab-content <?= $activeTab === "meta"
            ? "active"
            : "" ?>" id="tab-meta">
            <div class="card">
                <div class="card-header"><h2>📘 Meta / Facebook Ads</h2></div>
                <div class="card-body">
                    <div class="settings-info">
                        ℹ️ Configura aquí las credenciales de tu app de Meta para recibir leads automáticamente vía webhook.
                        <br>URL del webhook: <strong><?= htmlspecialchars(
                            Settings::get("app_url", APP_URL),
                        ) ?>/webhook.php</strong>
                        <button class="copy-btn" onclick="copyText('<?= htmlspecialchars(
                            Settings::get("app_url", APP_URL),
                        ) ?>/webhook.php')">📋 Copiar</button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="save_meta">
                        <div class="form-group">
                            <label>Token de verificación del webhook</label>
                            <input type="text" name="meta_verify_token" value="<?= htmlspecialchars(
                                $meta["meta_verify_token"] ?? "",
                            ) ?>" placeholder="mi_token_secreto_aqui">
                            <p class="field-hint">El mismo token que introduces en el panel de Meta al configurar el webhook.</p>
                        </div>
                        <div class="form-group">
                            <label>App Secret</label>
                            <div class="secret-field">
                                <input type="password" id="meta_app_secret" name="meta_app_secret" value="<?= htmlspecialchars(
                                    $meta["meta_app_secret"] ?? "",
                                ) ?>" placeholder="••••••••••••••••">
                                <button type="button" class="secret-toggle" onclick="toggleSecret('meta_app_secret')">👁</button>
                            </div>
                            <p class="field-hint">Encuéntralo en Meta for Developers → Tu App → Configuración → Información básica.</p>
                        </div>
                        <div class="form-group">
                            <label>Page Access Token</label>
                            <div class="secret-field">
                                <input type="password" id="meta_page_token" name="meta_page_access_token" value="<?= htmlspecialchars(
                                    $meta["meta_page_access_token"] ?? "",
                                ) ?>" placeholder="••••••••••••••••">
                                <button type="button" class="secret-toggle" onclick="toggleSecret('meta_page_token')">👁</button>
                            </div>
                            <p class="field-hint">Token de acceso a la página de Facebook para consultar los datos del lead.</p>
                        </div>
                        <button type="submit" class="btn btn-primary">💾 Guardar configuración Meta</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB: Google -->
        <div class="tab-content <?= $activeTab === "google"
            ? "active"
            : "" ?>" id="tab-google">
            <div class="card">
                <div class="card-header"><h2>📅 Google Calendar & Meet</h2></div>
                <div class="card-body">
                    <div class="settings-info">
                        ℹ️ Configura las credenciales OAuth 2.0 de Google para crear eventos de Google Meet automáticamente.
                        <br>URI de redirección OAuth:
                        <code class="url-box"><?= htmlspecialchars(
                            Settings::get("app_url", APP_URL),
                        ) ?>/admin/google_callback.php</code>
                        <button class="copy-btn" onclick="copyText('<?= htmlspecialchars(
                            Settings::get("app_url", APP_URL),
                        ) ?>/admin/google_callback.php')">📋 Copiar</button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="save_google">
                        <div class="form-group">
                            <label>Client ID</label>
                            <input type="text" name="google_client_id" value="<?= htmlspecialchars(
                                $google["google_client_id"] ?? "",
                            ) ?>" placeholder="xxxxxxxxx.apps.googleusercontent.com">
                        </div>
                        <div class="form-group">
                            <label>Client Secret</label>
                            <div class="secret-field">
                                <input type="password" id="google_secret" name="google_client_secret" value="<?= htmlspecialchars(
                                    $google["google_client_secret"] ?? "",
                                ) ?>" placeholder="GOCSPX-••••••••••">
                                <button type="button" class="secret-toggle" onclick="toggleSecret('google_secret')">👁</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Calendar ID</label>
                            <input type="text" name="google_calendar_id" value="<?= htmlspecialchars(
                                $google["google_calendar_id"] ?? "primary",
                            ) ?>" placeholder="primary">
                            <p class="field-hint">Usa "primary" para el calendario principal o el ID del calendario específico.</p>
                        </div>
                        <button type="submit" class="btn btn-primary">💾 Guardar configuración Google</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB: Twilio -->
        <div class="tab-content <?= $activeTab === "twilio"
            ? "active"
            : "" ?>" id="tab-twilio">
            <div class="card">
                <div class="card-header"><h2>💬 Twilio / WhatsApp</h2></div>
                <div class="card-body">
                    <div class="settings-info">
                        ℹ️ Configura tu cuenta de Twilio para el envío de mensajes WhatsApp. Necesitas una cuenta en <a href="https://twilio.com" target="_blank" style="color:var(--blue)">twilio.com</a> con WhatsApp habilitado.
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="save_twilio">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Account SID</label>
                                <input type="text" name="twilio_account_sid" value="<?= htmlspecialchars(
                                    $twilio["twilio_account_sid"] ?? "",
                                ) ?>" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                            </div>
                            <div class="form-group">
                                <label>Auth Token</label>
                                <div class="secret-field">
                                    <input type="password" id="twilio_token" name="twilio_auth_token" value="<?= htmlspecialchars(
                                        $twilio["twilio_auth_token"] ?? "",
                                    ) ?>" placeholder="••••••••••••••••••••••••••••••••">
                                    <button type="button" class="secret-toggle" onclick="toggleSecret('twilio_token')">👁</button>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Número WhatsApp remitente</label>
                                <input type="text" name="twilio_whatsapp_from" value="<?= htmlspecialchars(
                                    $twilio["twilio_whatsapp_from"] ??
                                        "whatsapp:+14155238886",
                                ) ?>" placeholder="whatsapp:+14155238886">
                                <p class="field-hint">Formato: whatsapp:+XXXXXXXXXXX</p>
                            </div>
                            <div class="form-group">
                                <label>Tu WhatsApp (admin)</label>
                                <input type="text" name="admin_whatsapp" value="<?= htmlspecialchars(
                                    $twilio["admin_whatsapp"] ?? "",
                                ) ?>" placeholder="whatsapp:+34600000000">
                                <p class="field-hint">Recibirás los recordatorios de tus citas en este número.</p>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Clave secreta del cron</label>
                            <div class="secret-field">
                                <input type="text" id="cron_key" name="cron_secret_key" value="<?= htmlspecialchars(
                                    $system["cron_secret_key"] ??
                                        "CAMBIA_ESTA_CLAVE",
                                ) ?>">
                                <button type="button" class="secret-toggle" onclick="generateCronKey()">🔄</button>
                            </div>
                            <p class="field-hint">Clave para ejecutar el cron de recordatorios desde el navegador. URL del cron:</p>
                            <code class="url-box"><?= htmlspecialchars(
                                Settings::get("app_url", APP_URL),
                            ) ?>/cron/reminders.php?cron_key=<span id="cronKeyPreview"><?= htmlspecialchars(
    $system["cron_secret_key"] ?? "CAMBIA_ESTA_CLAVE",
) ?></span></code>
                        </div>
                        <button type="submit" class="btn btn-primary">💾 Guardar configuración Twilio</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB: Seguridad -->
        <div class="tab-content <?= $activeTab === "security"
            ? "active"
            : "" ?>" id="tab-security">
            <div class="two-col" style="gap:24px;align-items:start">
                <div class="card">
                    <div class="card-header"><h2>🔐 Cambiar contraseña</h2></div>
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
                <div class="card">
                    <div class="card-header"><h2>ℹ️ Información del sistema</h2></div>
                    <div class="card-body">
                        <table class="mini-table">
                            <tr><td style="color:var(--muted);width:50%">Aplicación</td><td><strong><?= htmlspecialchars(
                                Settings::get("app_name", APP_NAME),
                            ) ?></strong></td></tr>
                            <tr><td style="color:var(--muted)">URL</td><td style="font-size:.82rem"><?= htmlspecialchars(
                                Settings::get("app_url", APP_URL),
                            ) ?></td></tr>
                            <tr><td style="color:var(--muted)">Negocio</td><td><?= htmlspecialchars(
                                Settings::get("business_name", BUSINESS_NAME),
                            ) ?></td></tr>
                            <tr><td style="color:var(--muted)">PHP</td><td><?= phpversion() ?></td></tr>
                            <tr><td style="color:var(--muted)">Servidor</td><td><?= $_SERVER[
                                "SERVER_SOFTWARE"
                            ] ?? "N/A" ?></td></tr>
                            <tr><td style="color:var(--muted)">Zona horaria</td><td><?= APP_TIMEZONE ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: Logs -->
        <div class="tab-content <?= $activeTab === "logs"
            ? "active"
            : "" ?>" id="tab-logs">
            <div class="card">
                <div class="card-header">
                    <h2>📨 Log de notificaciones WhatsApp</h2>
                    <span style="font-size:.82rem;color:var(--muted)"><?= $logTotal ?> registros en total</span>
                </div>
                <div class="card-body" style="padding:0;overflow-x:auto">
                    <?php if (empty($notifLogs)): ?>
                        <p class="empty-msg" style="padding:20px">No hay notificaciones registradas aún.</p>
                    <?php else: ?>
                    <table class="data-table">
                        <thead><tr><th>#Cita</th><th>Cliente</th><th>Tipo</th><th>Teléfono</th><th>Estado</th><th>Enviado</th><th>Error</th></tr></thead>
                        <tbody>
                        <?php
                        $typeLabels = [
                            "confirmation" => "✅ Confirmación",
                            "reminder_1day" => "📅 1 día antes",
                            "reminder_2hours" => "⏰ 1h antes",
                            "reminder_15min" => "⚡ 15 min antes",
                        ];
                        foreach ($notifLogs as $log): ?>
                            <tr>
                                <td>#<?= $log["appointment_id"] ?></td>
                                <td>
                                    <?= htmlspecialchars($log["full_name"]) ?>
                                    <div style="font-size:.78rem;color:var(--muted)"><?= formatDate(
                                        $log["appointment_date"],
                                    ) ?> <?= formatTime(
     $log["appointment_time"],
 ) ?></div>
                                </td>
                                <td><?= $typeLabels[$log["type"]] ??
                                    htmlspecialchars($log["type"]) ?></td>
                                <td style="font-size:.82rem"><?= htmlspecialchars(
                                    $log["whatsapp_to"],
                                ) ?></td>
                                <td><span class="badge badge-<?= $log[
                                    "status"
                                ] ?>"><?= ucfirst($log["status"]) ?></span></td>
                                <td style="font-size:.78rem;color:var(--muted)"><?= $log[
                                    "sent_at"
                                ]
                                    ? date(
                                        "d/m H:i",
                                        strtotime($log["sent_at"]),
                                    )
                                    : "—" ?></td>
                                <td style="font-size:.78rem;color:#e53935;max-width:180px"><?= htmlspecialchars(
                                    $log["error_message"] ?? "—",
                                ) ?></td>
                            </tr>
                        <?php endforeach;
                        ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
                <?php if ($logPages > 1): ?>
                <div class="card-body" style="border-top:1px solid var(--border)">
                    <div class="pagination">
                        <?php for ($p = 1; $p <= $logPages; $p++): ?>
                            <a href="?tab=logs&logpage=<?= $p ?>" class="page-btn <?= $p ===
$logPage
    ? "active"
    : "" ?>"><?= $p ?></a>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- page-body -->
</div><!-- main-content -->

<script>
function showTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    document.querySelector(`[onclick="showTab('${tab}')"]`).classList.add('active');
}
function toggleSecret(id) {
    const el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
}
function copyText(text) {
    navigator.clipboard.writeText(text).then(() => {
        const btn = event.target;
        const orig = btn.textContent;
        btn.textContent = '✅ Copiado';
        setTimeout(() => btn.textContent = orig, 2000);
    });
}
function generateCronKey() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let key = '';
    for (let i = 0; i < 32; i++) key += chars.charAt(Math.floor(Math.random() * chars.length));
    document.getElementById('cron_key').value = key;
    document.getElementById('cronKeyPreview').textContent = key;
}
</script>
</body>
</html>
