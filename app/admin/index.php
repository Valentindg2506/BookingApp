<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/helpers.php';

session_name(SESSION_NAME);
session_start();

// Si ya está logueado, redirigir al dashboard
if (!empty($_SESSION['admin_id'])) {
    redirect(APP_URL . '/admin/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Por favor, completa todos los campos.';
    } else {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                "SELECT id, username, password, name FROM admin_users WHERE username = :u LIMIT 1"
            );
            $stmt->execute([':u' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id']   = $user['id'];
                $_SESSION['admin_name'] = $user['name'];
                $_SESSION['admin_user'] = $user['username'];
                redirect(APP_URL . '/admin/dashboard.php');
            } else {
                $error = 'Usuario o contraseña incorrectos.';
                // Pequeño delay para evitar brute-force
                sleep(1);
            }
        } catch (Exception $e) {
            $error = 'Error del sistema. Inténtalo más tarde.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login – <?= APP_NAME ?></title>
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
        .login-card {
            background: #fff;
            border-radius: 20px;
            padding: 48px 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 24px 60px rgba(0,0,0,.25);
        }
        .logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .logo-icon {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, #1b3a2d, #2d6a4f);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }
        .logo h1 { font-size: 1.4rem; font-weight: 700; color: #1b3a2d; }
        .logo p  { font-size: .82rem; color: #90a4ae; margin-top: 4px; }
        .field { margin-bottom: 16px; }
        .field label { display: block; font-size: .8rem; font-weight: 600; color: #607d8b; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .04em; }
        .field input {
            width: 100%;
            padding: 13px 16px;
            border: 2px solid #e2ddd5;
            border-radius: 10px;
            font-size: .95rem;
            font-family: inherit;
            color: #263238;
            outline: none;
            transition: border-color .2s;
        }
        .field input:focus { border-color: #2d6a4f; }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #1b3a2d, #2d6a4f);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: .95rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            margin-top: 8px;
            transition: opacity .2s;
        }
        .btn-login:hover { opacity: .88; }
        .alert { background: #fdecea; border-left: 4px solid #e53935; border-radius: 8px; padding: 12px 16px; font-size: .85rem; color: #b71c1c; margin-bottom: 20px; }
        .back-link { display: block; text-align: center; margin-top: 20px; font-size: .82rem; color: #90a4ae; text-decoration: none; }
        .back-link:hover { color: #2d6a4f; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="logo">
        <div class="logo-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <h1><?= htmlspecialchars(APP_NAME) ?></h1>
        <p>Panel de Administración</p>
    </div>

    <?php if ($error): ?>
    <div class="alert">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="field">
            <label>Usuario</label>
            <input type="text" name="username" autocomplete="username" placeholder="admin" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="field">
            <label>Contraseña</label>
            <input type="password" name="password" autocomplete="current-password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn-login">Iniciar sesión →</button>
    </form>

    <a href="<?= APP_URL ?>/index.php" class="back-link">← Volver al inicio</a>
</div>
</body>
</html>
