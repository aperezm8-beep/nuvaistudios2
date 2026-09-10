<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$db = getDB();
$basePath = '../';
$pageTitle = 'Consultar Documentación';
$currentPage = 'documentacion';

// Carpetas permanentes (IDEXPEDIENTE = 0): 253=1ª Doc, 254=2ª Doc, 255=Precontrato
$seccionesMap = [
    253 => ['titulo' => '1ª Documentación', 'icon' => '📄', 'color' => '#16a34a'],
    254 => ['titulo' => '2ª Documentación', 'icon' => '📋', 'color' => '#2563eb'],
    255 => ['titulo' => 'Precontrato',       'icon' => '📝', 'color' => '#9333ea'],
];

$carpetaIds = array_keys($seccionesMap);
$placeholders = implode(',', array_fill(0, count($carpetaIds), '?'));

$stmt = $db->prepare("
    SELECT d.*, c.NOMBRECARPETA
    FROM m_documentos d
    JOIN m_carpetas c ON d.IDCARPETA = c.IDCARPETA
    WHERE d.IDCARPETA IN ({$placeholders})
      AND d.ACTIVO = 1
      AND d.VISIBLE = 1
    ORDER BY d.IDCARPETA, d.FECREACION
");
$stmt->execute($carpetaIds);
$allDocs = $stmt->fetchAll();

// Agrupar por carpeta
$docsByCarpeta = [];
foreach ($allDocs as $doc) {
    $docsByCarpeta[$doc['IDCARPETA']][] = $doc;
}

function getDocIcon(string $ext): string {
    return match(strtolower($ext)) {
        'pdf'  => '📕',
        'mp4','mov','avi' => '🎬',
        'jpg','jpeg','png','gif','webp' => '🖼️',
        'docx','doc' => '📘',
        'xlsx','xls' => '📗',
        default => '📎',
    };
}

function formatBytes(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes/1024, 1) . ' KB';
    return round($bytes/1048576, 1) . ' MB';
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">📁 Consultar Documentación</h1>
    <p class="page-subtitle">Accede y descarga los documentos de cada fase</p>
</div>

<?php foreach ($seccionesMap as $carpetaId => $seccion): ?>
<div class="doc-section">
    <div class="doc-section-title" style="border-bottom-color:<?= $seccion['color'] ?>">
        <?= $seccion['icon'] ?> <?= htmlspecialchars($seccion['titulo']) ?>
        <span style="margin-left:auto;font-size:0.78rem;font-weight:400;color:var(--gray-400);">
            <?= count($docsByCarpeta[$carpetaId] ?? []) ?> archivo(s)
        </span>
    </div>

    <?php if (empty($docsByCarpeta[$carpetaId])): ?>
        <div class="card">
            <div class="empty-state" style="padding:28px;">
                <div class="empty-icon">📭</div>
                <p>No hay documentos disponibles en esta sección.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="doc-grid">
            <?php foreach ($docsByCarpeta[$carpetaId] as $doc):
                $ext    = strtolower($doc['Extension'] ?? pathinfo($doc['NOMBREDOCUMENTO'] ?? '', PATHINFO_EXTENSION));
                $icon   = getDocIcon($ext);
                $titulo = htmlspecialchars($doc['NOMBREVISUAL'] ?? $doc['NOMBREDOCUMENTO'] ?? 'Documento');
                $peso   = $doc['Peso'] ? formatBytes((int)$doc['Peso']) : '';
                $fecha  = $doc['FECREACION'] ? date('d/m/Y', strtotime($doc['FECREACION'])) : '';
                $docId  = (int)$doc['IDDOCUMENTO'];
                // URL de descarga/vista - apunta al endpoint de descarga
                $urlDescarga = '../api/download.php?id=' . $docId;
            ?>
                <div class="doc-card">
                    <div class="doc-icon"><?= $icon ?></div>
                    <div class="doc-info">
                        <div class="doc-title" title="<?= $titulo ?>"><?= $titulo ?></div>
                        <div class="doc-meta">
                            <?= strtoupper($ext) ?>
                            <?php if ($peso): ?> · <?= $peso ?><?php endif; ?>
                            <?php if ($fecha): ?> · <?= $fecha ?><?php endif; ?>
                        </div>
                    </div>
                    <div class="doc-actions">
                        <?php if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4'])): ?>
                        <button class="doc-btn doc-btn-view"
                            onclick="openDocViewer(<?= $docId ?>, '<?= addslashes($titulo) ?>', '<?= $ext ?>')">
                            👁️ Ver
                        </button>
                        <?php endif; ?>
                        <a href="<?= $urlDescarga ?>&action=download" class="doc-btn doc-btn-download" download>
                            ⬇️ Descargar
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<!-- MODAL VISOR DE DOCUMENTOS -->
<div class="modal-backdrop" id="docViewerModal">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header">
            <span class="modal-title" id="docViewerTitle">Documento</span>
            <button class="modal-close" data-modal-close="docViewerModal">✕</button>
        </div>
        <div class="modal-body" style="padding:0;" id="docViewerBody">
            <!-- Contenido dinámico -->
        </div>
    </div>
</div>

<script>
function openDocViewer(id, titulo, ext) {
    document.getElementById('docViewerTitle').textContent = titulo;
    const body = document.getElementById('docViewerBody');
    const url  = '../api/download.php?id=' + id + '&action=view';

    if (ext === 'pdf') {
        body.innerHTML = '<iframe src="' + url + '" style="width:100%;height:70vh;border:none;"></iframe>';
    } else if (['jpg','jpeg','png','gif','webp'].includes(ext)) {
        body.innerHTML = '<img src="' + url + '" style="max-width:100%;display:block;margin:auto;padding:16px;" alt="' + titulo + '">';
    } else if (ext === 'mp4') {
        body.innerHTML = '<video controls style="width:100%;"><source src="' + url + '" type="video/mp4">Tu navegador no soporta vídeo HTML5.</video>';
    }

    document.getElementById('docViewerModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
