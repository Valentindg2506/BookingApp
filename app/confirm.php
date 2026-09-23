<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/includes/helpers.php";

session_name(SESSION_NAME);
session_start();

$id = (int) ($_GET["id"] ?? 0);
$token = sanitize($_GET["token"] ?? "");

// Verificar token de sesión
if (
    $id <= 0 ||
    empty($token) ||
    ($_SESSION["booking_" . $id] ?? "") !== $token
) {
    redirect(APP_URL . "/index.php");
}

// Cargar datos de la cita
$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->prepare(
    "SELECT * FROM appointments WHERE id = :id AND status != 'cancelled' LIMIT 1",
);
$stmt->execute([":id" => $id]);
$appt = $stmt->fetch();

if (!$appt) {
    redirect(APP_URL . "/index.php");
}

// Construir datos para Google Calendar add-to-calendar
$gcTitle = urlencode("Reunión con " . BUSINESS_NAME);
$gcLocation = urlencode(
    "Google Meet (enlace enviado por WhatsApp el día de la reunión)",
);
$gcDetails = urlencode(
    "Reunión agendada con " .
        BUSINESS_NAME .
        "\n" .
        "El enlace de acceso a Google Meet te será enviado por WhatsApp el mismo día de la reunión." .
        "\n\n" .
        "Contacto: " .
        BUSINESS_PHONE,
);

// Fechas en formato UTC para Google Calendar (YYYYMMDDTHHmmssZ)
$apptTimestamp = strtotime(
    $appt["appointment_date"] . " " . $appt["appointment_time"],
);
$gcStart = gmdate("Ymd\THis\Z", $apptTimestamp);
$gcEnd = gmdate("Ymd\THis\Z", $apptTimestamp + 3600);

$gcCal =
    "https://calendar.google.com/calendar/render?action=TEMPLATE" .
    "&text=" .
    $gcTitle .
    "&dates=" .
    $gcStart .
    "/" .
    $gcEnd .
    "&details=" .
    $gcDetails .
    "&location=" .
    $gcLocation .
    "&sf=true&output=xml";

// Formatear fecha larga en español
$ts = strtotime($appt["appointment_date"]);
$dayName = getDayName((int) date("w", $ts));
$day = date("j", $ts);
$month = getMonthName((int) date("n", $ts));
$year = date("Y", $ts);
$longDate = "{$dayName}, {$day} de {$month} de {$year}";
$time = formatTime($appt["appointment_time"]);

// Ocultar teléfono parcialmente
$phoneMasked =
    substr($appt["phone"], 0, 3) .
    str_repeat("*", max(0, strlen($appt["phone"]) - 5)) .
    substr($appt["phone"], -2);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserva Confirmada – <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1b3a2d 0%, #2d6a4f 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: #fff;
            border-radius: 20px;
            padding: 48px 40px;
            max-width: 520px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,.25);
        }
        /* Círculo check animado */
        .check-circle {
            width: 90px; height: 90px;
            background: linear-gradient(135deg, #2d6a4f, #c9a84c);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 24px;
            animation: pop .5s cubic-bezier(.36,1.56,.64,1) forwards;
        }
        @keyframes pop {
            0%   { transform: scale(0); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .check-circle svg { width: 46px; height: 46px; }
        h1 { font-size: 1.75rem; font-weight: 700; color: #1b3a2d; margin-bottom: 8px; }
        .subtitle { color: #607d8b; font-size: .95rem; margin-bottom: 32px; }

        /* Detalles de la cita */
        .details {
            background: #f2f7f4;
            border-radius: 12px;
            padding: 20px;
            text-align: left;
            margin-bottom: 24px;
        }
        .detail-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #e2ddd5;
            font-size: .9rem;
            color: #263238;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-row .icon { font-size: 1.2rem; flex-shrink: 0; }
        .detail-row strong { display: block; font-size: .75rem; color: #90a4ae; font-weight: 500; text-transform: uppercase; letter-spacing: .05em; }

        /* Avisos */
        .notice {
            background: #e8f5e9;
            border-left: 4px solid #43a047;
            border-radius: 8px;
            padding: 12px 16px;
            text-align: left;
            font-size: .85rem;
            color: #2e7d32;
            margin-bottom: 16px;
        }
        .notice-wa {
            background: #dff0e8;
            border-left: 4px solid #2d6a4f;
            color: #1b3a2d;
        }

        /* Botones */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            font-size: .95rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: opacity .2s, transform .1s;
            margin-bottom: 12px;
            border: none;
        }
        .btn:active { transform: scale(.98); }
        .btn-meet {
            background: linear-gradient(135deg, #2d6a4f, #c9a84c);
            color: #fff;
        }
        .btn-gcal {
            background: #fff;
            color: #2d6a4f;
            border: 2px solid #2d6a4f;
        }
        .btn:hover { opacity: .88; }

        .footer-note {
            margin-top: 24px;
            font-size: .8rem;
            color: #b0bec5;
        }
        @media (max-width: 480px) {
            .card { padding: 32px 20px; }
            h1 { font-size: 1.4rem; }
        }
    </style>
</head>
<body>
<div class="card">
    <div class="check-circle">
        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"/>
        </svg>
    </div>

    <h1>¡Reserva Confirmada!</h1>
    <p class="subtitle">Tu reunión ha sido agendada correctamente.</p>

    <div class="details">
        <div class="detail-row">
            <span class="icon">👤</span>
            <div><strong>Nombre</strong><?= htmlspecialchars(
                $appt["full_name"],
            ) ?></div>
        </div>
        <div class="detail-row">
            <span class="icon">📅</span>
            <div><strong>Fecha</strong><?= $longDate ?></div>
        </div>
        <div class="detail-row">
            <span class="icon">🕐</span>
            <div><strong>Hora</strong><?= $time ?></div>
        </div>
        <div class="detail-row">
            <span class="icon">🎥</span>
            <div><strong>Acceso a la reunión</strong>El enlace de Google Meet se enviará por WhatsApp el día de la cita</div>
        </div>
    </div>

    <div class="notice notice-wa">
        📱 Hemos enviado la confirmación por WhatsApp al número <strong><?= $phoneMasked ?></strong>.<br>
        Recibirás recordatorios <strong>1 día antes</strong> y el enlace de acceso <strong>1 hora antes</strong> de tu cita.
    </div>

    <div class="notice">
        ✅ Si necesitas cancelar o cambiar tu cita, responde al mensaje de WhatsApp o contáctanos directamente.
    </div>



    <a href="<?= $gcCal ?>" target="_blank" rel="noopener" class="btn btn-gcal">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Añadir a Google Calendar
    </a>

    <p class="footer-note"><?= BUSINESS_NAME ?> · <?= BUSINESS_PHONE ?></p>
</div>
</body>
</html>
