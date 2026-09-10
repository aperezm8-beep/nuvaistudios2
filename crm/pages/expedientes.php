<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email_config.php';
requireLogin();

$db = getDB();
$basePath = '../';

$tipo            = $_GET['tipo']          ?? 'nacional';
$buscar          = trim($_GET['buscar']   ?? '');
$filtroModalidad = (int)($_GET['modalidad'] ?? 0);
$filtroFase      = (int)($_GET['fase']      ?? 0);
$filtroClasif    = isset($_GET['clasificacion']) && $_GET['clasificacion'] !== '' ? (int)$_GET['clasificacion'] : null;
$verAnulados     = isset($_GET['anulados']) && $_GET['anulados'] === '1';
$ordenar         = $_GET['orden'] ?? 'fecha';
$limit           = 30;
$offset          = 0;

$tipoLabel   = ($tipo === 'nacional') ? 'Nacionales' : 'Internacionales';
$currentPage = ($tipo === 'nacional') ? 'exp-nacionales' : 'exp-internacionales';
$pageTitle   = "Expedientes $tipoLabel";

// ── Detectar si la columna ANULADO existe en la BD ──────────
$tieneAnulado = false;
try {
    $db->query("SELECT ANULADO FROM trans_expedientes LIMIT 1");
    $tieneAnulado = true;
} catch (Exception $e) {
    $tieneAnulado = false;
}

// ── Detectar si la columna CLASIFICACION existe ──────────────
$tieneClasif = false;
try {
    $db->query("SELECT CLASIFICACION FROM trans_expedientes LIMIT 1");
    $tieneClasif = true;
} catch (Exception $e) {
    $tieneClasif = false;
}

// ── QUERY ────────────────────────────────────────────────────
$where  = "e.Activo = b'1'";
$params = [];

// Anulados (solo si la columna existe)
if ($tieneAnulado) {
    if ($verAnulados) {
        $where .= " AND e.ANULADO = 1";
    } else {
        $where .= " AND (e.ANULADO = 0 OR e.ANULADO IS NULL)";
    }
}

// Nacional / Internacional
if ($tipo === 'nacional') {
    $where .= " AND (e.ZONA IN ('Nacional','España','Spain') OR ((e.ZONA IS NULL OR TRIM(e.ZONA) = '') AND (e.PAIS IN ('España','Spain','') OR e.PAIS IS NULL)))";
} else {
    $where .= " AND e.ZONA NOT IN ('Nacional','España','Spain') AND e.ZONA IS NOT NULL AND TRIM(e.ZONA) != ''";
}

if ($buscar !== '') {
    $b = '%' . $buscar . '%';
    $where .= " AND (e.NOMBRE LIKE :b1 OR e.APELLIDOS LIKE :b2 OR e.EMAIL LIKE :b3 OR e.TELEFONO LIKE :b4 OR e.POBLACION LIKE :b5 OR e.PROVINCIA LIKE :b6 OR e.PAIS LIKE :b7 OR e.COMENTARIO LIKE :b8)";
    $params[':b1'] = $b; $params[':b2'] = $b; $params[':b3'] = $b; $params[':b4'] = $b;
    $params[':b5'] = $b; $params[':b6'] = $b; $params[':b7'] = $b; $params[':b8'] = $b;
}
if ($filtroModalidad) {
    $where .= " AND e.ID_MODALIDAD = :modalidad";
    $params[':modalidad'] = $filtroModalidad;
}
if ($filtroFase) {
    $where .= " AND e.ID_FASE = :fase";
    $params[':fase'] = $filtroFase;
}
if ($tieneClasif && $filtroClasif !== null) {
    if ($filtroClasif === 0) {
        $where .= " AND e.CLASIFICACION IS NULL";
    } else {
        $where .= " AND e.CLASIFICACION = :clasif";
        $params[':clasif'] = $filtroClasif;
    }
}

// Orden — compatible PHP 7 y 8
$orderMap = [
    'clasificacion' => "e.CLASIFICACION ASC, e.FECREACION DESC",
    'provincia'     => "e.PROVINCIA ASC, e.FECREACION DESC",
    'nombre'        => "e.NOMBRE ASC, e.APELLIDOS ASC",
    'fecha'         => "e.FECREACION DESC",
];
$orderSQL = isset($orderMap[$ordenar]) ? $orderMap[$ordenar] : "e.FECREACION DESC";

$stmtCount = $db->prepare("SELECT COUNT(*) FROM trans_expedientes e WHERE {$where}");
foreach ($params as $k => $v) $stmtCount->bindValue($k, $v);
$stmtCount->execute();
$total = (int)$stmtCount->fetchColumn();

$stmt = $db->prepare("
    SELECT e.*, m.NOMBREMODALIDAD, f.NOMBREFASE
    FROM trans_expedientes e
    LEFT JOIN m_modalidad_implantacion m ON e.ID_MODALIDAD = m.IDMODALIDAD
    LEFT JOIN m_fases f ON e.ID_FASE = f.IDFASE
    WHERE {$where}
    ORDER BY {$orderSQL}
    LIMIT {$limit} OFFSET {$offset}
");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$rows = $stmt->fetchAll();

$modalidades = $db->query("SELECT IDMODALIDAD, NOMBREMODALIDAD FROM m_modalidad_implantacion WHERE ACTIVO = 1 ORDER BY NOMBREMODALIDAD")->fetchAll();
$fases       = $db->query("SELECT IDFASE, NOMBREFASE FROM m_fases WHERE ACTIVO = 1 ORDER BY IDFASE")->fetchAll();

// Clasificación: iconos y etiquetas
$clasificaciones = [
    1 => ['icon' => '⭐', 'label' => 'Muy interesado',  'color' => '#16a34a', 'bg' => '#dcfce7'],
    2 => ['icon' => '🔵', 'label' => 'Interesado',       'color' => '#2563eb', 'bg' => '#dbeafe'],
    3 => ['icon' => '🟡', 'label' => 'Poco interesado',  'color' => '#d97706', 'bg' => '#fef3c7'],
    4 => ['icon' => '🔴', 'label' => 'Sin interés',      'color' => '#dc2626', 'bg' => '#fee2e2'],
];

// Estilos de fase
$faseStyle = [
    88 => ['bg' => '#f3f4f6', 'color' => '#6b7280', 'icon' => '⬜'],
    24 => ['bg' => '#dbeafe', 'color' => '#1d4ed8', 'icon' => '1️⃣'],
    25 => ['bg' => '#d1fae5', 'color' => '#065f46', 'icon' => '2️⃣'],
    81 => ['bg' => '#fef3c7', 'color' => '#92400e', 'icon' => '📋'],
    82 => ['bg' => '#ede9fe', 'color' => '#5b21b6', 'icon' => '✅'],
];

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">
        <?= $tipo === 'nacional' ? '🇪🇸' : '🌍' ?>
        Expedientes <?= htmlspecialchars($tipoLabel) ?>
        <?php if ($verAnulados): ?><span class="badge-anulados">ANULADOS</span><?php endif; ?>
    </h1>
    <p class="page-subtitle"><?= $total ?> expedientes</p>
</div>

<?php if (!$tieneAnulado || !$tieneClasif): ?>
<div class="alert-info">
    ℹ️ Algunas funcionalidades (clasificación/anulación) requieren ejecutar la migración de base de datos
    <code>migracion_v5.sql</code> en MySQL.
</div>
<?php endif; ?>

<div class="tabs">
    <button class="tab-btn <?= $tipo === 'nacional'      && !$verAnulados ? 'active' : '' ?>" onclick="location.href='expedientes.php?tipo=nacional'">🇪🇸 Nacionales</button>
    <button class="tab-btn <?= $tipo === 'internacional' && !$verAnulados ? 'active' : '' ?>" onclick="location.href='expedientes.php?tipo=internacional'">🌍 Internacionales</button>
    <?php if ($tieneAnulado): ?>
    <button class="tab-btn tab-btn-anulados <?= $verAnulados ? 'active' : '' ?>" onclick="location.href='expedientes.php?tipo=<?= $tipo ?>&anulados=1'">🚫 Anulados</button>
    <?php endif; ?>
</div>

<form method="GET" action="expedientes.php" id="searchForm">
    <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
    <?php if ($verAnulados): ?><input type="hidden" name="anulados" value="1"><?php endif; ?>
    <div class="search-bar">
        <input type="text" name="buscar" id="searchInput" class="search-input"
            placeholder="Buscar por nombre, email, teléfono, ciudad..."
            value="<?= htmlspecialchars($buscar) ?>">
        <button type="submit" class="search-btn">🔍</button>
        <button type="button" class="filter-btn" data-toggle-filter>⚙️ Filtros</button>
    </div>
    <div class="filter-panel <?= ($filtroModalidad || $filtroFase || $filtroClasif !== null) ? 'open' : '' ?>" id="filterPanel">
        <div class="filter-row">
            <div class="filter-group">
                <label>Modalidad</label>
                <select name="modalidad">
                    <option value="0">Todas</option>
                    <?php foreach ($modalidades as $m): ?>
                        <option value="<?= $m['IDMODALIDAD'] ?>" <?= $filtroModalidad == $m['IDMODALIDAD'] ? 'selected' : '' ?>><?= htmlspecialchars($m['NOMBREMODALIDAD']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Fase</label>
                <select name="fase">
                    <option value="0">Todas</option>
                    <?php foreach ($fases as $f): ?>
                        <option value="<?= $f['IDFASE'] ?>" <?= $filtroFase == $f['IDFASE'] ? 'selected' : '' ?>><?= htmlspecialchars($f['NOMBREFASE']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($tieneClasif): ?>
            <div class="filter-group">
                <label>Clasificación</label>
                <select name="clasificacion">
                    <option value="">Todas</option>
                    <option value="0" <?= $filtroClasif === 0 ? 'selected' : '' ?>>Sin clasificar</option>
                    <?php foreach ($clasificaciones as $k => $c): ?>
                        <option value="<?= $k ?>" <?= $filtroClasif === $k ? 'selected' : '' ?>><?= $c['icon'] ?> <?= $c['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="filter-group">
                <label>Ordenar por</label>
                <select name="orden">
                    <option value="fecha"         <?= $ordenar === 'fecha'         ? 'selected' : '' ?>>Fecha (reciente)</option>
                    <?php if ($tieneClasif): ?>
                    <option value="clasificacion" <?= $ordenar === 'clasificacion' ? 'selected' : '' ?>>Clasificación</option>
                    <?php endif; ?>
                    <option value="provincia"     <?= $ordenar === 'provincia'     ? 'selected' : '' ?>>Provincia</option>
                    <option value="nombre"        <?= $ordenar === 'nombre'        ? 'selected' : '' ?>>Nombre</option>
                </select>
            </div>
        </div>
        <div class="filter-actions">
            <button type="button" class="btn-sm btn-secondary" id="clearFilters">Limpiar</button>
            <button type="submit" class="btn-sm btn-primary">Aplicar filtros</button>
        </div>
    </div>
</form>

<?php if (empty($rows)): ?>
    <div class="card"><div class="empty-state"><div class="empty-icon"><?= $verAnulados ? '🚫' : '🔍' ?></div>
    <p><?= $verAnulados ? 'No hay expedientes anulados.' : 'No se encontraron expedientes.' ?></p></div></div>
<?php else: ?>
<div class="exp-list" id="expList">
<?php foreach ($rows as $e):
    $esNacional  = trim(strtolower($e['ZONA'] ?? '')) === '' || in_array(strtolower($e['ZONA'] ?? ''), ['nacional', 'españa', 'spain']);
    $fecha       = $e['FECREACION'] ? date('d/m/Y', strtotime($e['FECREACION'])) : '—';
    $nombre      = htmlspecialchars(trim(($e['NOMBRE'] ?? '') . ' ' . ($e['APELLIDOS'] ?? '')));
    $tel         = htmlspecialchars($e['TELEFONO'] ?? '');
    $email       = htmlspecialchars($e['EMAIL'] ?? '');
    $pob         = htmlspecialchars(trim(($e['POBLACION'] ?? '') . ($e['PROVINCIA'] ? ', ' . $e['PROVINCIA'] : '')));
    $pais        = htmlspecialchars($e['PAIS'] ?? '');
    $mod         = htmlspecialchars($e['NOMBREMODALIDAD'] ?? 'Sin modalidad');
    $faseN       = htmlspecialchars($e['NOMBREFASE'] ?? '');
    $faseId      = (int)($e['ID_FASE'] ?? 88);
    $com         = htmlspecialchars($e['COMENTARIO'] ?? '');
    $id          = (int)$e['IDEXPEDIENTE'];
    $fs          = isset($faseStyle[$faseId]) ? $faseStyle[$faseId] : $faseStyle[88];
    $hasEmail    = !empty(trim($e['EMAIL'] ?? ''));
    $clasif      = ($tieneClasif && $e['CLASIFICACION'] !== null) ? (int)$e['CLASIFICACION'] : null;
    $isAnulado   = ($tieneAnulado && (int)($e['ANULADO'] ?? 0) === 1);
    $clasifData  = ($clasif !== null && isset($clasificaciones[$clasif])) ? $clasificaciones[$clasif] : null;
?>
<div class="exp-card <?= $esNacional ? 'nacional' : 'internacional' ?><?= $isAnulado ? ' exp-anulado' : '' ?>" data-id="<?= $id ?>">

    <!-- Cabecera -->
    <div class="exp-card-header">
        <span class="exp-name"><?= $esNacional ? '🇪🇸' : '🌍' ?> <?= $nombre ?: "Expediente #{$id}" ?></span>
        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
            <?php if ($isAnulado): ?>
                <span class="badge-anulado-card">🚫 Anulado</span>
            <?php endif; ?>
            <span class="exp-date"><?= $fecha ?></span>
        </div>
    </div>

    <!-- Meta badges -->
    <div class="exp-card-meta">
        <span class="badge badge-<?= $esNacional ? 'green' : 'blue' ?>"><?= $esNacional ? 'Nacional' : 'Internacional' ?></span>
        <?php if ($mod): ?><span class="badge badge-gray"><?= $mod ?></span><?php endif; ?>
        <span class="fase-badge" id="fasebadge-<?= $id ?>"
            style="background:<?= $fs['bg'] ?>;color:<?= $fs['color'] ?>;padding:3px 10px;border-radius:20px;font-size:0.72rem;font-weight:700;">
            <?= $fs['icon'] ?> <span class="fase-label"><?= $faseN ?: 'Sin fase' ?></span>
        </span>
        <?php if ($tieneClasif): ?>
        <span class="clasif-badge" id="clasifbadge-<?= $id ?>"
            style="<?= $clasifData ? "background:{$clasifData['bg']};color:{$clasifData['color']};" : 'background:#f3f4f6;color:#9ca3af;' ?>padding:3px 10px;border-radius:20px;font-size:0.72rem;font-weight:700;cursor:pointer;"
            onclick="toggleClasifPanel(<?= $id ?>, event)">
            <?= $clasifData ? $clasifData['icon'] . ' ' . $clasifData['label'] : '⚪ Sin clasificar' ?>
        </span>
        <?php endif; ?>
    </div>

    <!-- Panel rápido de clasificación -->
    <?php if ($tieneClasif): ?>
    <div class="clasif-panel" id="clasifpanel-<?= $id ?>" style="display:none" onclick="event.stopPropagation()">
        <div class="clasif-panel-title">Clasificar expediente</div>
        <div class="clasif-btns">
            <?php foreach ($clasificaciones as $k => $c): ?>
            <button class="clasif-btn <?= $clasif === $k ? 'clasif-btn-active' : '' ?>"
                style="--cbg:<?= $c['bg'] ?>;--ccolor:<?= $c['color'] ?>"
                onclick="setClasificacion(<?= $id ?>, <?= $k ?>, event)">
                <?= $c['icon'] ?> <?= $c['label'] ?>
            </button>
            <?php endforeach; ?>
            <button class="clasif-btn clasif-btn-clear" onclick="setClasificacion(<?= $id ?>, 0, event)">
                ✕ Quitar clasificación
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Contacto -->
    <?php if ($pob || $pais || $tel || $email): ?>
    <div class="exp-contact">
        <?php if ($pob): ?><span>📍 <?= $pob ?></span><?php elseif ($pais): ?><span>📍 <?= $pais ?></span><?php endif; ?>
        <?php if ($tel): ?><span>📞 <?= $tel ?></span><?php endif; ?>
        <?php if ($email): ?><span>✉️ <?= $email ?></span><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($com): ?><div class="exp-comment"><?= $com ?></div><?php endif; ?>

    <?php if (!$isAnulado): ?>
    <!-- DOCUMENTACIÓN -->
    <div class="doc-send-section" onclick="event.stopPropagation()">
        <div class="doc-send-title">📨 Enviar documentación</div>
        <?php if ($hasEmail): ?>
        <div class="doc-send-grid">
            <button class="doc-send-btn doc-btn-fase1" onclick="previewEmail(<?= $id ?>, '1doc', event)">
                <span class="doc-btn-num">1</span>
                <span class="doc-btn-text"><strong>1ª Documentación</strong><small>Dossier + Vídeo</small></span>
                <span class="doc-btn-arrow">→</span>
            </button>
            <button class="doc-send-btn doc-btn-fase2" onclick="previewEmail(<?= $id ?>, '2doc', event)">
                <span class="doc-btn-num">2</span>
                <span class="doc-btn-text"><strong>2ª Documentación</strong><small>Vídeos informativos</small></span>
                <span class="doc-btn-arrow">→</span>
            </button>
            <button class="doc-send-btn doc-btn-pre" onclick="previewEmail(<?= $id ?>, 'precontrato', event)">
                <span class="doc-btn-num">3</span>
                <span class="doc-btn-text"><strong>Precontrato</strong><small>Fase precontrato</small></span>
                <span class="doc-btn-arrow">→</span>
            </button>
            <button class="doc-send-btn doc-btn-cont" onclick="previewEmail(<?= $id ?>, 'contrato', event)">
                <span class="doc-btn-num">4</span>
                <span class="doc-btn-text"><strong>Contrato</strong><small>Fase contrato</small></span>
                <span class="doc-btn-arrow">→</span>
            </button>
        </div>
        <?php else: ?>
        <div class="doc-no-email">⚠️ Sin email registrado — no se puede enviar documentación</div>
        <?php endif; ?>
    </div>

    <!-- Cambio de fase -->
    <div class="fase-change-section" onclick="event.stopPropagation()">
        <span class="fase-change-label">Cambiar fase:</span>
        <div class="fase-change-row">
            <select class="fase-select" id="fasesel-<?= $id ?>">
                <?php foreach ($fases as $f): ?>
                    <option value="<?= $f['IDFASE'] ?>" <?= $faseId == $f['IDFASE'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($f['NOMBREFASE']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn-cambiar-fase" onclick="cambiarFase(this, <?= $id ?>)">Guardar fase</button>
        </div>
    </div>

    <!-- Anular expediente -->
    <?php if ($tieneAnulado): ?>
    <div class="anular-section" onclick="event.stopPropagation()">
        <button class="btn-anular" onclick="anularExpediente(<?= $id ?>, this)">🚫 Anular expediente</button>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- Reactivar -->
    <?php if ($tieneAnulado): ?>
    <div class="anular-section" onclick="event.stopPropagation()">
        <button class="btn-reactivar" onclick="reactivarExpediente(<?= $id ?>, this)">✅ Reactivar expediente</button>
        <button class="btn-borrar-anulado" onclick="borrarExpedienteAnulado(<?= $id ?>, this)">🗑️ Borrar definitivamente</button>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div>
<?php endforeach; ?>
</div>

<?php if ($total > $limit): ?>
<div class="load-more-wrap">
    <button class="btn-load-more" id="loadMoreBtn"
        data-offset="<?= $limit ?>"
        data-endpoint="../api/expedientes.php"
        data-params="tipo=<?= urlencode($tipo) ?>&buscar=<?= urlencode($buscar) ?>&modalidad=<?= $filtroModalidad ?>&fase=<?= $filtroFase ?>">
        ⬇️ Cargar más expedientes
    </button>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- MODAL PREVIEW EMAIL -->
<div class="modal-backdrop" id="emailPreviewModal" style="display:none">
    <div class="modal email-modal" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="emailModalTitle">Vista previa del email</span>
            <button class="modal-close" onclick="closeEmailModal()">✕</button>
        </div>
        <div class="email-meta-block">
            <div class="email-meta-row"><span class="email-meta-k">Para:</span><span id="emailPara" class="email-meta-v">—</span></div>
            <div class="email-meta-row"><span class="email-meta-k">Asunto:</span><span id="emailAsunto" class="email-meta-v">—</span></div>
        </div>
        <div class="email-iframe-wrap">
            <div id="emailLoading" style="text-align:center;padding:40px;color:#6b7280;">Cargando vista previa...</div>
            <iframe id="emailIframe" srcdoc="" style="display:none;width:100%;height:420px;border:none;"></iframe>
        </div>
        <div class="email-modal-footer">
            <button class="btn-cancel-email" onclick="closeEmailModal()">Cancelar</button>
            <button class="btn-send-confirm" id="btnConfirmSend">📨 Enviar email</button>
        </div>
    </div>
</div>

<style>
.alert-info { background:#eff6ff; border:1.5px solid #93c5fd; color:#1e40af; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:0.85rem; }
.alert-info code { background:#dbeafe; padding:2px 6px; border-radius:4px; font-size:0.82rem; }

/* ── CLASIFICACIÓN ──────────────────────────────────────── */
.clasif-panel { background:#f9fafb; border:1.5px solid #e5e7eb; border-radius:12px; padding:12px 14px; margin-top:6px; }
.clasif-panel-title { font-size:.72rem; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; margin-bottom:10px; }
.clasif-btns { display:flex; flex-wrap:wrap; gap:8px; }
.clasif-btn { padding:7px 14px; border-radius:20px; border:2px solid transparent; background:var(--cbg,#f3f4f6); color:var(--ccolor,#374151); font-size:.8rem; font-weight:700; cursor:pointer; transition:all .15s; font-family:inherit; }
.clasif-btn:hover { filter:brightness(.92); transform:translateY(-1px); }
.clasif-btn-active { border-color:var(--ccolor,#374151) !important; box-shadow:0 0 0 3px rgba(0,0,0,.08); }
.clasif-btn-clear { background:#f3f4f6; color:#9ca3af; font-size:.75rem; }

/* ── ANULACIÓN ──────────────────────────────────────────── */
.anular-section { margin-top:12px; padding-top:10px; border-top:1px solid #f3f4f6; }
.btn-anular { background:none; border:1.5px solid #fca5a5; color:#dc2626; padding:6px 16px; border-radius:8px; font-size:.78rem; font-weight:700; cursor:pointer; transition:all .18s; font-family:inherit; }
.btn-anular:hover { background:#fee2e2; }
.btn-reactivar { background:none; border:1.5px solid #86efac; color:#16a34a; padding:6px 16px; border-radius:8px; font-size:.78rem; font-weight:700; cursor:pointer; transition:all .18s; font-family:inherit; }
.btn-reactivar:hover { background:#dcfce7; }
.btn-borrar-anulado { background:none; border:1.5px solid #991b1b; color:#991b1b; padding:6px 16px; border-radius:8px; font-size:.78rem; font-weight:700; cursor:pointer; transition:all .18s; font-family:inherit; margin-left:8px; }
.btn-borrar-anulado:hover { background:#991b1b; color:white; }
@media(max-width:500px){ .btn-borrar-anulado { margin-left:0; margin-top:8px; } }
.exp-anulado { opacity:.65; border-left-color:#dc2626 !important; }
.badge-anulado-card { background:#fee2e2; color:#dc2626; font-size:.68rem; font-weight:700; padding:2px 8px; border-radius:10px; }
.badge-anulados { background:#dc2626; color:white; font-size:.65rem; font-weight:700; padding:3px 10px; border-radius:10px; vertical-align:middle; margin-left:8px; }
.tab-btn-anulados { border-color:#fca5a5 !important; color:#dc2626 !important; }
.tab-btn-anulados.active { background:#dc2626 !important; color:white !important; border-color:#dc2626 !important; }

/* ── DOC SEND ───────────────────────────────────────────── */
.doc-send-section { margin-top:14px; padding-top:14px; border-top:2px dashed #e5e7eb; }
.doc-send-title { font-size:.8rem; font-weight:700; color:#374151; margin-bottom:10px; text-transform:uppercase; letter-spacing:.05em; }
.doc-send-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:8px; }
@media(max-width:500px){ .doc-send-grid{ grid-template-columns:1fr; } }
.doc-send-btn { display:flex; align-items:center; gap:10px; padding:12px 14px; border-radius:12px; border:2px solid transparent; cursor:pointer; transition:all .18s; text-align:left; width:100%; font-family:inherit; }
.doc-send-btn:hover { transform:translateY(-2px); box-shadow:0 4px 14px rgba(0,0,0,.15); }
.doc-btn-num { flex-shrink:0; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1rem; font-weight:800; background:rgba(255,255,255,.35); }
.doc-btn-text { flex:1; line-height:1.3; }
.doc-btn-text strong { font-size:.82rem; display:block; }
.doc-btn-text small { font-size:.68rem; opacity:.8; display:block; }
.doc-btn-arrow { font-size:1rem; opacity:.6; }
.doc-btn-fase1 { background:linear-gradient(135deg,#3b82f6,#1d4ed8); color:white; }
.doc-btn-fase2 { background:linear-gradient(135deg,#10b981,#065f46); color:white; }
.doc-btn-pre   { background:linear-gradient(135deg,#f59e0b,#d97706); color:white; }
.doc-btn-cont  { background:linear-gradient(135deg,#8b5cf6,#5b21b6); color:white; }
.doc-no-email  { font-size:.78rem; color:#9ca3af; padding:10px 0; font-style:italic; }

/* ── FASE ───────────────────────────────────────────────── */
.fase-change-section { margin-top:12px; padding-top:12px; border-top:1px solid #f3f4f6; display:flex; flex-direction:column; gap:6px; }
.fase-change-label { font-size:.72rem; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.04em; }
.fase-change-row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.fase-select { flex:1; min-width:180px; font-size:.78rem; padding:6px 10px; border:1.5px solid #e5e7eb; border-radius:8px; background:#f9fafb; color:#374151; }
.btn-cambiar-fase { padding:7px 16px; background:var(--green-600); color:white; border:none; border-radius:8px; font-size:.78rem; font-weight:700; cursor:pointer; white-space:nowrap; font-family:inherit; }
.btn-cambiar-fase:hover { background:var(--green-700); }

/* ── EMAIL MODAL ────────────────────────────────────────── */
.email-modal { max-width:680px; width:96vw; padding:0; overflow:hidden; }
.email-meta-block { padding:14px 20px; background:#f9fafb; border-bottom:1px solid #e5e7eb; }
.email-meta-row { display:flex; gap:8px; font-size:.82rem; margin-bottom:4px; }
.email-meta-k { font-weight:700; color:#6b7280; min-width:56px; }
.email-meta-v { color:#111827; }
.email-iframe-wrap { min-height:120px; }
.email-modal-footer { padding:14px 20px; display:flex; justify-content:flex-end; gap:10px; border-top:1px solid #e5e7eb; }
.btn-send-confirm { background:linear-gradient(135deg,#16a34a,#15803d); color:white; padding:10px 24px; border-radius:10px; border:none; font-weight:700; cursor:pointer; font-size:.9rem; }
.btn-send-confirm:disabled { opacity:.6; cursor:not-allowed; }
.btn-cancel-email { background:#f3f4f6; color:#374151; padding:10px 20px; border-radius:10px; border:none; font-weight:600; cursor:pointer; font-size:.9rem; }
</style>

<script>
var clasificaciones = <?= json_encode($clasificaciones) ?>;
var tieneClasif     = <?= $tieneClasif ? 'true' : 'false' ?>;
var tieneAnulado    = <?= $tieneAnulado ? 'true' : 'false' ?>;

// ── CLASIFICACIÓN ────────────────────────────────────────
function toggleClasifPanel(id, e) {
    if (e) e.stopPropagation();
    var panel  = document.getElementById('clasifpanel-' + id);
    var isOpen = panel.style.display !== 'none';
    document.querySelectorAll('.clasif-panel').forEach(function(p) { p.style.display = 'none'; });
    if (!isOpen) panel.style.display = 'block';
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.clasif-badge') && !e.target.closest('.clasif-panel')) {
        document.querySelectorAll('.clasif-panel').forEach(function(p) { p.style.display = 'none'; });
    }
});

function setClasificacion(expId, valor, e) {
    if (e) e.stopPropagation();
    var fd = new FormData();
    fd.append('accion', 'clasificacion');
    fd.append('expediente_id', expId);
    fd.append('valor', valor === 0 ? '' : valor);
    fetch('../api/actualizar_expediente.php', { method:'POST', body:fd })
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (!data.ok) { alert('Error: ' + data.error); return; }
            var badge = document.getElementById('clasifbadge-' + expId);
            var panel = document.getElementById('clasifpanel-' + expId);
            panel.querySelectorAll('.clasif-btn').forEach(function(b){ b.classList.remove('clasif-btn-active'); });
            if (data.clasificacion && clasificaciones[data.clasificacion]) {
                var c = clasificaciones[data.clasificacion];
                badge.style.background = c.bg;
                badge.style.color = c.color;
                badge.textContent = c.icon + ' ' + c.label;
            } else {
                badge.style.background = '#f3f4f6';
                badge.style.color = '#9ca3af';
                badge.textContent = '⚪ Sin clasificar';
            }
            panel.style.display = 'none';
            showToast('✅ Clasificación guardada');
        })
        .catch(function(){ alert('Error al guardar'); });
}

// ── ANULACIÓN ────────────────────────────────────────────
function anularExpediente(expId, btn) {
    if (!confirm('¿Anular este expediente? Desaparecerá de las búsquedas normales.')) return;
    var fd = new FormData();
    fd.append('accion', 'anular');
    fd.append('expediente_id', expId);
    fetch('../api/actualizar_expediente.php', { method:'POST', body:fd })
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (!data.ok) { alert('Error: ' + data.error); return; }
            var card = document.querySelector('.exp-card[data-id="' + expId + '"]');
            if (card) {
                card.style.transition = 'opacity .4s, transform .4s';
                card.style.opacity = '0';
                card.style.transform = 'translateX(30px)';
                setTimeout(function(){ card.remove(); }, 420);
            }
            showToast('🚫 Expediente anulado');
        })
        .catch(function(){ alert('Error al anular'); });
}

function reactivarExpediente(expId, btn) {
    var fd = new FormData();
    fd.append('accion', 'reactivar');
    fd.append('expediente_id', expId);
    fetch('../api/actualizar_expediente.php', { method:'POST', body:fd })
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (!data.ok) { alert('Error: ' + data.error); return; }
            var card = document.querySelector('.exp-card[data-id="' + expId + '"]');
            if (card) { card.style.transition='opacity .4s'; card.style.opacity='0'; setTimeout(function(){ card.remove(); }, 420); }
            showToast('✅ Expediente reactivado');
        })
        .catch(function(){ alert('Error al reactivar'); });
}

function borrarExpedienteAnulado(expId, btn) {
    if (!confirm('Esta acción eliminará definitivamente el expediente anulado. No se puede deshacer. ¿Continuar?')) return;
    btn.disabled = true;
    btn.textContent = '⏳ Borrando...';
    var fd = new FormData();
    fd.append('accion', 'borrar_anulado');
    fd.append('expediente_id', expId);
    fetch('../api/actualizar_expediente.php', { method:'POST', body:fd })
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (!data.ok) {
                alert('Error: ' + data.error);
                btn.disabled = false;
                btn.textContent = '🗑️ Borrar definitivamente';
                return;
            }
            var card = document.querySelector('.exp-card[data-id="' + expId + '"]');
            if (card) {
                card.style.transition = 'opacity .35s, transform .35s';
                card.style.opacity = '0';
                card.style.transform = 'translateX(30px)';
                setTimeout(function(){ card.remove(); }, 370);
            }
            showToast('🗑️ Expediente eliminado definitivamente');
        })
        .catch(function(){
            alert('Error al borrar el expediente');
            btn.disabled = false;
            btn.textContent = '🗑️ Borrar definitivamente';
        });
}

// ── EMAIL ────────────────────────────────────────────────
var _pendingExpId = null, _pendingTipo = null;
var tipoLabels = { '1doc':'1ª Documentación','2doc':'2ª Documentación','precontrato':'Precontrato','contrato':'Contrato' };

function previewEmail(expId, tipo, evt) {
    if (evt) evt.stopPropagation();
    _pendingExpId = expId; _pendingTipo = tipo;
    document.getElementById('emailModalTitle').textContent = '📧 ' + (tipoLabels[tipo] || tipo);
    document.getElementById('emailPara').textContent = '…';
    document.getElementById('emailAsunto').textContent = '…';
    document.getElementById('emailLoading').style.display = 'block';
    document.getElementById('emailIframe').style.display = 'none';
    document.getElementById('emailIframe').srcdoc = '';
    document.getElementById('btnConfirmSend').disabled = false;
    document.getElementById('btnConfirmSend').textContent = '📨 Enviar email';
    var modal = document.getElementById('emailPreviewModal');
    modal.style.display = 'flex';
    setTimeout(function(){ modal.classList.add('open'); }, 10);

    fetch('../api/send_email.php?expediente_id=' + expId + '&tipo=' + tipo + '&preview=1')
        .then(function(r){ return r.json(); })
        .then(function(data) {
            document.getElementById('emailLoading').style.display = 'none';
            if (!data.ok) { alert('Error: ' + data.error); closeEmailModal(); return; }
            document.getElementById('emailPara').textContent = data.nombre + ' <' + data.para + '>';
            document.getElementById('emailAsunto').textContent = data.asunto;
            document.getElementById('emailIframe').srcdoc = data.html;
            document.getElementById('emailIframe').style.display = 'block';
        })
        .catch(function(){ alert('Error al cargar la vista previa'); closeEmailModal(); });
}

function closeEmailModal() {
    var modal = document.getElementById('emailPreviewModal');
    modal.classList.remove('open');
    setTimeout(function(){ modal.style.display = 'none'; }, 220);
    _pendingExpId = null; _pendingTipo = null;
}

document.getElementById('btnConfirmSend').addEventListener('click', function() {
    if (!_pendingExpId || !_pendingTipo) return;
    var btn = this;
    btn.disabled = true; btn.textContent = '⏳ Enviando...';
    var fd = new FormData();
    fd.append('expediente_id', _pendingExpId);
    fd.append('tipo', _pendingTipo);
    fetch('../api/send_email.php', { method:'POST', body:fd })
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (data.ok) {
                var expId = _pendingExpId;
                closeEmailModal();
                showToast('✅ Email enviado correctamente');
                if (data.nueva_fase_id) {
                    updateFaseBadge(expId, data.nueva_fase_id, data.nueva_fase);
                    var sel = document.getElementById('fasesel-' + expId);
                    if (sel) sel.value = data.nueva_fase_id;
                }
            } else {
                alert('❌ ' + data.error);
                btn.disabled = false; btn.textContent = '📨 Enviar email';
            }
        })
        .catch(function(){ alert('Error al enviar'); btn.disabled = false; btn.textContent = '📨 Enviar email'; });
});

document.getElementById('emailPreviewModal').addEventListener('click', function(e) {
    if (e.target === this) closeEmailModal();
});

// ── FASE ─────────────────────────────────────────────────
function cambiarFase(btn, expId) {
    var sel = document.getElementById('fasesel-' + expId);
    var faseId = parseInt(sel.value);
    btn.disabled = true; btn.textContent = '…';
    var fd = new FormData();
    fd.append('expediente_id', expId); fd.append('fase_id', faseId);
    fetch('../api/cambiar_fase.php', { method:'POST', body:fd })
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (data.ok) {
                updateFaseBadge(expId, data.nueva_fase_id, data.nueva_fase);
                showToast('✅ Fase actualizada');
                btn.textContent = '✓ Guardado';
                setTimeout(function(){ btn.textContent = 'Guardar fase'; btn.disabled = false; }, 1400);
            } else { alert('Error: ' + data.error); btn.textContent = 'Guardar fase'; btn.disabled = false; }
        })
        .catch(function(){ alert('Error'); btn.textContent = 'Guardar fase'; btn.disabled = false; });
}

var faseStyles = { 88:{bg:'#f3f4f6',color:'#6b7280',icon:'⬜'}, 24:{bg:'#dbeafe',color:'#1d4ed8',icon:'1️⃣'}, 25:{bg:'#d1fae5',color:'#065f46',icon:'2️⃣'}, 81:{bg:'#fef3c7',color:'#92400e',icon:'📋'}, 82:{bg:'#ede9fe',color:'#5b21b6',icon:'✅'} };
function updateFaseBadge(expId, faseId, faseNombre) {
    var badge = document.getElementById('fasebadge-' + expId);
    if (!badge) return;
    var s = faseStyles[faseId] || faseStyles[88];
    badge.style.background = s.bg; badge.style.color = s.color;
    badge.innerHTML = s.icon + ' <span class="fase-label">' + faseNombre + '</span>';
}

function showToast(msg) {
    var t = document.createElement('div');
    t.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#111827;color:white;padding:12px 20px;border-radius:12px;font-size:.9rem;font-weight:600;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.3);transition:opacity .3s';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function(){ t.style.opacity='0'; setTimeout(function(){ t.remove(); }, 350); }, 2500);
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeEmailModal();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
