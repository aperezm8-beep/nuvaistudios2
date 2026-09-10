<?php
/**
 * API: Envío de documentación por email
 * POST /api/send_email.php
 *   expediente_id  int
 *   tipo           string  — '1doc' | '2doc' | 'precontrato' | 'contrato'
 *   preview        bool    — solo devuelve HTML sin enviar
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email_templates.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$id      = (int)($_POST['expediente_id'] ?? $_GET['expediente_id'] ?? 0);
$tipo    = trim($_POST['tipo'] ?? $_GET['tipo'] ?? '');
$preview = !empty($_POST['preview']) || !empty($_GET['preview']);

if (!$id || !array_key_exists($tipo, TIPOS_DOC)) {
    echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

$db = getDB();

$stmt = $db->prepare("
    SELECT e.*, m.NOMBREMODALIDAD, f.NOMBREFASE
    FROM trans_expedientes e
    LEFT JOIN m_modalidad_implantacion m ON e.ID_MODALIDAD = m.IDMODALIDAD
    LEFT JOIN m_fases f ON e.ID_FASE = f.IDFASE
    WHERE e.IDEXPEDIENTE = :id AND e.Activo = b'1'
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$exp = $stmt->fetch();

if (!$exp) {
    echo json_encode(['ok' => false, 'error' => 'Expediente no encontrado']);
    exit;
}
if (empty($exp['EMAIL'])) {
    echo json_encode(['ok' => false, 'error' => 'El expediente no tiene email registrado']);
    exit;
}

// Preview: devuelve HTML sin enviar
if ($preview) {
    try {
        $html   = buildEmailHtml($tipo, $exp);
        $asunto = getEmailSubjectForTipo($tipo, $exp);
        $nombre = trim(($exp['NOMBRE'] ?? '') . ' ' . ($exp['APELLIDOS'] ?? ''));
        echo json_encode([
            'ok'     => true,
            'html'   => $html,
            'asunto' => $asunto,
            'para'   => $exp['EMAIL'],
            'nombre' => $nombre,
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Envío real
$resultado = enviarEmailDocumentacion($tipo, $exp);

if ($resultado['ok']) {
    try {
        $htmlEnviado = buildEmailHtml($tipo, $exp);
        $asuntoEnviado = getEmailSubjectForTipo($tipo, $exp);
        $logEmail = $db->prepare(" 
            INSERT INTO crm_email_webhooks (
                source, domain, sender_name, sender_email, recipient_email,
                subject, message_text, message_html, headers_json, raw_payload, received_at
            ) VALUES (
                'smtp', :domain, :sender_name, :sender_email, :recipient_email,
                :subject, :message_text, :message_html, :headers_json, NULL, NOW()
            )
        ");
        $logEmail->execute([
            ':domain' => strtolower((string)(strrchr(SMTP_FROM, '@') ? substr(strrchr(SMTP_FROM, '@'), 1) : '')),
            ':sender_name' => SMTP_FROM_NAME,
            ':sender_email' => SMTP_FROM,
            ':recipient_email' => trim($exp['EMAIL']),
            ':subject' => $asuntoEnviado,
            ':message_text' => trim(html_entity_decode(strip_tags($htmlEnviado), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            ':message_html' => $htmlEnviado,
            ':headers_json' => json_encode([
                'direction' => 'outbound',
                'expediente_id' => (int)$exp['IDEXPEDIENTE'],
                'tipo' => $tipo,
            ], JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable $e) {
        error_log('send_email.php: no se pudo registrar el email enviado: ' . $e->getMessage());
    }

    // Cambiar fase del expediente
    $nuevaFase = getFaseParaTipo($tipo);
    if ($nuevaFase) {
        $upd = $db->prepare("
            UPDATE trans_expedientes
            SET ID_FASE = :fase,
                FEMODIFICACION = NOW(),
                USMODIFICACION = :usr
            WHERE IDEXPEDIENTE = :id
        ");
        $upd->execute([
            ':fase' => $nuevaFase,
            ':id'   => $id,
            ':usr'  => $_SESSION['user_id'],
        ]);

        // Obtener nombre de la nueva fase
        $faseRow = $db->prepare("SELECT NOMBREFASE FROM m_fases WHERE IDFASE = :id");
        $faseRow->execute([':id' => $nuevaFase]);
        $faseNombre = $faseRow->fetchColumn() ?: '';
    }

    echo json_encode([
        'ok'           => true,
        'mensaje'      => 'Email enviado correctamente',
        'nueva_fase_id'=> $nuevaFase ?? null,
        'nueva_fase'   => $faseNombre ?? '',
    ]);
} else {
    echo json_encode(['ok' => false, 'error' => $resultado['error']]);
}
