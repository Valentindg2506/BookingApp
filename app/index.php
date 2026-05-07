<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/includes/helpers.php";

session_name(SESSION_NAME);
session_start();
$csrf = csrf_token();

$error = $_GET["error"] ?? "";
$errorMessages = [
    "validation" => "Por favor, completa todos los campos correctamente.",
    "slot_taken" => "Ese horario ya fue reservado. Elige otro.",
    "date_past" => "No puedes reservar en una fecha pasada.",
    "db" => "Error al guardar tu reserva. Intenta de nuevo.",
    "csrf" => "Sesión expirada. Recarga la página.",
];
$errorMsg = $errorMessages[$error] ?? "";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> – Reserva tu reunión</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --blue-dark: #1b3a2d;
            --blue:      #2d6a4f;
            --cyan:      #c9a84c;
            --bg:        #f5f5f0;
            --white:     #ffffff;
            --text:      #1c1c1c;
            --muted:     #6b7280;
            --border:    #e2ddd5;
            --radius:    12px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        /* ---- HERO ---- */
        .hero {
            background: linear-gradient(135deg, var(--blue-dark) 0%, var(--blue) 100%);
            color: #fff;
            padding: 60px 20px 80px;
            text-align: center;
        }
        .hero-logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 32px;
            opacity: .95;
        }
        .hero h1 {
            font-size: clamp(1.8rem, 5vw, 2.8rem);
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 16px;
        }
        .hero p {
            font-size: 1.05rem;
            opacity: .85;
            max-width: 480px;
            margin: 0 auto 40px;
        }
        .benefits {
            display: flex;
            justify-content: center;
            gap: 24px;
            flex-wrap: wrap;
        }
        .benefit {
            background: rgba(255,255,255,.12);
            border-radius: 10px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: .9rem;
            font-weight: 500;
            min-width: 160px;
        }
        .benefit svg { flex-shrink: 0; }

        /* ---- FORM WRAPPER ---- */
        .form-wrapper {
            max-width: 580px;
            margin: -40px auto 60px;
            padding: 0 16px;
        }
        .form-card {
            background: var(--white);
            border-radius: 20px;
            padding: 40px 36px;
            box-shadow: 0 12px 40px rgba(45,106,79,.15);
        }

        /* ---- PROGRESS BAR ---- */
        .progress-bar {
            display: flex;
            align-items: center;
            margin-bottom: 32px;
            gap: 0;
        }
        .step-dot {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: var(--border);
            color: var(--muted);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .8rem;
            font-weight: 700;
            flex-shrink: 0;
            transition: all .3s;
            position: relative;
            z-index: 1;
        }
        .step-dot.active   { background: var(--blue); color: #fff; }
        .step-dot.done     { background: var(--cyan); color: #fff; }
        .step-line {
            flex: 1;
            height: 3px;
            background: var(--border);
            transition: background .3s;
        }
        .step-line.done { background: var(--cyan); }
        .step-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 6px;
            margin-bottom: 24px;
        }
        .step-label { font-size: .72rem; color: var(--muted); text-align: center; flex: 1; }
        .step-label:first-child { text-align: left; }
        .step-label:last-child  { text-align: right; }

        /* ---- STEPS ---- */
        .step { display: none; animation: fadeIn .3s ease; }
        .step.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }

        .step h2 { font-size: 1.25rem; font-weight: 700; color: var(--blue-dark); margin-bottom: 20px; }

        /* ---- INPUTS ---- */
        .field { margin-bottom: 16px; }
        .field label { display: block; font-size: .82rem; font-weight: 600; color: var(--muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .04em; }
        .field input {
            width: 100%;
            padding: 13px 16px;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-size: .95rem;
            font-family: inherit;
            color: var(--text);
            transition: border-color .2s;
            outline: none;
        }
        .field input:focus { border-color: var(--blue); }
        .field input.error-field { border-color: #e53935; }

        /* ---- CALENDAR ---- */
        .cal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .cal-header h3 { font-size: 1.05rem; font-weight: 700; color: var(--blue-dark); }
        .cal-nav {
            background: none;
            border: 2px solid var(--border);
            border-radius: 8px;
            width: 36px; height: 36px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: border-color .2s;
        }
        .cal-nav:hover { border-color: var(--blue); }
        .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
        .cal-dow {
            text-align: center;
            font-size: .72rem;
            font-weight: 700;
            color: var(--muted);
            padding: 6px 0;
            text-transform: uppercase;
        }
        .day-btn {
            aspect-ratio: 1;
            border: none;
            background: none;
            border-radius: 8px;
            font-size: .85rem;
            font-family: inherit;
            font-weight: 500;
            cursor: pointer;
            transition: all .2s;
            color: var(--text);
        }
        .day-btn:hover:not(.disabled):not(.empty) { background: #eaf4ee; }
        .day-btn.available:not(.disabled) { font-weight: 600; color: var(--blue); }
        .day-btn.selected { background: var(--cyan) !important; color: #fff !important; }
        .day-btn.disabled, .day-btn.past { opacity: .35; cursor: not-allowed; color: var(--muted); }
        .day-btn.today { border: 2px solid var(--blue); }
        .day-btn.empty { cursor: default; }
        .cal-loading { text-align: center; color: var(--muted); padding: 30px 0; font-size: .9rem; }

        /* ---- TIME SLOTS ---- */
        .selected-date-label {
            background: #edf7f1;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: .9rem;
            color: var(--blue);
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .slots-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        .slot-btn {
            padding: 12px 8px;
            border: 2px solid var(--border);
            border-radius: 10px;
            background: #fff;
            font-size: .9rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            color: var(--blue);
            transition: all .2s;
        }
        .slot-btn:hover  { border-color: var(--blue); background: #eaf4ee; }
        .slot-btn.selected { background: var(--blue); color: #fff; border-color: var(--blue); }
        .slots-loading { text-align: center; color: var(--muted); padding: 20px 0; font-size: .9rem; }
        .no-slots { text-align: center; color: #e53935; padding: 20px 0; font-size: .9rem; }

        /* ---- SUMMARY ---- */
        .summary-box {
            background: #f2f7f4;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .summary-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
            font-size: .9rem;
            border-bottom: 1px solid var(--border);
        }
        .summary-row:last-child { border-bottom: none; }
        .summary-row span.lbl { font-size: .75rem; color: var(--muted); font-weight: 600; text-transform: uppercase; display: block; }
        .terms-check { display: flex; align-items: flex-start; gap: 10px; font-size: .85rem; color: var(--muted); margin-bottom: 20px; cursor: pointer; }
        .terms-check input { margin-top: 2px; }

        /* ---- PHONE SELECTOR ---- */
        .phone-field-wrap {
            position: relative;
        }
        .phone-wrapper {
            display: flex;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            background: #fff;
            transition: border-color .2s;
        }
        .phone-wrapper:focus-within { border-color: var(--blue); }
        .phone-wrapper.error-field  { border-color: #e53935; }
        .phone-flag-btn {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 0 10px 0 14px;
            background: #f7f5f0;
            border: none;
            border-right: 2px solid var(--border);
            border-radius: calc(var(--radius) - 2px) 0 0 calc(var(--radius) - 2px);
            cursor: pointer;
            font-size: .92rem;
            font-family: inherit;
            color: var(--text);
            white-space: nowrap;
            outline: none;
            height: 48px;
            transition: background .2s;
            flex-shrink: 0;
        }
        .phone-flag-btn:hover { background: #ede9e0; }
        .phone-flag-btn .btn-flag  { font-size: 1.25rem; line-height: 1; }
        .phone-flag-btn .btn-dial  { font-weight: 600; font-size: .88rem; color: var(--text); }
        .phone-flag-btn .btn-arrow { opacity: .45; transition: transform .2s; }
        .phone-flag-btn.open .btn-arrow { transform: rotate(180deg); }
        .phone-number-input {
            flex: 1;
            padding: 0 14px;
            height: 48px;
            border: none;
            background: transparent;
            font-size: .95rem;
            font-family: inherit;
            color: var(--text);
            outline: none;
            min-width: 0;
            border-radius: 0 calc(var(--radius) - 2px) calc(var(--radius) - 2px) 0;
        }
        /* Dropdown */
        .phone-dropdown {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            width: 100%;
            background: #fff;
            border: 2px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 12px 32px rgba(0,0,0,.13);
            z-index: 1000;
            overflow: hidden;
            opacity: 0;
            transform: translateY(-6px);
            pointer-events: none;
            transition: opacity .18s ease, transform .18s ease;
        }
        .phone-dropdown.open {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }
        .phone-search-wrap {
            padding: 10px 10px 8px;
            border-bottom: 1px solid var(--border);
            background: #faf9f6;
        }
        .phone-dropdown-search {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: .85rem;
            font-family: inherit;
            color: var(--text);
            background: #fff;
            outline: none;
            transition: border-color .2s;
        }
        .phone-dropdown-search:focus { border-color: var(--blue); }
        .phone-options-list {
            max-height: 240px;
            overflow-y: auto;
            padding: 6px 0;
        }
        .phone-options-list::-webkit-scrollbar { width: 5px; }
        .phone-options-list::-webkit-scrollbar-track { background: transparent; }
        .phone-options-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
        .phone-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 14px;
            cursor: pointer;
            font-size: .88rem;
            color: var(--text);
            transition: background .12s;
            user-select: none;
        }
        .phone-option:hover    { background: #f2f7f4; }
        .phone-option.selected { background: #e6f2eb; }
        .phone-option .opt-flag { font-size: 1.3rem; flex-shrink: 0; line-height: 1; }
        .phone-option .opt-name { flex: 1; }
        .phone-option .opt-dial { color: var(--muted); font-size: .8rem; font-weight: 600; }

        /* ---- BUTTONS ---- */
        .btn-row { display: flex; gap: 12px; margin-top: 8px; }
        .btn-next, .btn-back, .btn-submit {
            flex: 1;
            padding: 14px;
            border-radius: var(--radius);
            font-size: .95rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: opacity .2s, transform .1s;
            border: none;
        }
        .btn-next, .btn-submit {
            background: linear-gradient(135deg, var(--blue), var(--cyan));
            color: #fff;
        }
        .btn-back {
            background: #fff;
            color: var(--blue);
            border: 2px solid var(--border);
        }
        .btn-next:hover, .btn-submit:hover { opacity: .88; }
        .btn-next:active, .btn-submit:active { transform: scale(.98); }
        .btn-submit:disabled { opacity: .55; cursor: not-allowed; }

        /* ---- ALERT ---- */
        .alert-error {
            background: #fdecea;
            border-left: 4px solid #e53935;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: .88rem;
            color: #b71c1c;
            margin-bottom: 20px;
        }

        /* ---- FOOTER ---- */
        footer { text-align: center; padding: 24px; font-size: .82rem; color: var(--muted); }

        @media (max-width: 480px) {
            .form-card { padding: 28px 20px; }
            .benefits { flex-direction: column; align-items: center; }
            .slots-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<!-- HERO -->
<section class="hero">
    <div class="hero-logo">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <?= htmlspecialchars(APP_NAME) ?>
    </div>
    <h1>Reserva tu reunión<br>en minutos</h1>
    <p>Elige el día y la hora que mejor te venga. Sin esperas, sin llamadas.</p>
    <div class="benefits">
        <div class="benefit">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#00bcd4" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Sin esperas
        </div>
        <div class="benefit">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#00bcd4" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            Confirmación inmediata
        </div>
        <div class="benefit">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#00bcd4" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.63 3.48 2 2 0 0 1 3.6 1.27h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.91a16 16 0 0 0 6 6l.86-.86a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21.73 16.92z"/></svg>
            Recordatorio WhatsApp
        </div>
    </div>
</section>

<!-- FORM -->
<div class="form-wrapper">
    <div class="form-card">

        <?php if ($errorMsg): ?>
        <div class="alert-error">⚠️ <?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>

        <!-- Progress -->
        <div class="progress-bar" id="progressBar">
            <div class="step-dot active" id="dot-1">1</div>
            <div class="step-line" id="line-1"></div>
            <div class="step-dot" id="dot-2">2</div>
            <div class="step-line" id="line-2"></div>
            <div class="step-dot" id="dot-3">3</div>
            <div class="step-line" id="line-3"></div>
            <div class="step-dot" id="dot-4">4</div>
        </div>
        <div class="step-labels">
            <span class="step-label">Tus datos</span>
            <span class="step-label">Día</span>
            <span class="step-label">Hora</span>
            <span class="step-label">Confirmar</span>
        </div>

        <form id="bookingForm" method="POST" action="process_booking.php">
            <input type="hidden" name="csrf_token"    value="<?= $csrf ?>">
            <input type="hidden" name="meta_lead_id"  id="metaLeadId" value="">
            <input type="hidden" name="selected_date" id="selectedDate" value="">
            <input type="hidden" name="selected_time" id="selectedTime" value="">

            <!-- STEP 1: Datos personales -->
            <div class="step active" id="step-1">
                <h2>👤 Tus datos</h2>
                <div class="field">
                    <label>Nombre completo</label>
                    <input type="text" name="full_name" id="inputName" placeholder="Ej: Ana García López" autocomplete="name" required>
                </div>
                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" id="inputEmail" placeholder="tu@email.com" autocomplete="email" required>
                </div>
                <div class="field">
                    <label>Teléfono</label>
                    <input type="hidden" name="phone" id="inputPhone">
                    <div class="phone-field-wrap">
                        <div class="phone-wrapper" id="phoneWrapper">
                            <button type="button" class="phone-flag-btn" id="phoneFlagBtn" onclick="toggleDropdown()">
                                <span class="btn-flag" id="selectedFlag">🇪🇸</span>
                                <span class="btn-dial" id="selectedDial">+34</span>
                                <svg class="btn-arrow" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <input type="tel" class="phone-number-input" id="phoneNumber" placeholder="612 345 678" autocomplete="tel" oninput="updatePhone()">
                        </div>
                        <div class="phone-dropdown" id="phoneDropdown">
                            <div class="phone-search-wrap">
                                <input type="text" class="phone-dropdown-search" placeholder="🔍 Buscar país…" id="countrySearch" oninput="filterCountries()" onclick="event.stopPropagation()" autocomplete="off">
                            </div>
                            <div class="phone-options-list" id="countryList"></div>
                        </div>
                    </div>
                </div>
                <div class="btn-row">
                    <button type="button" class="btn-next" onclick="nextStep(1)">Siguiente →</button>
                </div>
            </div>

            <!-- STEP 2: Elegir día -->
            <div class="step" id="step-2">
                <h2>📅 Elige un día</h2>
                <div class="cal-header">
                    <button type="button" class="cal-nav" onclick="changeMonth(-1)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <h3 id="calMonthLabel">–</h3>
                    <button type="button" class="cal-nav" onclick="changeMonth(1)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>
                <div class="cal-grid" id="calDaysOfWeek"></div>
                <div class="cal-grid" id="calGrid"><div class="cal-loading">Cargando disponibilidad…</div></div>
                <div class="btn-row" style="margin-top:20px">
                    <button type="button" class="btn-back" onclick="prevStep(2)">← Volver</button>
                    <button type="button" class="btn-next" id="btnNextStep2" onclick="nextStep(2)" style="opacity:.4;pointer-events:none">Siguiente →</button>
                </div>
            </div>

            <!-- STEP 3: Elegir hora -->
            <div class="step" id="step-3">
                <h2>🕐 Elige la hora</h2>
                <div class="selected-date-label" id="selectedDateLabel">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span id="selectedDateText">–</span>
                </div>
                <div id="slotsContainer"><div class="slots-loading">Cargando horarios…</div></div>
                <div class="btn-row" style="margin-top:20px">
                    <button type="button" class="btn-back" onclick="prevStep(3)">← Volver</button>
                    <button type="button" class="btn-next" id="btnNextStep3" onclick="nextStep(3)" style="opacity:.4;pointer-events:none">Siguiente →</button>
                </div>
            </div>

            <!-- STEP 4: Resumen -->
            <div class="step" id="step-4">
                <h2>✅ Confirma tu reserva</h2>
                <div class="summary-box">
                    <div class="summary-row">
                        <span style="font-size:1.2rem">👤</span>
                        <div><span class="lbl">Nombre</span><span id="summName">–</span></div>
                    </div>
                    <div class="summary-row">
                        <span style="font-size:1.2rem">✉️</span>
                        <div><span class="lbl">Email</span><span id="summEmail">–</span></div>
                    </div>
                    <div class="summary-row">
                        <span style="font-size:1.2rem">📱</span>
                        <div><span class="lbl">Teléfono</span><span id="summPhone">–</span></div>
                    </div>
                    <div class="summary-row">
                        <span style="font-size:1.2rem">📅</span>
                        <div><span class="lbl">Fecha y hora</span><span id="summDateTime">–</span></div>
                    </div>
                </div>
                <label class="terms-check">
                    <input type="checkbox" id="termsCheck" onchange="toggleSubmit()">
                    He leído y acepto los términos del servicio y la política de privacidad.
                </label>
                <div class="btn-row">
                    <button type="button" class="btn-back" onclick="prevStep(4)">← Volver</button>
                    <button type="submit" class="btn-submit" id="btnSubmit" disabled>Confirmar Reserva 🗓️</button>
                </div>
            </div>
        </form>
    </div>
</div>

<footer>
    <?= htmlspecialchars(BUSINESS_NAME) ?> · <?= htmlspecialchars(
     BUSINESS_PHONE,
 ) ?>
</footer>

<script>
// ============================================================
//  Estado global
// ============================================================
let currentStep   = 1;
let selectedDate  = null;
let selectedTime  = null;
let currentYear   = new Date().getFullYear();
let currentMonth  = new Date().getMonth() + 1; // 1-12
let availableDays = [];

const DAYS   = ['L','M','X','J','V','S','D'];
const MONTHS = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const DAYS_FULL = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
const MONTHS_FULL = ['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];

// ============================================================
//  Init
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    // Leer meta_lead_id desde URL param
    const params = new URLSearchParams(window.location.search);
    const leadId = params.get('lead_id');
    if (leadId) document.getElementById('metaLeadId').value = leadId;

    // Renderizar días de la semana en el calendario
    const dowGrid = document.getElementById('calDaysOfWeek');
    DAYS.forEach(d => {
        const el = document.createElement('div');
        el.className = 'cal-dow';
        el.textContent = d;
        dowGrid.appendChild(el);
    });
});

// ============================================================
//  Navegación de steps
// ============================================================
function nextStep(from) {
    if (from === 1 && !validateStep1()) return;
    if (from === 2 && !selectedDate) return;
    if (from === 3 && !selectedTime) return;

    // Marcar step anterior como done
    document.getElementById('dot-' + from).classList.remove('active');
    document.getElementById('dot-' + from).classList.add('done');
    if (from < 4) document.getElementById('line-' + from).classList.add('done');

    document.getElementById('step-' + from).classList.remove('active');
    currentStep = from + 1;
    document.getElementById('step-' + currentStep).classList.add('active');
    document.getElementById('dot-' + currentStep).classList.add('active');

    // Acciones al entrar en un step
    if (currentStep === 2) loadCalendar(currentYear, currentMonth);
    if (currentStep === 4) updateSummary();
}

function prevStep(from) {
    document.getElementById('dot-' + from).classList.remove('active');
    document.getElementById('step-' + from).classList.remove('active');
    const prev = from - 1;
    document.getElementById('dot-' + prev).classList.remove('done');
    document.getElementById('dot-' + prev).classList.add('active');
    if (prev < 4) document.getElementById('line-' + prev).classList.remove('done');
    document.getElementById('step-' + prev).classList.add('active');
    currentStep = prev;
}

// ============================================================
//  Validación Step 1
// ============================================================
// ============================================================
//  Selector de prefijo telefónico
// ============================================================
const COUNTRIES = [
    { flag: '🇪🇸', name: 'España',            dial: '+34' },
    { flag: '🇵🇹', name: 'Portugal',         dial: '+351' },
    { flag: '🇬🇧', name: 'Reino Unido',      dial: '+44' },
    { flag: '🇩🇪', name: 'Alemania',         dial: '+49' },
    { flag: '🇫🇷', name: 'Francia',          dial: '+33' },
    { flag: '🇮🇹', name: 'Italia',           dial: '+39' },
    { flag: '🇳🇱', name: 'Países Bajos',    dial: '+31' },
    { flag: '🇧🇪', name: 'Bélgica',          dial: '+32' },
    { flag: '🇨🇭', name: 'Suiza',            dial: '+41' },
    { flag: '🇸🇪', name: 'Suecia',           dial: '+46' },
    { flag: '🇳🇴', name: 'Noruega',          dial: '+47' },
    { flag: '🇩🇰', name: 'Dinamarca',        dial: '+45' },
    { flag: '🇺🇸', name: 'Estados Unidos',   dial: '+1' },
    { flag: '🇲🇽', name: 'México',           dial: '+52' },
    { flag: '🇦🇷', name: 'Argentina',        dial: '+54' },
    { flag: '🇨🇴', name: 'Colombia',         dial: '+57' },
    { flag: '🇨🇱', name: 'Chile',            dial: '+56' },
    { flag: '🇵🇪', name: 'Perú',            dial: '+51' },
    { flag: '🇻🇪', name: 'Venezuela',        dial: '+58' },
    { flag: '🇧🇷', name: 'Brasil',           dial: '+55' },
    { flag: '🇲🇦', name: 'Marruecos',        dial: '+212' },
    { flag: '🇨🇳', name: 'China',            dial: '+86' },
    { flag: '🇷🇺', name: 'Rusia',            dial: '+7' },
    { flag: '🇦🇪', name: 'Emiratos Árabes', dial: '+971' },
];

let selectedCountry = COUNTRIES[0]; // España por defecto

function renderCountryList(filter = '') {
    const list = document.getElementById('countryList');
    list.innerHTML = '';
    const q = filter.toLowerCase().trim();
    const filtered = COUNTRIES.filter(c =>
        c.name.toLowerCase().includes(q) || c.dial.includes(q)
    );
    if (!filtered.length) {
        list.innerHTML = '<div style="padding:14px;text-align:center;color:var(--muted);font-size:.85rem">Sin resultados</div>';
        return;
    }
    filtered.forEach(c => {
        const el = document.createElement('div');
        el.className = 'phone-option' + (c.dial === selectedCountry.dial ? ' selected' : '');
        el.innerHTML = `<span class="opt-flag">${c.flag}</span><span class="opt-name">${c.name}</span><span class="opt-dial">${c.dial}</span>`;
        el.onmousedown = (e) => { e.preventDefault(); selectCountry(c); };
        list.appendChild(el);
    });
    // Scroll al seleccionado
    const sel = list.querySelector('.selected');
    if (sel) sel.scrollIntoView({ block: 'nearest' });
}

function selectCountry(country) {
    selectedCountry = country;
    document.getElementById('selectedFlag').textContent = country.flag;
    document.getElementById('selectedDial').textContent = country.dial;
    closeDropdown();
    updatePhone();
    document.getElementById('phoneNumber').focus();
}

function toggleDropdown() {
    const dd  = document.getElementById('phoneDropdown');
    const btn = document.getElementById('phoneFlagBtn');
    const isOpen = dd.classList.contains('open');
    if (isOpen) {
        closeDropdown();
    } else {
        dd.classList.add('open');
        btn.classList.add('open');
        document.getElementById('countrySearch').value = '';
        renderCountryList();
        setTimeout(() => document.getElementById('countrySearch').focus(), 80);
    }
}

function closeDropdown() {
    document.getElementById('phoneDropdown').classList.remove('open');
    document.getElementById('phoneFlagBtn').classList.remove('open');
}

function filterCountries() {
    renderCountryList(document.getElementById('countrySearch').value);
}

function updatePhone() {
    const num = document.getElementById('phoneNumber').value.trim().replace(/^0+/, '');
    document.getElementById('inputPhone').value = num ? selectedCountry.dial + num : '';
}

// Cerrar dropdown al hacer clic fuera
document.addEventListener('click', e => {
    if (!e.target.closest('.phone-field-wrap')) {
        closeDropdown();
    }
});

// ============================================================
//  Validación Step 1
// ============================================================
function validateStep1() {
    let ok = true;
    const name  = document.getElementById('inputName');
    const email = document.getElementById('inputEmail');
    const phoneNum = document.getElementById('phoneNumber');
    const phoneWrapper = document.getElementById('phoneWrapper');

    [name, email].forEach(f => f.classList.remove('error-field'));
    phoneWrapper.classList.remove('error-field');

    if (!name.value.trim() || name.value.trim().length < 3) {
        name.classList.add('error-field'); ok = false;
    }
    const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRe.test(email.value.trim())) {
        email.classList.add('error-field'); ok = false;
    }
    const phoneRe = /^[\d\s\-]{6,18}$/;
    if (!phoneRe.test(phoneNum.value.trim())) {
        phoneWrapper.classList.add('error-field'); ok = false;
    }
    // Asegurar que el campo oculto tiene el valor completo
    updatePhone();
    return ok;
}

// ============================================================
//  Calendario
// ============================================================
function changeMonth(delta) {
    currentMonth += delta;
    if (currentMonth > 12) { currentMonth = 1;  currentYear++; }
    if (currentMonth < 1)  { currentMonth = 12; currentYear--; }
    loadCalendar(currentYear, currentMonth);
}

function loadCalendar(year, month) {
    const label = document.getElementById('calMonthLabel');
    label.textContent = MONTHS[month] + ' ' + year;

    const grid = document.getElementById('calGrid');
    grid.innerHTML = '<div class="cal-loading">Cargando disponibilidad…</div>';

    const dateStr = year + '-' + String(month).padStart(2,'0');

    fetch('get_slots.php?date=' + dateStr)
        .then(r => r.json())
        .then(data => {
            availableDays = data.available_days || [];
            renderCalendar(year, month);
        })
        .catch(() => {
            grid.innerHTML = '<div class="cal-loading">Error al cargar. Reintenta.</div>';
        });
}

function renderCalendar(year, month) {
    const grid     = document.getElementById('calGrid');
    const today    = new Date();
    const todayStr = today.getFullYear() + '-' + String(today.getMonth()+1).padStart(2,'0') + '-' + String(today.getDate()).padStart(2,'0');

    // Primer día del mes (lunes=0 en nuestro grid: ajustar de Date.getDay() donde Dom=0)
    const firstDay = new Date(year, month - 1, 1);
    // getDay(): 0=Dom,1=Lun...6=Sáb → convertir a Lun-based (0=Lun...6=Dom)
    let startOffset = (firstDay.getDay() + 6) % 7;
    const daysInMonth = new Date(year, month, 0).getDate();

    grid.innerHTML = '';

    // Celdas vacías al inicio
    for (let i = 0; i < startOffset; i++) {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = 'day-btn empty';
        grid.appendChild(el);
    }

    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = year + '-' + String(month).padStart(2,'0') + '-' + String(d).padStart(2,'0');
        const el = document.createElement('button');
        el.type      = 'button';
        el.textContent = d;
        el.dataset.date = dateStr;

        const isPast      = dateStr < todayStr;
        const isAvailable = availableDays.includes(dateStr);
        const isToday     = dateStr === todayStr;
        const isSelected  = dateStr === selectedDate;

        el.className = 'day-btn';
        if (isToday)     el.classList.add('today');
        if (isSelected)  el.classList.add('selected');
        if (isPast)      el.classList.add('past', 'disabled');
        else if (isAvailable) el.classList.add('available');
        else             el.classList.add('disabled');

        if (!isPast && isAvailable) {
            el.onclick = () => selectDay(dateStr);
        }

        grid.appendChild(el);
    }
}

function selectDay(dateStr) {
    selectedDate = dateStr;
    document.getElementById('selectedDate').value = dateStr;

    // Reset hora
    selectedTime = null;
    document.getElementById('selectedTime').value = '';

    // Actualizar visual del calendario
    document.querySelectorAll('#calGrid .day-btn').forEach(b => b.classList.remove('selected'));
    const btn = document.querySelector('[data-date="' + dateStr + '"]');
    if (btn) btn.classList.add('selected');

    // Habilitar botón siguiente
    const btnNext2 = document.getElementById('btnNextStep2');
    btnNext2.style.opacity        = '1';
    btnNext2.style.pointerEvents  = 'auto';

    // Cargar slots para el siguiente step
    loadTimeSlots(dateStr);
}

// ============================================================
//  Slots de hora
// ============================================================
function loadTimeSlots(dateStr) {
    // Formatear fecha en español para la etiqueta
    const parts = dateStr.split('-');
    const ts    = new Date(parts[0], parts[1]-1, parts[2]);
    const label = DAYS_FULL[ts.getDay()] + ', ' + parseInt(parts[2]) + ' de ' + MONTHS_FULL[parseInt(parts[1])] + ' de ' + parts[0];
    document.getElementById('selectedDateText').textContent = label;

    const container = document.getElementById('slotsContainer');
    container.innerHTML = '<div class="slots-loading">Cargando horarios…</div>';

    fetch('get_slots.php?date=' + dateStr)
        .then(r => r.json())
        .then(data => renderSlots(data.slots || []))
        .catch(() => {
            container.innerHTML = '<div class="no-slots">Error al cargar horarios.</div>';
        });
}

function renderSlots(slots) {
    const container = document.getElementById('slotsContainer');
    const btnNext3  = document.getElementById('btnNextStep3');

    if (!slots.length) {
        container.innerHTML = '<div class="no-slots">No hay horarios disponibles para este día.<br>Elige otra fecha.</div>';
        btnNext3.style.opacity = '.4';
        btnNext3.style.pointerEvents = 'none';
        return;
    }

    const grid = document.createElement('div');
    grid.className = 'slots-grid';

    slots.forEach(slot => {
        const btn = document.createElement('button');
        btn.type        = 'button';
        btn.textContent = slot;
        btn.className   = 'slot-btn' + (slot === selectedTime ? ' selected' : '');
        btn.onclick     = () => selectTime(slot, btn);
        grid.appendChild(btn);
    });

    container.innerHTML = '';
    container.appendChild(grid);
}

function selectTime(time, btnEl) {
    selectedTime = time;
    document.getElementById('selectedTime').value = time;

    // Visual
    document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
    btnEl.classList.add('selected');

    // Habilitar siguiente
    const btnNext3 = document.getElementById('btnNextStep3');
    btnNext3.style.opacity       = '1';
    btnNext3.style.pointerEvents = 'auto';
}

// ============================================================
//  Resumen Step 4
// ============================================================
function updateSummary() {
    document.getElementById('summName').textContent  = document.getElementById('inputName').value;
    document.getElementById('summEmail').textContent = document.getElementById('inputEmail').value;
    document.getElementById('summPhone').textContent = document.getElementById('inputPhone').value || (selectedCountry.dial + ' ' + document.getElementById('phoneNumber').value.trim());

    const parts = selectedDate.split('-');
    const ts    = new Date(parts[0], parts[1]-1, parts[2]);
    const dateLabel = DAYS_FULL[ts.getDay()] + ', ' + parseInt(parts[2]) + ' de ' + MONTHS_FULL[parseInt(parts[1])] + ' de ' + parts[0];
    document.getElementById('summDateTime').textContent = dateLabel + ' a las ' + selectedTime;
}

function toggleSubmit() {
    const checked = document.getElementById('termsCheck').checked;
    document.getElementById('btnSubmit').disabled = !checked;
}
</script>
</body>
</html>
