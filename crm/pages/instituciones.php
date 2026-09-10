<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$db = getDB();
$basePath = '../';

$tipo   = $_GET['tipo'] ?? 'ayuntamientos';
$buscar = trim($_GET['buscar'] ?? '');
$filtroProvncia = trim($_GET['provincia'] ?? '');
$filtrocaa = trim($_GET['ccaa'] ?? '');
$limit  = 50;
$offset = 0;

$currentPage = ($tipo === 'ayuntamientos') ? 'inst-ayuntamientos' : 'inst-ccaa';
$pageTitle = 'Instituciones — ' . ($tipo === 'ayuntamientos' ? 'Ayuntamientos' : 'CC.AA.');

// ── AYUNTAMIENTOS ────────────────────────
if ($tipo === 'ayuntamientos') {
    $where  = "Activo = 1";
    $params = [];

    if ($buscar !== '') {
        $b = '%' . $buscar . '%';
        $where .= " AND (N_AYUNTAMIENTO LIKE :b1 OR POBLACION LIKE :b2 OR PROVINCIA LIKE :b3 OR CCAA LIKE :b4 OR ALCALDE LIKE :b5)";
        $params[':b1'] = $b; $params[':b2'] = $b; $params[':b3'] = $b;
        $params[':b4'] = $b; $params[':b5'] = $b;
    }
    if ($filtroProvncia) {
        $where .= " AND PROVINCIA = :prov";
        $params[':prov'] = $filtroProvncia;
    }
    if ($filtrocaa) {
        $where .= " AND CCAA = :ccaa";
        $params[':ccaa'] = $filtrocaa;
    }

    $stmtCount = $db->prepare("SELECT COUNT(*) FROM m_ayuntamientos WHERE {$where}");
    foreach ($params as $k => $v) $stmtCount->bindValue($k, $v);
    $stmtCount->execute();
    $total = (int)$stmtCount->fetchColumn();

    $stmt = $db->prepare("SELECT * FROM m_ayuntamientos WHERE {$where} ORDER BY N_AYUNTAMIENTO LIMIT {$limit} OFFSET {$offset}");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    // Lista de CCAA y provincias únicas para filtros
    $ccaas      = $db->query("SELECT DISTINCT CCAA FROM m_ayuntamientos WHERE Activo = 1 ORDER BY CCAA")->fetchAll(PDO::FETCH_COLUMN);
    $provincias = $db->query("SELECT DISTINCT PROVINCIA FROM m_ayuntamientos WHERE Activo = 1 ORDER BY PROVINCIA")->fetchAll(PDO::FETCH_COLUMN);

} else {
    // ── CC.AA. ───────────────────────────────
    $where  = "activo = 1";
    $params = [];

    if ($buscar !== '') {
        $b = '%' . $buscar . '%';
        $where .= " AND (N_CCAA LIKE :b1 OR REFERENCIA LIKE :b2 OR COMENTARIOS LIKE :b3)";
        $params[':b1'] = $b; $params[':b2'] = $b; $params[':b3'] = $b;
    }

    $stmtCount = $db->prepare("SELECT COUNT(*) FROM m_ccaa WHERE {$where}");
    foreach ($params as $k => $v) $stmtCount->bindValue($k, $v);
    $stmtCount->execute();
    $total = (int)$stmtCount->fetchColumn();

    $stmt = $db->prepare("SELECT * FROM m_ccaa WHERE {$where} ORDER BY N_CCAA LIMIT {$limit} OFFSET {$offset}");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $rows = $stmt->fetchAll();
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">
        <?= $tipo === 'ayuntamientos' ? '🏛️' : '🗺️' ?>
        Instituciones
    </h1>
    <p class="page-subtitle"><?= $tipo === 'ayuntamientos' ? 'Ayuntamientos' : 'Comunidades Autónomas' ?> — <?= $total ?> registros</p>
</div>

<div class="tabs">
    <button class="tab-btn <?= $tipo === 'ayuntamientos' ? 'active' : '' ?>"
        onclick="location.href='instituciones.php?tipo=ayuntamientos'">🏛️ Ayuntamientos</button>
    <button class="tab-btn <?= $tipo === 'ccaa' ? 'active' : '' ?>"
        onclick="location.href='instituciones.php?tipo=ccaa'">🗺️ CC.AA.</button>
</div>

<form method="GET" action="instituciones.php" id="searchForm">
    <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
    <div class="search-bar">
        <input type="text" name="buscar" id="searchInput"
            class="search-input"
            placeholder="Buscar..."
            value="<?= htmlspecialchars($buscar) ?>">
        <?php if ($tipo === 'ayuntamientos'): ?>
        <button type="submit" class="search-btn">🔍</button>
        <button type="button" class="filter-btn" data-toggle-filter>⚙️ Filtros</button>
        <?php endif; ?>
        <?php if ($tipo === 'ccaa'): ?>
        <button type="submit" class="filter-btn">🔍 Buscar</button>
        <?php endif; ?>
    </div>

    <?php if ($tipo === 'ayuntamientos'): ?>
    <div class="filter-panel <?= ($filtroProvncia || $filtrocaa) ? 'open' : '' ?>" id="filterPanel">
        <div class="filter-row">
            <div class="filter-group">
                <label>Comunidad Autónoma</label>
                <select name="ccaa">
                    <option value="">Todas</option>
                    <?php foreach ($ccaas as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= $filtrocaa === $c ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Provincia</label>
                <select name="provincia">
                    <option value="">Todas</option>
                    <?php foreach ($provincias as $p): ?>
                        <option value="<?= htmlspecialchars($p) ?>" <?= $filtroProvncia === $p ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="filter-actions">
            <button type="button" class="btn-sm btn-secondary" id="clearFilters">Limpiar</button>
            <button type="submit" class="btn-sm btn-primary">Aplicar</button>
        </div>
    </div>
    <?php endif; ?>
</form>

<?php if (empty($rows)): ?>
    <div class="card"><div class="empty-state"><div class="empty-icon">🔍</div><p>No se encontraron resultados.</p></div></div>
<?php elseif ($tipo === 'ayuntamientos'): ?>

<!-- AYUNTAMIENTOS: tarjetas en mobile, tabla en desktop -->
<div class="exp-list" id="expList" style="display:grid;grid-template-columns:1fr;gap:10px;">
    <?php foreach ($rows as $a):
        $id     = (int)$a['IDAYUNTAMIENTO'];
        $nombre = htmlspecialchars($a['N_AYUNTAMIENTO'] ?? '');
        $ccaa   = htmlspecialchars($a['CCAA'] ?? '');
        $prov   = htmlspecialchars($a['PROVINCIA'] ?? '');
        $hab    = htmlspecialchars(trim($a['HABITANTES'] ?? ''));
        $tlf    = htmlspecialchars($a['TLF_FIJO'] ?? '');
        $email  = htmlspecialchars($a['EMAIL'] ?? '');
        $alcalde= htmlspecialchars($a['ALCALDE'] ?? '');
    ?>
        <div class="exp-card" data-id="ay-<?= $id ?>" style="border-left-color:#6366f1">
            <div class="exp-card-header">
                <span class="exp-name">🏛️ <?= $nombre ?></span>
                <?php if ($hab): ?><span class="exp-date">👥 <?= $hab ?> hab.</span><?php endif; ?>
            </div>
            <div class="exp-card-meta">
                <?php if ($prov): ?><span class="badge badge-gray"><?= $prov ?></span><?php endif; ?>
                <?php if ($ccaa): ?><span class="badge badge-blue"><?= $ccaa ?></span><?php endif; ?>
            </div>
            <div class="exp-contact">
                <?php if ($tlf): ?><span>📞 <?= $tlf ?></span><?php endif; ?>
                <?php if ($email): ?><span>✉️ <?= $email ?></span><?php endif; ?>
                <?php if ($alcalde): ?><span>👤 <?= $alcalde ?></span><?php endif; ?>
            </div>
        </div>

        <div class="modal-backdrop" id="modal-exp-ay-<?= $id ?>">
            <div class="modal">
                <div class="modal-header">
                    <span class="modal-title">🏛️ <?= $nombre ?></span>
                    <button class="modal-close" data-modal-close="modal-exp-ay-<?= $id ?>">✕</button>
                </div>
                <div class="modal-body">
                    <?php foreach ([
                        ['Ayuntamiento', $nombre], ['CCAA', $ccaa], ['Provincia', $prov],
                        ['Población', htmlspecialchars($a['POBLACION'] ?? '')],
                        ['Habitantes', $hab], ['Alcalde', $alcalde],
                        ['Cargo', htmlspecialchars($a['CARGO'] ?? '')],
                        ['Teléfono', $tlf], ['Móvil', htmlspecialchars($a['TLF_MOVIL'] ?? '')],
                        ['Email', $email], ['WhatsApp', htmlspecialchars($a['WASAP'] ?? '')],
                        ['Domicilio', htmlspecialchars($a['DOMICILIO'] ?? '')],
                        ['C.P.', htmlspecialchars($a['COD_POSTAL'] ?? '')],
                        ['Comentarios', htmlspecialchars($a['COMENTARIOS'] ?? '')],
                    ] as [$lbl, $val]): if (!$val) continue; ?>
                        <div class="modal-row">
                            <span class="modal-label"><?= $lbl ?></span>
                            <span class="modal-value"><?= $val ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php else: // CC.AA. ?>

<div class="exp-list">
    <?php foreach ($rows as $c):
        $id    = (int)$c['IDCCAA'];
        $nombre= htmlspecialchars($c['N_CCAA'] ?? '');
        $ref   = htmlspecialchars($c['REFERENCIA'] ?? '');
        $com   = htmlspecialchars($c['COMENTARIOS'] ?? '');
        $wasap = $c['WASAP'] ? htmlspecialchars($c['WASAP']) : '';
        $email = htmlspecialchars($c['EMAIL'] ?? '');
    ?>
        <div class="exp-card" data-id="ca-<?= $id ?>" style="border-left-color:#8b5cf6">
            <div class="exp-card-header">
                <span class="exp-name">🗺️ <?= $nombre ?></span>
            </div>
            <div class="exp-card-meta">
                <?php if ($ref): ?><span class="badge badge-gray"><?= $ref ?></span><?php endif; ?>
            </div>
            <?php if ($wasap || $email): ?>
            <div class="exp-contact">
                <?php if ($wasap): ?><span>📱 <?= $wasap ?></span><?php endif; ?>
                <?php if ($email): ?><span>✉️ <?= $email ?></span><?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($com): ?><div class="exp-comment"><?= $com ?></div><?php endif; ?>
        </div>

        <div class="modal-backdrop" id="modal-exp-ca-<?= $id ?>">
            <div class="modal">
                <div class="modal-header">
                    <span class="modal-title">🗺️ <?= $nombre ?></span>
                    <button class="modal-close" data-modal-close="modal-exp-ca-<?= $id ?>">✕</button>
                </div>
                <div class="modal-body">
                    <?php foreach ([
                        ['Comunidad Autónoma', $nombre], ['Referencia', $ref],
                        ['WhatsApp', $wasap], ['Email', $email], ['Comentarios', $com],
                    ] as [$lbl, $val]): if (!$val) continue; ?>
                        <div class="modal-row">
                            <span class="modal-label"><?= $lbl ?></span>
                            <span class="modal-value"><?= $val ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
// Reasignar data-id para ayuntamientos y CCAA
document.querySelectorAll('.exp-card[data-id]').forEach(card => {
    card.addEventListener('click', () => {
        const id = card.dataset.id;
        const modalId = 'modal-exp-' + id;
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
