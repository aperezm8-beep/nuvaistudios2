<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$db = getDB();
$basePath = '../';

$tipo   = $_GET['tipo'] ?? 'centros_comerciales';
$buscar = trim($_GET['buscar'] ?? '');
$filtroEstado = (int)($_GET['estado'] ?? 0);
$limit  = 30;
$offset = 0;

$catMap = [
    'centros_comerciales' => ['id'=>25,'label'=>'Centros Comerciales','icon'=>'🏬','color'=>'#f97316','page'=>'op-cc'],
    'parkings_publicos'   => ['id'=>26,'label'=>'Parkings Públicos',   'icon'=>'🅿️','color'=>'#3b82f6','page'=>'op-parking'],
];
if (!isset($catMap[$tipo])) $tipo = 'centros_comerciales';
$cat = $catMap[$tipo];
$currentPage = $cat['page'];
$pageTitle   = 'Oportunidades — ' . $cat['label'];
$catId       = $cat['id'];

$where  = "p.Activo = 1 AND p.ID_CATEGORIA = :catId";
$params = [':catId' => $catId];

if ($buscar !== '') {
    $b = '%' . $buscar . '%';
    $where .= " AND (p.NOMBRE_COM LIKE :b1 OR p.NOMBRE LIKE :b2 OR p.PROPIETARIO LIKE :b3 OR p.POBLACION LIKE :b4 OR p.PROVINCIA LIKE :b5 OR p.PERSONA_CONTACTO LIKE :b6 OR p.EMAIL LIKE :b7 OR p.GERENCIA LIKE :b8 OR p.COMERCIALIZACION LIKE :b9)";
    $params[':b1'] = $b; $params[':b2'] = $b; $params[':b3'] = $b;
    $params[':b4'] = $b; $params[':b5'] = $b; $params[':b6'] = $b;
    $params[':b7'] = $b; $params[':b8'] = $b; $params[':b9'] = $b;
}
if ($filtroEstado) {
    $where .= " AND p.ID_ESTADO = :estado";
    $params[':estado'] = $filtroEstado;
}

$stmtCount = $db->prepare("SELECT COUNT(*) FROM trans_proveedores p WHERE {$where}");
foreach ($params as $k => $v) $stmtCount->bindValue($k, $v);
$stmtCount->execute();
$total = (int)$stmtCount->fetchColumn();

$stmt = $db->prepare("SELECT p.*, es.NOMBREESTADO FROM trans_proveedores p LEFT JOIN m_estados es ON p.ID_ESTADO = es.IDESTADO WHERE {$where} ORDER BY p.NOMBRE_COM, p.NOMBRE, p.FECREACION DESC LIMIT {$limit} OFFSET {$offset}");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$rows = $stmt->fetchAll();

$estados = $db->query("SELECT IDESTADO, NOMBREESTADO FROM m_estados WHERE ACTIVO = 1 ORDER BY NOMBREESTADO")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><?= $cat['icon'] ?> Oportunidades de Negocio</h1>
    <p class="page-subtitle"><?= htmlspecialchars($cat['label']) ?> — <?= $total ?> registros</p>
</div>

<div class="tabs">
    <button class="tab-btn <?= $tipo === 'centros_comerciales' ? 'active' : '' ?>" onclick="location.href='oportunidades.php?tipo=centros_comerciales'">🏬 Centros Comerciales</button>
    <button class="tab-btn <?= $tipo === 'parkings_publicos' ? 'active' : '' ?>" onclick="location.href='oportunidades.php?tipo=parkings_publicos'">🅿️ Parkings Públicos</button>
</div>

<form method="GET" action="oportunidades.php" id="searchForm">
    <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
    <input type="hidden" name="estado" value="<?= $filtroEstado ?>">
    <div class="search-bar">
        <input type="text" name="buscar" id="searchInput" class="search-input" placeholder="Buscar por nombre, ciudad, gerencia, cadena..." value="<?= htmlspecialchars($buscar) ?>">
        <button type="submit" class="search-btn">🔍</button>
        <button type="button" class="filter-btn" data-toggle-filter>⚙️ Filtros</button>
    </div>
    <div class="filter-panel <?= $filtroEstado ? 'open' : '' ?>" id="filterPanel">
        <div class="filter-row">
            <div class="filter-group">
                <label>Estado</label>
                <select name="estado">
                    <option value="0">Todos</option>
                    <?php foreach ($estados as $es): ?>
                        <option value="<?= $es['IDESTADO'] ?>" <?= $filtroEstado == $es['IDESTADO'] ? 'selected' : '' ?>><?= htmlspecialchars($es['NOMBREESTADO']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="filter-actions">
            <button type="button" class="btn-sm btn-secondary" id="clearFilters">Limpiar</button>
            <button type="submit" class="btn-sm btn-primary">Aplicar</button>
        </div>
    </div>
</form>

<?php if (empty($rows)): ?>
    <div class="card"><div class="empty-state"><div class="empty-icon">🔍</div><p>No se encontraron resultados.</p></div></div>
<?php else: ?>
<div class="oport-grid" id="oportunList">
<?php foreach ($rows as $p):
    $id          = (int)$p['IDPROVEEDOR'];
    $nombre      = htmlspecialchars(trim($p['NOMBRE_COM'] ?: trim(($p['NOMBRE']??'').' '.($p['APELLIDOS']??''))));
    $cadena      = '';
    $gerencia    = htmlspecialchars($p['GERENCIA'] ?? '');
    $comercializ = htmlspecialchars($p['COMERCIALIZACION'] ?? '');
    $propietario = htmlspecialchars($p['PROPIETARIO'] ?? '');
    $promotor    = htmlspecialchars($p['PROMOTOR'] ?? '');
    $pob         = htmlspecialchars(trim(($p['POBLACION']??'').($p['PROVINCIA']?', '.$p['PROVINCIA']:'')));
    $cp          = htmlspecialchars($p['COD_POSTAL'] ?? '');
    $tel         = htmlspecialchars($p['TLF_MOVIL'] ?: ($p['TLF_FIJO'] ?? ''));
    $email       = htmlspecialchars($p['EMAIL'] ?? '');
    $contacto    = htmlspecialchars($p['PERSONA_CONTACTO'] ?? '');
    $cargo       = '';
    $whatsapp    = $p['WHATSAPP'] ?? '';
    $comentario  = htmlspecialchars($p['COMENTARIO'] ?? '');
    $est         = htmlspecialchars($p['NOMBREESTADO'] ?? '');
    $fecha       = $p['FECREACION'] ? date('d/m/Y', strtotime($p['FECREACION'])) : '—';
    $apertAnyo   = htmlspecialchars($p['APERT_AÑO'] ?? '');
    $sba         = htmlspecialchars($p['SBA'] ?? '');
    $comercios   = htmlspecialchars($p['COMERCIOS'] ?? '');
    $afluencia   = htmlspecialchars($p['AFLUENCIA'] ?? '');
    $locomotora  = htmlspecialchars($p['LOCOMOTORA'] ?? '');
    $locSup      = htmlspecialchars($p['LOC_SUP'] ?? '');
    $renta       = htmlspecialchars($p['RENTA'] ?? '');
    $plantas     = htmlspecialchars($p['PLANTAS'] ?? '');
    $lavadero    = htmlspecialchars($p['LAVADERO'] ?? '');
    $operario    = htmlspecialchars($p['OPERARIO'] ?? '');
    $pkDesc      = htmlspecialchars($p['PK_DESC'] ?? '');
    $pkCub       = htmlspecialchars($p['PK_CUB'] ?? '');
    $parking     = htmlspecialchars($p['PARKING'] ?? '');
    $pkUso       = htmlspecialchars($p['PK_USO'] ?? '');
    $plazasPub   = $p['PLAZAS_PUBLICAS']   !== null ? (int)$p['PLAZAS_PUBLICAS']   : null;
    $plazasRes   = $p['PLAZAS_RESIDENTES'] !== null ? (int)$p['PLAZAS_RESIDENTES'] : null;
    $plazasAbo   = $p['PLAZAS_ABONADOS']   !== null ? (int)$p['PLAZAS_ABONADOS']   : null;
    $plazasTot   = $p['PLAZAS_TOTALES']    !== null ? (int)$p['PLAZAS_TOTALES']    : null;
    $cargEv      = $p['CARGADORES_EV']     !== null ? (int)$p['CARGADORES_EV']     : null;
    $ops2023     = htmlspecialchars($p['OPERACIONES_2023'] ?? '');
?>
<div class="oport-card" data-id="<?= $id ?>" style="border-top:3px solid <?= $cat['color'] ?>">
    <div class="oport-card-header">
        <div>
            <div class="oport-name"><?= $cat['icon'] ?> <?= $nombre ?: "#{$id}" ?></div>
            <?php if ($cadena): ?><div class="oport-chain">🔗 <?= $cadena ?></div><?php endif; ?>
        </div>
        <div class="oport-header-right">
            <span class="oport-fecha"><?= $fecha ?></span>
            <?php if ($est): ?><span class="badge badge-orange" style="font-size:.65rem"><?= $est ?></span><?php endif; ?>
        </div>
    </div>

    <?php if ($pob): ?>
    <div class="oport-info-row"><span class="oport-info-icon">📍</span><span><?= $pob ?><?= $cp ? " (CP: {$cp})" : '' ?></span></div>
    <?php endif; ?>
    <?php if ($contacto): ?>
    <div class="oport-info-row"><span class="oport-info-icon">👤</span><span><?= $contacto ?><?= $cargo ? " — {$cargo}" : '' ?></span></div>
    <?php endif; ?>
    <?php if ($tel): ?>
    <div class="oport-info-row"><span class="oport-info-icon">📞</span><a href="tel:<?= $tel ?>" style="color:inherit"><?= $tel ?></a><?php if ($whatsapp): ?>&nbsp;<a href="https://wa.me/<?= preg_replace('/\D/','',htmlspecialchars($whatsapp)) ?>" target="_blank" style="color:#25d366">💬</a><?php endif; ?></div>
    <?php endif; ?>
    <?php if ($email): ?>
    <div class="oport-info-row"><span class="oport-info-icon">✉️</span><a href="mailto:<?= $email ?>" style="color:inherit"><?= $email ?></a></div>
    <?php endif; ?>

    <?php if ($gerencia || $comercializ || $propietario || $promotor): ?>
    <div class="oport-section">
        <?php if ($gerencia): ?><div class="oport-kv"><span class="oport-k">Gerencia</span><span class="oport-v"><?= $gerencia ?></span></div><?php endif; ?>
        <?php if ($comercializ): ?><div class="oport-kv"><span class="oport-k">Comercialización</span><span class="oport-v"><?= $comercializ ?></span></div><?php endif; ?>
        <?php if ($propietario): ?><div class="oport-kv"><span class="oport-k">Propietario</span><span class="oport-v"><?= $propietario ?></span></div><?php endif; ?>
        <?php if ($promotor): ?><div class="oport-kv"><span class="oport-k">Promotor</span><span class="oport-v"><?= $promotor ?></span></div><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($tipo === 'centros_comerciales'): ?>
    <?php if ($apertAnyo || $sba || $comercios || $afluencia || $plantas || $renta): ?>
    <div class="oport-section oport-stats-grid">
        <?php if ($apertAnyo): ?><div class="oport-stat"><div class="oport-stat-label">Apertura</div><div class="oport-stat-val"><?= $apertAnyo ?></div></div><?php endif; ?>
        <?php if ($sba): ?><div class="oport-stat"><div class="oport-stat-label">SBA (m²)</div><div class="oport-stat-val"><?= $sba ?></div></div><?php endif; ?>
        <?php if ($comercios): ?><div class="oport-stat"><div class="oport-stat-label">Comercios</div><div class="oport-stat-val"><?= $comercios ?></div></div><?php endif; ?>
        <?php if ($afluencia): ?><div class="oport-stat"><div class="oport-stat-label">Afluencia</div><div class="oport-stat-val"><?= $afluencia ?></div></div><?php endif; ?>
        <?php if ($plantas): ?><div class="oport-stat"><div class="oport-stat-label">Plantas</div><div class="oport-stat-val"><?= $plantas ?></div></div><?php endif; ?>
        <?php if ($renta): ?><div class="oport-stat"><div class="oport-stat-label">Renta</div><div class="oport-stat-val"><?= $renta ?></div></div><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($locomotora): ?><div class="oport-kv"><span class="oport-k">Locomotora</span><span class="oport-v"><?= $locomotora ?></span></div><?php endif; ?>
    <?php if ($locSup): ?><div class="oport-kv"><span class="oport-k">Loc. Sup.</span><span class="oport-v"><?= $locSup ?></span></div><?php endif; ?>
    <?php if ($lavadero): ?><div class="oport-kv"><span class="oport-k">Lavadero</span><span class="oport-v"><?= $lavadero ?></span></div><?php endif; ?>
    <?php if ($operario): ?><div class="oport-kv"><span class="oport-k">Operario</span><span class="oport-v"><?= $operario ?></span></div><?php endif; ?>

    <?php else: ?>
    <?php if ($plazasTot !== null || $plazasPub !== null || $plazasRes !== null || $plazasAbo !== null || $cargEv !== null): ?>
    <div class="oport-section oport-stats-grid">
        <?php if ($plazasTot !== null): ?><div class="oport-stat"><div class="oport-stat-label">Plazas tot.</div><div class="oport-stat-val"><?= number_format($plazasTot) ?></div></div><?php endif; ?>
        <?php if ($plazasPub !== null): ?><div class="oport-stat"><div class="oport-stat-label">Públicas</div><div class="oport-stat-val"><?= number_format($plazasPub) ?></div></div><?php endif; ?>
        <?php if ($plazasRes !== null): ?><div class="oport-stat"><div class="oport-stat-label">Residentes</div><div class="oport-stat-val"><?= number_format($plazasRes) ?></div></div><?php endif; ?>
        <?php if ($plazasAbo !== null): ?><div class="oport-stat"><div class="oport-stat-label">Abonados</div><div class="oport-stat-val"><?= number_format($plazasAbo) ?></div></div><?php endif; ?>
        <?php if ($cargEv !== null): ?><div class="oport-stat"><div class="oport-stat-label">Cargad. EV</div><div class="oport-stat-val"><?= $cargEv ?></div></div><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($pkDesc): ?><div class="oport-kv"><span class="oport-k">P. Descubierto</span><span class="oport-v"><?= $pkDesc ?></span></div><?php endif; ?>
    <?php if ($pkCub): ?><div class="oport-kv"><span class="oport-k">P. Cubierto</span><span class="oport-v"><?= $pkCub ?></span></div><?php endif; ?>
    <?php if ($parking): ?><div class="oport-kv"><span class="oport-k">Parking</span><span class="oport-v"><?= $parking ?></span></div><?php endif; ?>
    <?php if ($pkUso): ?><div class="oport-kv"><span class="oport-k">Uso</span><span class="oport-v"><?= $pkUso ?></span></div><?php endif; ?>
    <?php if ($ops2023): ?><div class="oport-kv"><span class="oport-k">Operaciones 2023</span><span class="oport-v"><?= $ops2023 ?></span></div><?php endif; ?>
    <?php if ($lavadero): ?><div class="oport-kv"><span class="oport-k">Lavadero</span><span class="oport-v"><?= $lavadero ?></span></div><?php endif; ?>
    <?php if ($operario): ?><div class="oport-kv"><span class="oport-k">Operario</span><span class="oport-v"><?= $operario ?></span></div><?php endif; ?>
    <?php endif; ?>

    <?php if ($comentario): ?><div class="exp-comment" style="margin-top:8px"><?= $comentario ?></div><?php endif; ?>
</div>
<?php endforeach; ?>
</div>

<?php if ($total > $limit): ?>
<div class="load-more-wrap">
    <button class="btn-load-more" id="loadMoreBtn" data-offset="<?= $limit ?>">⬇️ Cargar más</button>
</div>
<?php endif; ?>
<?php endif; ?>

<style>
.oport-grid{display:grid;grid-template-columns:1fr;gap:16px;margin-top:16px}
@media(min-width:640px){.oport-grid{grid-template-columns:repeat(2,1fr)}}
@media(min-width:1024px){.oport-grid{grid-template-columns:repeat(3,1fr)}}
.oport-card{background:#fff;border-radius:14px;padding:18px;box-shadow:0 1px 6px rgba(0,0,0,.07);display:flex;flex-direction:column;gap:6px;transition:box-shadow .2s}
.oport-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.12)}
.oport-card-header{display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:4px}
.oport-name{font-size:1rem;font-weight:700;color:#111827;line-height:1.3}
.oport-chain{font-size:.78rem;color:#6b7280;margin-top:2px}
.oport-header-right{display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0}
.oport-fecha{font-size:.7rem;color:#9ca3af}
.oport-info-row{display:flex;align-items:flex-start;gap:6px;font-size:.8rem;color:#374151}
.oport-info-icon{flex-shrink:0;width:18px;text-align:center}
.oport-section{margin-top:8px;padding-top:8px;border-top:1px solid #f3f4f6}
.oport-stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(85px,1fr));gap:8px}
.oport-stat{background:#f9fafb;border-radius:8px;padding:8px;text-align:center}
.oport-stat-label{font-size:.62rem;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.03em}
.oport-stat-val{font-size:.95rem;font-weight:700;color:#111827;margin-top:2px}
.oport-kv{display:flex;gap:8px;font-size:.78rem;padding:2px 0}
.oport-k{font-weight:600;color:#6b7280;min-width:110px;flex-shrink:0}
.oport-v{color:#374151}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
