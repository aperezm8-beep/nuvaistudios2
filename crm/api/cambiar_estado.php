<?php
/**
 * API: Cambiar estado de un expediente
 * POST /api/cambiar_estado.php
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$id     = (int)($_POST['expediente_id'] ?? 0);
$estado = (int)($_POST['estado_id'] ?? 0);

if (!$id || !$estado) {
    echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

$db = getDB();

// Verificar que el estado existe
$chk = $db->prepare("SELECT IDESTADO, NOMBREESTADO FROM m_estados WHERE IDESTADO = :id AND ACTIVO = 1");
$chk->execute([':id' => $estado]);
$estadoRow = $chk->fetch();

if (!$estadoRow) {
    echo json_encode(['ok' => false, 'error' => 'Estado no válido']);
    exit;
}

$upd = $db->prepare("
    UPDATE trans_expedientes
    SET ID_ESTADO = :estado,
        FEMODIFICACION = NOW(),
        USMODIFICACION = :usr
    WHERE IDEXPEDIENTE = :id AND Activo = 1
");
$upd->execute([
    ':estado' => $estado,
    ':id'     => $id,
    ':usr'    => $_SESSION['user_id'],
]);

if ($upd->rowCount() > 0) {
    echo json_encode([
        'ok'            => true,
        'nuevo_estado'  => $estadoRow['NOMBREESTADO'],
        'nuevo_estado_id' => $estado,
    ]);
} else {
    echo json_encode(['ok' => false, 'error' => 'No se pudo actualizar el expediente']);
}
