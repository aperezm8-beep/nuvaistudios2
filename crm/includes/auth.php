<?php
require_once __DIR__ . '/db.php';

// Iniciar sesión de forma segura
if (session_status() === PHP_SESSION_NONE) {
    $sessionPath = __DIR__ . '/../storage/sessions';
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0700, true);
    }
    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        ini_set('session.save_path', $sessionPath);
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// Comprobar cookie "recuérdame" si no hay sesión activa
if (empty($_SESSION['logged_in']) && !empty($_COOKIE['gw_remember'])) {
    $token = $_COOKIE['gw_remember'];
    // Token formato: userId:randomToken (guardado como hash en BD no implementado aquí,
    // pero se puede ampliar). Por simplicidad, almacenamos en sesión-fichero firmado.
    // Implementación básica: el token contiene userId|hash(userId+secret)
    $secret = 'GW_SECRET_REMEMBER_2024'; // Cambiar en producción
    $parts = explode('|', $token);
    if (count($parts) === 2) {
        [$userId, $hash] = $parts;
        $userId = (int)$userId;
        if ($userId > 0 && hash_equals(hash_hmac('sha256', (string)$userId, $secret), $hash)) {
            $db = getDB();
            $stmt = $db->prepare(
                "SELECT u.IdUsuario, u.Nombre, u.Apellido1, u.IdRol, r.NombreRol, u.User
                 FROM m_usuarios u
                 LEFT JOIN m_roles r ON u.IdRol = r.IdRol
                 WHERE u.IdUsuario = :id AND u.Activo = 1 LIMIT 1"
            );
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch();
            if ($user) {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['IdUsuario'];
                $_SESSION['username']  = $user['User'];
                $_SESSION['nombre']    = $user['Nombre'] . ' ' . $user['Apellido1'];
                $_SESSION['id_rol']    = $user['IdRol'];
                $_SESSION['logged_in'] = true;
                // Renovar cookie
                setcookie('gw_remember', $token, time() + 30 * 86400, '/', '', false, true);
            }
        }
    }
}

/**
 * Verifica las credenciales del usuario usando prepared statements.
 * Las contraseñas almacenadas usan el formato ASP.NET Identity (PBKDF2).
 * Si el servidor actual usa password_hash de PHP, adaptar según corresponda.
 */
function login(string $username, string $password): bool {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT IdUsuario, Nombre, Apellido1, IdRol, Password
         FROM m_usuarios
         WHERE User = :username AND Activo = 1
         LIMIT 1"
    );
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if (!$user) {
        return false;
    }

    // Las contraseñas en la BD usan formato ASP.NET Identity PBKDF2.
    // Para una nueva instalación PHP, usar password_verify con password_hash.
    // Esta función intenta ambos métodos:
    $valid = false;

    // Método 1: PHP password_hash (para usuarios creados desde esta app)
    if (password_verify($password, $user['Password'])) {
        $valid = true;
    }

    // Método 2: Contraseña en texto plano (solo para migración inicial)
    // ELIMINAR EN PRODUCCIÓN si no se necesita compatibilidad
    // if ($user['Password'] === $password) { $valid = true; }

    if ($valid) {
        // Regenerar ID de sesión para prevenir session fixation
        session_regenerate_id(true);

        $_SESSION['user_id']   = $user['IdUsuario'];
        $_SESSION['username']  = $username;
        $_SESSION['nombre']    = $user['Nombre'] . ' ' . $user['Apellido1'];
        $_SESSION['id_rol']    = $user['IdRol'];
        $_SESSION['logged_in'] = true;

        // Registrar acceso en log de auditoría
        $log = $db->prepare("INSERT INTO log_audit_accesos (IDUSUARIO) VALUES (:id)");
        $log->execute([':id' => $user['IdUsuario']]);

        // Cookie "recuérdame" (30 días)
        if (!empty($_POST['remember'])) {
            $secret = 'GW_SECRET_REMEMBER_2024'; // Cambiar en producción
            $hash   = hash_hmac('sha256', (string)$user['IdUsuario'], $secret);
            $token  = $user['IdUsuario'] . '|' . $hash;
            setcookie('gw_remember', $token, time() + 30 * 86400, '/', '', false, true);
        }

        return true;
    }

    return false;
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    // Borrar cookie de recuérdame
    setcookie('gw_remember', '', time() - 3600, '/');
    session_destroy();
}
function requireLogin(): void {
    // Si no está definido 'logged_in' pero sí existe un 'user_id' válido en la sesión, lo damos por bueno
    if (empty($_SESSION['logged_in']) && empty($_SESSION['user_id'])) {
        header('Location: index.php');
        exit();
    }
}

function isLoggedIn(): bool {
    return !empty($_SESSION['logged_in']);
}
