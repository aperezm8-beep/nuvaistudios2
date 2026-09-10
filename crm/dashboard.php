<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db = getDB();
$basePath = '';
$pageTitle = 'Inicio';
$currentPage = 'inicio';

// — Estadísticas Generales —
$stats = $db->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN ZONA IN ('Nacional','España','Spain') THEN 1 ELSE 0 END) AS nacionales,
        SUM(CASE WHEN ZONA NOT IN ('Nacional','España','Spain') AND ZONA IS NOT NULL THEN 1 ELSE 0 END) AS internacionales,
        SUM(CASE WHEN DATE(FECREACION) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS semana
    FROM trans_expedientes WHERE Activo = b'1'
")->fetch();

// ── ÚLTIMOS EXPEDIENTES (primeros 30) ─────
$limit  = 30;
$offset = 0;
$rows   = $db->query("
    SELECT e.*, m.NOMBREMODALIDAD, es.NOMBREESTADO, f.NOMBREFASE
    FROM trans_expedientes e
    LEFT JOIN m_modalidad_implantacion m ON e.ID_MODALIDAD = m.IDMODALIDAD
    LEFT JOIN m_estados es ON e.ID_ESTADO = es.IDESTADO
    LEFT JOIN m_fases f ON e.ID_FASE = f.IDFASE
    WHERE e.Activo = b'1'
    ORDER BY e.FECREACION DESC
    LIMIT 30
")->fetchAll();

$totalRows = (int)$db->query("SELECT COUNT(*) FROM trans_expedientes WHERE Activo = b'1'")->fetchColumn();

include __DIR__ . '/includes/header.php';

function renderExpedienteCard(array $e, bool $modal = true): string {
    $zona = strtolower($e['ZONA'] ?? '');
    $esNacional = $zona === '' || in_array($zona, ['nacional', 'españa', 'spain']);
    $claseZona = $esNacional ? 'nacional' : 'internacional';
    $iconZona  = $esNacional ? '🇪🇸' : '🌍';

    $fecha = $e['FECREACION'] ? date('d/m/Y', strtotime($e['FECREACION'])) : '—';
    $nombre = htmlspecialchars(trim(($e['NOMBRE'] ?? '') . ' ' . ($e['APELLIDOS'] ?? '')));
    $tel    = htmlspecialchars($e['TELEFONO'] ?? '');
    $email  = htmlspecialchars($e['EMAIL'] ?? '');
    $pob    = htmlspecialchars(trim(($e['POBLACION'] ?? '') . ($e['PROVINCIA'] ? ', ' . $e['PROVINCIA'] : '')));
    $pais   = htmlspecialchars($e['PAIS'] ?? '');
    $modal_ = htmlspecialchars($e['NOMBREMODALIDAD'] ?? 'Sin modalidad');
    $est    = htmlspecialchars($e['NOMBREESTADO'] ?? '');
    $fase   = htmlspecialchars($e['NOMBREFASE'] ?? '');
    $com    = htmlspecialchars($e['COMENTARIO'] ?? '');
    $id     = (int)$e['IDEXPEDIENTE'];

    $out  = "<div class=\"exp-card {$claseZona}\" data-id=\"{$id}\">";
    $out .= "<div class=\"exp-card-header\">";
    $out .= "<span class=\"exp-name\">{$iconZona} {$nombre}</span>";
    $out .= "<span class=\"exp-date\">{$fecha}</span>";
    $out .= "</div>";
    $out .= "<div class=\"exp-card-meta\">";
    $out .= "<span class=\"badge badge-" . ($esNacional ? 'green' : 'blue') . "\">" . ($esNacional ? 'Nacional' : 'Internacional') . "</span>";
    if ($modal_)  $out .= "<span class=\"badge badge-gray\">{$modal_}</span>";
    if ($est)     $out .= "<span class=\"badge badge-orange\">{$est}</span>";
    if ($fase)    $out .= "<span class=\"badge badge-purple\">{$fase}</span>";
    $out .= "</div>";
    if ($pob || $pais) {
        $loc = $pob ?: $pais;
        $out .= "<div class=\"exp-contact\"><span>📍 {$loc}</span>";
        if ($tel) $out .= "<span>📞 {$tel}</span>";
        $out .= "</div>";
    }
    if ($com) $out .= "<div class=\"exp-comment\">{$com}</div>";
    $out .= "</div>";

    if ($modal) {
        $recibido  = htmlspecialchars($e['RECIBIDO'] ?? '');
        $obs       = htmlspecialchars($e['OBSERVACIONES'] ?? '');
        $out .= "<div class=\"modal-backdrop\" id=\"modal-exp-{$id}\">";
        $out .= "<div class=\"modal\">";
        $out .= "<div class=\"modal-header\"><span class=\"modal-title\">Expediente #{$id}</span><button class=\"modal-close\" data-modal-close=\"modal-exp-{$id}\">✕</button></div>";
        $out .= "<div class=\"modal-body\">";
        $rows_ = [
            ['Nombre', $nombre], ['Teléfono', $tel], ['Email', $email],
            ['Zona', htmlspecialchars($e['ZONA'] ?? '')], ['Población', $pob], ['País', $pais],
            ['Modalidad', $modal_], ['Estado', $est], ['Fase', $fase],
            ['Recibido', $recibido], ['Comentario', $com], ['Observaciones', $obs],
            ['Fecha entrada', $fecha],
        ];
        foreach ($rows_ as [$label, $val]) {
            if (!$val) continue;
            $out .= "<div class=\"modal-row\"><span class=\"modal-label\">{$label}</span><span class=\"modal-value\">{$val}</span></div>";
        }
        $out .= "</div></div></div>";
    }

    return $out;
}
?>

<div class="page-header">
    <h1 class="page-title">🏠 Inicio</h1>
    <p class="page-subtitle">Nuevos expedientes recibidos</p>
</div>

<!-- STATS -->
<div class="stats-grid">
    <div class="stat-card green">
        <div class="stat-icon">📋</div>
        <div class="stat-value"><?= number_format($stats['total']) ?></div>
        <div class="stat-label">Total expedientes</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">🇪🇸</div>
        <div class="stat-value"><?= number_format($stats['nacionales']) ?></div>
        <div class="stat-label">Nacionales</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon">🌍</div>
        <div class="stat-value"><?= number_format($stats['internacionales']) ?></div>
        <div class="stat-label">Internacionales</div>
    </div>
    <div class="stat-card purple">
        <div class="stat-icon">🆕</div>
        <div class="stat-value"><?= number_format($stats['semana']) ?></div>
        <div class="stat-label">Últimos 7 días</div>
    </div>
</div>

<!-- EXPEDIENTES -->
<div class="card">
    <div class="card-header">
        <span>📋</span>
        <h3>Expedientes recientes</h3>
        <span style="margin-left:auto;font-size:0.78rem;color:var(--gray-400);">
            Mostrando <?= count($rows) ?> de <?= $totalRows ?>
        </span>
    </div>
    <div class="card-body" style="padding:12px;">
        <?php if (empty($rows)): ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <p>No hay expedientes</p>
            </div>
        <?php else: ?>
            <div class="exp-list" id="expList">
                <?php foreach ($rows as $e): ?>
                    <?= renderExpedienteCard($e) ?>
                <?php endforeach; ?>
            </div>

            <?php if ($totalRows > $limit): ?>
            <div class="load-more-wrap">
                <button
                    class="btn-load-more"
                    id="loadMoreBtn"
                    data-offset="<?= $limit ?>"
                    data-endpoint="api/expedientes.php"
                    data-params="origen=inicio"
                >
                    ⬇️ Cargar más expedientes
                </button>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
