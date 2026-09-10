<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$db     = getDB();
$offset = max(0, (int)($_GET['offset'] ?? 0));
$limit  = 30;
$origen = $_GET['origen'] ?? 'inicio';
$tipo   = $_GET['tipo'] ?? '';
$buscar = trim($_GET['buscar'] ?? '');

// Construir WHERE según origen
$where  = "e.Activo = b'1'";
$params = [];

if ($tipo === 'nacional') {
    $where .= " AND (e.ZONA IN ('Nacional','España','Spain') OR e.ZONA IS NULL OR TRIM(e.ZONA) = '')";
} elseif ($tipo === 'internacional') {
    $where .= " AND e.ZONA NOT IN ('Nacional','España','Spain') AND e.ZONA IS NOT NULL AND TRIM(e.ZONA) != ''";
}

if ($buscar !== '') {
    $where .= " AND (e.NOMBRE LIKE :buscar OR e.APELLIDOS LIKE :buscar OR e.EMAIL LIKE :buscar OR e.TELEFONO LIKE :buscar OR e.POBLACION LIKE :buscar OR e.COMENTARIO LIKE :buscar)";
    $params[':buscar'] = '%' . $buscar . '%';
}

$stmt = $db->prepare("
    SELECT e.*, m.NOMBREMODALIDAD, es.NOMBREESTADO, f.NOMBREFASE
    FROM trans_expedientes e
    LEFT JOIN m_modalidad_implantacion m ON e.ID_MODALIDAD = m.IDMODALIDAD
    LEFT JOIN m_estados es ON e.ID_ESTADO = es.IDESTADO
    LEFT JOIN m_fases f ON e.ID_FASE = f.IDFASE
    WHERE {$where}
    ORDER BY e.FECREACION DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

// Contar total
$stmtCount = $db->prepare("SELECT COUNT(*) FROM trans_expedientes e WHERE {$where}");
foreach ($params as $k => $v) $stmtCount->bindValue($k, $v);
$stmtCount->execute();
$total = (int)$stmtCount->fetchColumn();

// Generar HTML de tarjetas
ob_start();
foreach ($rows as $e) {
    $zona = strtolower($e['ZONA'] ?? '');
    $esNacional = $zona === '' || in_array($zona, ['nacional', 'españa', 'spain']);
    $claseZona = $esNacional ? 'nacional' : 'internacional';
    $iconZona  = $esNacional ? '🇪🇸' : '🌍';
    $fecha = $e['FECREACION'] ? date('d/m/Y', strtotime($e['FECREACION'])) : '—';
    $nombre = htmlspecialchars(trim(($e['NOMBRE'] ?? '') . ' ' . ($e['APELLIDOS'] ?? '')));
    $tel   = htmlspecialchars($e['TELEFONO'] ?? '');
    $pob   = htmlspecialchars(trim(($e['POBLACION'] ?? '') . ($e['PROVINCIA'] ? ', ' . $e['PROVINCIA'] : '')));
    $pais  = htmlspecialchars($e['PAIS'] ?? '');
    $mod   = htmlspecialchars($e['NOMBREMODALIDAD'] ?? 'Sin modalidad');
    $est   = htmlspecialchars($e['NOMBREESTADO'] ?? '');
    $fase  = htmlspecialchars($e['NOMBREFASE'] ?? '');
    $com   = htmlspecialchars($e['COMENTARIO'] ?? '');
    $id    = (int)$e['IDEXPEDIENTE'];
    echo "<div class=\"exp-card {$claseZona}\" data-id=\"{$id}\">";
    echo "<div class=\"exp-card-header\"><span class=\"exp-name\">{$iconZona} {$nombre}</span><span class=\"exp-date\">{$fecha}</span></div>";
    echo "<div class=\"exp-card-meta\">";
    echo "<span class=\"badge badge-" . ($esNacional ? 'green' : 'blue') . "\">" . ($esNacional ? 'Nacional' : 'Internacional') . "</span>";
    if ($mod)  echo "<span class=\"badge badge-gray\">{$mod}</span>";
    if ($est)  echo "<span class=\"badge badge-orange\">{$est}</span>";
    echo "</div>";
    if ($pob || $pais) {
        echo "<div class=\"exp-contact\"><span>📍 " . ($pob ?: $pais) . "</span>";
        if ($tel) echo "<span>📞 {$tel}</span>";
        echo "</div>";
    }
    if ($com) echo "<div class=\"exp-comment\">{$com}</div>";
    echo "</div>";
}
$html = ob_get_clean();

// Modales
ob_start();
foreach ($rows as $e) {
    $id   = (int)$e['IDEXPEDIENTE'];
    $nombre = htmlspecialchars(trim(($e['NOMBRE'] ?? '') . ' ' . ($e['APELLIDOS'] ?? '')));
    $tel   = htmlspecialchars($e['TELEFONO'] ?? '');
    $email = htmlspecialchars($e['EMAIL'] ?? '');
    $pob   = htmlspecialchars(trim(($e['POBLACION'] ?? '') . ($e['PROVINCIA'] ? ', ' . $e['PROVINCIA'] : '')));
    $pais  = htmlspecialchars($e['PAIS'] ?? '');
    $mod   = htmlspecialchars($e['NOMBREMODALIDAD'] ?? '');
    
    $fase  = htmlspecialchars($e['NOMBREFASE'] ?? '');
    $com   = htmlspecialchars($e['COMENTARIO'] ?? '');
    $obs   = htmlspecialchars($e['OBSERVACIONES'] ?? '');
    $rec   = htmlspecialchars($e['RECIBIDO'] ?? '');
    $fecha = $e['FECREACION'] ? date('d/m/Y', strtotime($e['FECREACION'])) : '—';
    echo "<div class=\"modal-backdrop\" id=\"modal-exp-{$id}\">";
    echo "<div class=\"modal\">";
    echo "<div class=\"modal-header\"><span class=\"modal-title\">Expediente #{$id}</span><button class=\"modal-close\" data-modal-close=\"modal-exp-{$id}\">✕</button></div>";
    echo "<div class=\"modal-body\">";
    foreach ([['Nombre',$nombre],['Teléfono',$tel],['Email',$email],
               ['Zona',htmlspecialchars($e['ZONA']??'')],['Población',$pob],['País',$pais],
               ['Modalidad',$mod],['Estado',$est],['Fase',$fase],
               ['Recibido',$rec],['Comentario',$com],['Observaciones',$obs],
               ['Fecha entrada',$fecha]] as [$label,$val]) {
        if (!$val) continue;
        echo "<div class=\"modal-row\"><span class=\"modal-label\">{$label}</span><span class=\"modal-value\">{$val}</span></div>";
    }
    echo "</div></div></div>";
}
$modals = ob_get_clean();

echo json_encode([
    'html'    => $html,
    'modals'  => $modals,
    'count'   => count($rows),
    'hasMore' => ($offset + count($rows)) < $total,
]);
