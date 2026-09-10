<?php
require_once __DIR__ . '/includes/auth.php';

// Si ya está logado, redirigir al dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Error de seguridad. Por favor recarga la página.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Por favor introduce tu usuario y contraseña.';
        } elseif (login($username, $password)) {
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos.';
            // Pequeño retraso para mitigar brute force
            sleep(1);
        }
    }
}

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión | Nuvai CRM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <span class="login-logo-title">Nuvai CRM</span>
            <span class="login-logo-sub">Panel de gestión</span>
        </div>

        <h2 class="login-title">Iniciar sesión</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="form-group">
                <label for="username">Usuario</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    class="form-input"
                    placeholder="Introduce tu usuario"
                    autocomplete="username"
                    maxlength="50"
                    required
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <div class="password-wrap">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-input"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        maxlength="250"
                        required
                    >
                    <button type="button" class="pwd-toggle" id="togglePwd" aria-label="Mostrar contraseña">
                        <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <svg id="eyeOffIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                            <line x1="1" y1="1" x2="23" y2="23"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="remember-row">
                <label class="remember-label">
                    <input type="checkbox" name="remember" id="remember" value="1">
                    <span>Recuérdame</span>
                </label>
            </div>

            <button type="submit" class="btn-full">Entrar</button>
        </form>

        <p style="text-align:center;margin-top:20px;font-size:0.75rem;color:#9ca3af;">
            GW CRM &copy; <?= date('Y') ?>
        </p>
    </div>
</div>
</body>
<script>
document.getElementById('togglePwd').addEventListener('click', function() {
    var pwd = document.getElementById('password');
    var eyeOn = document.getElementById('eyeIcon');
    var eyeOff = document.getElementById('eyeOffIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        eyeOn.style.display = 'none';
        eyeOff.style.display = '';
    } else {
        pwd.type = 'password';
        eyeOn.style.display = '';
        eyeOff.style.display = 'none';
    }
});
</script>
</html>
