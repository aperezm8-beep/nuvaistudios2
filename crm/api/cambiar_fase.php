<?php
/**
 * API: Cambiar fase de un expediente manualmente
 * POST /api/cambiar_fase.php
 *   expediente_id  int
 *   fase_id        int
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$id    = (int)($_POST['expediente_id'] ?? 0);
$fase  = (int)($_POST['fase_id'] ?? 0);

if (!$id || !$fase) {
    echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

$db = getDB();

$chk = $db->prepare("SELECT IDFASE, NOMBREFASE FROM m_fases WHERE IDFASE = :id AND ACTIVO = 1");
$chk->execute([':id' => $fase]);
$faseRow = $chk->fetch();

if (!$faseRow) {
    echo json_encode(['ok' => false, 'error' => 'Fase no válida']);
    exit;
}

$upd = $db->prepare("
    UPDATE trans_expedientes
    SET ID_FASE = :fase,
        FEMODIFICACION = NOW(),
        USMODIFICACION = :usr
    WHERE IDEXPEDIENTE = :id AND Activo = b'1'
");
$upd->execute([
    ':fase' => $fase,
    ':id'   => $id,
    ':usr'  => $_SESSION['user_id'],
]);

if ($upd->rowCount() > 0) {
    echo json_encode([
        'ok'         => true,
        'nueva_fase' => $faseRow['NOMBREFASE'],
        'nueva_fase_id' => $fase,
    ]);
} else {
    echo json_encode(['ok' => false, 'error' => 'No se pudo actualizar']);
}
