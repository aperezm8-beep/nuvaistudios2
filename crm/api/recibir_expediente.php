<?php
/**
 * Endpoint para recibir expedientes desde un formulario externo.
 *
 * Formato: POST application/x-www-form-urlencoded, multipart/form-data o JSON.
 * El formulario esta en nuvaistudio.com y el endpoint en crm.nuvaistudio.com.
 *
 * Campos admitidos:
 * nombre, apellidos, email, telefono, codigo_pais, zona, pais, poblacion,
 * provincia, franquicia, modalidad_id, estado_id, fase_id, recibido,
 * mensaje, comentario, observaciones
 */
header('Content-Type: application/json; charset=utf-8');
header('X-CRM-Endpoint-Version: 2026-08-20-3');

$isBrowserForm = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html') !== false;

$allowedOrigin = 'https://nuvaistudio.com';
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($requestOrigin === $allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Accept, Content-Type');
    header('Vary: Origin');
}

ini_set('display_errors', '0');
set_exception_handler(static function ($error) {
    error_log('Error no controlado al recibir expediente: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error interno del CRM']);
});
register_shutdown_function(static function () {
    $lastError = error_get_last();
    if (!$lastError || !in_array($lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    error_log('Error fatal al recibir expediente: ' . $lastError['message']);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => false, 'error' => 'Error fatal en el endpoint del CRM']);
});

require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if ($requestOrigin !== $allowedOrigin) {
        http_response_code(403);
        exit;
    }
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodo no permitido']);
    exit;
}

$redirectWithError = static function (string $message) use ($isBrowserForm) {
    if ($isBrowserForm) {
        header('Location: https://nuvaistudio.com/mensaje-rechazado.html', true, 303);
        exit;
    }
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
};

$input = $_POST;
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $json = json_decode(file_get_contents('php://input'), true);
    if (!is_array($json)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'JSON no valido']);
        exit;
    }
    $input = $json;
}

$value = static function (array $data, string $key): string {
    return trim((string)($data[$key] ?? ''));
};

// Honeypot y tiempo minimo para descartar envios automaticos sencillos.
if ($value($input, 'website') !== '') {
    $redirectWithError('Solicitud no valida');
}

$formTimestamp = (int)$value($input, 'form_ts');
$currentTimestamp = time();
if ($formTimestamp > 0 && ($currentTimestamp - $formTimestamp < 0 || $currentTimestamp - $formTimestamp > 86400)) {
    $redirectWithError('Formulario caducado');
}

$recaptchaToken = $value($input, 'g-recaptcha-response');
$recaptchaSecret = getenv('RECAPTCHA_SECRET_KEY') ?: '6LdacIYtAAAAAN0yInVUjOo8LVZnkrHaQkkfBDeO';
if ($recaptchaSecret === '' || $recaptchaToken === '') {
    $redirectWithError('reCAPTCHA no configurado o no completado');
}

$recaptchaPayload = http_build_query([
    'secret'   => $recaptchaSecret,
    'response' => $recaptchaToken,
    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
]);
$recaptchaResponse = @file_get_contents(
    'https://www.google.com/recaptcha/api/siteverify',
    false,
    stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content'       => $recaptchaPayload,
            'timeout'       => 5,
            'ignore_errors' => true,
        ],
    ])
);

if ($recaptchaResponse === false) {
    $redirectWithError('El servidor CRM no puede contactar con Google reCAPTCHA');
}
$recaptchaResult = $recaptchaResponse ? json_decode($recaptchaResponse, true) : null;
if (!is_array($recaptchaResult)) {
    error_log('reCAPTCHA sin respuesta valida de Google');
    $redirectWithError('El servidor no pudo contactar con reCAPTCHA');
}
if (empty($recaptchaResult['success'])) {
    $recaptchaErrors = $recaptchaResult['error-codes'] ?? [];
    error_log('reCAPTCHA rechazado: ' . implode(', ', $recaptchaErrors));
    $redirectWithError('No se pudo validar el reCAPTCHA');
}

$nullableInt = static function (array $data, string $key): ?int {
    $raw = trim((string)($data[$key] ?? ''));
    return $raw === '' ? null : (int)$raw;
};

$franquicia = strtolower($value($input, 'franquicia'));
$modalidadId = $nullableInt($input, 'modalidad_id');

$zona = strtolower($value($input, 'zona'));
$zona = [
    'espana'       => 'España',
    'españa'       => 'España',
    'nacional'     => 'España',
    'internacional'=> 'Internacional',
][$zona] ?? $value($input, 'zona');

$telefono = trim($value($input, 'codigo_pais') . ' ' . $value($input, 'telefono'));
$comentario = $value($input, 'comentario') ?: $value($input, 'mensaje');
$observaciones = $value($input, 'observaciones');

$nombre = $value($input, 'nombre');
$email  = $value($input, 'email');

if ($nombre === '') {
    $redirectWithError('El campo nombre es obligatorio');
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $redirectWithError('El email no es valido');
}

$values = [
    ':nombre'       => $nombre,
    ':apellidos'    => $value($input, 'apellidos'),
    ':email'        => $email,
    ':telefono'     => $telefono,
    ':zona'         => $zona,
    ':pais'         => $value($input, 'pais'),
    ':poblacion'    => $value($input, 'poblacion'),
    ':provincia'    => $value($input, 'provincia'),
    ':modalidad'    => $modalidadId,
    ':estado'       => $nullableInt($input, 'estado_id'),
    ':fase'         => $nullableInt($input, 'fase_id') ?? 88,
    ':recibido'     => $value($input, 'recibido') ?: 'Formulario web',
    ':comentario'   => $comentario,
    ':observaciones'=> $observaciones,
];

try {
    $db = getDB();

    if ($modalidadId === null && $franquicia !== '') {
        $modalidadPatterns = [
            'parking'   => ['%PARKING%SUBTERR%', '%SUBTERR%'],
            'industrial' => ['%INDUSTRIAL%', '%LOCAL%'],
            'superficie' => ['%SUPERFICIE%'],
            'lowcost'   => ['%LOW%COST%', '%LOW%'],
        ];
        $patterns = $modalidadPatterns[$franquicia] ?? [];
        foreach ($patterns as $index => $pattern) {
            $lookup = $db->prepare(
                'SELECT IDMODALIDAD FROM m_modalidad_implantacion
                 WHERE ACTIVO = 1 AND NOMBREMODALIDAD LIKE :nombre LIMIT 1'
            );
            $lookup->execute([':nombre' => $pattern]);
            $foundId = $lookup->fetchColumn();
            if ($foundId !== false) {
                $modalidadId = (int)$foundId;
                break;
            }
        }
    }

    if ($franquicia !== '' && $modalidadId === null) {
        $observaciones = trim("Modalidad web: {$franquicia}\n" . $observaciones);
    }

    $values[':modalidad'] = $modalidadId;
    $values[':observaciones'] = $observaciones;

    $stmt = $db->prepare("
        INSERT INTO trans_expedientes (
            NOMBRE, APELLIDOS, EMAIL, TELEFONO, ZONA, PAIS,
            POBLACION, PROVINCIA, ID_MODALIDAD, ID_ESTADO, ID_FASE,
            RECIBIDO, COMENTARIO, OBSERVACIONES, FECREACION, Activo
        ) VALUES (
            :nombre, :apellidos, :email, :telefono, :zona, :pais,
            :poblacion, :provincia, :modalidad, :estado, :fase,
            :recibido, :comentario, :observaciones, NOW(), b'1'
        )
    ");
    $stmt->execute($values);

    if ($isBrowserForm) {
        header('Location: https://nuvaistudio.com/gracias.html', true, 303);
        exit;
    }

    http_response_code(201);
    echo json_encode([
        'ok' => true,
        'id' => (int)$db->lastInsertId(),
        'mensaje' => 'Expediente recibido correctamente',
    ]);
} catch (Exception $e) {
    error_log('Error al recibir expediente: ' . $e->getMessage());
    $redirectWithError('No se pudo guardar el expediente');
}
