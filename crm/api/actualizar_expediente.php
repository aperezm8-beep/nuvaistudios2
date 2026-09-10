<?php
/**
 * API: Actualizar clasificación o anulación de un expediente
 * POST /api/actualizar_expediente.php
 *   accion         string  — 'clasificacion' | 'anular' | 'reactivar'
 *   expediente_id  int
 *   valor          int     — para clasificacion: 1-4 | 0 = quitar clasificación
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$accion = trim($_POST['accion'] ?? '');
$id     = (int)($_POST['expediente_id'] ?? 0);
$valor  = $_POST['valor'] ?? null;

if (!$id || !in_array($accion, ['clasificacion', 'anular', 'reactivar', 'borrar_anulado'])) {
    echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

$db = getDB();

switch ($accion) {

    case 'clasificacion':
        $clasificacion = ($valor === '' || $valor === null) ? null : (int)$valor;
        if ($clasificacion !== null && ($clasificacion < 1 || $clasificacion > 4)) {
            echo json_encode(['ok' => false, 'error' => 'Clasificación debe ser 1-4 o vacía']);
            exit;
        }
        $upd = $db->prepare("
            UPDATE trans_expedientes
            SET CLASIFICACION = :c, FEMODIFICACION = NOW(), USMODIFICACION = :usr
            WHERE IDEXPEDIENTE = :id
        ");
        $upd->execute([':c' => $clasificacion, ':id' => $id, ':usr' => $_SESSION['user_id']]);
        echo json_encode(['ok' => true, 'clasificacion' => $clasificacion]);
        break;

    case 'anular':
        $upd = $db->prepare("
            UPDATE trans_expedientes
            SET ANULADO = 1, FEMODIFICACION = NOW(), USMODIFICACION = :usr
            WHERE IDEXPEDIENTE = :id
        ");
        $upd->execute([':id' => $id, ':usr' => $_SESSION['user_id']]);
        echo json_encode(['ok' => true]);
        break;

    case 'reactivar':
        $upd = $db->prepare("
            UPDATE trans_expedientes
            SET ANULADO = 0, FEMODIFICACION = NOW(), USMODIFICACION = :usr
            WHERE IDEXPEDIENTE = :id
        ");
        $upd->execute([':id' => $id, ':usr' => $_SESSION['user_id']]);
        echo json_encode(['ok' => true]);
        break;

    case 'borrar_anulado':
        $check = $db->prepare(
            "SELECT IDEXPEDIENTE FROM trans_expedientes
             WHERE IDEXPEDIENTE = :id AND ANULADO = 1 LIMIT 1"
        );
        $check->execute([':id' => $id]);
        if (!$check->fetchColumn()) {
            echo json_encode(['ok' => false, 'error' => 'Solo se pueden borrar expedientes anulados']);
            exit;
        }

        $delete = $db->prepare(
            "DELETE FROM trans_expedientes
             WHERE IDEXPEDIENTE = :id AND ANULADO = 1"
        );
        $delete->execute([':id' => $id]);
        echo json_encode([
            'ok' => $delete->rowCount() === 1,
            'error' => $delete->rowCount() === 1 ? null : 'No se pudo borrar el expediente',
        ]);
        break;
}
