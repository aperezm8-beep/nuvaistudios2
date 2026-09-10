<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$db = getDB();
$basePath = '../';
$pageTitle = 'Registro de correos';
$currentPage = 'emails';
$buscar = trim($_GET['buscar'] ?? '');
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina = 30;
$offset = ($pagina - 1) * $porPagina;

$where = '1=1';
$params = [];
if ($buscar !== '') {
    $where .= ' AND (sender_name LIKE :buscar OR sender_email LIKE :buscar OR recipient_email LIKE :buscar OR subject LIKE :buscar OR message_text LIKE :buscar)';
    $params[':buscar'] = '%' . $buscar . '%';
}

$total = 0;
$totalPaginas = 1;
$totalPaginas = max(1, (int)ceil($total / $porPagina));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $porPagina;

$correos = [];
$dbError = null;
try {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM crm_email_webhooks WHERE {$where}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $totalPaginas = max(1, (int)ceil($total / $porPagina));
    $pagina = min($pagina, $totalPaginas);
    $offset = ($pagina - 1) * $porPagina;

    $stmt = $db->prepare("SELECT * FROM crm_email_webhooks WHERE {$where} ORDER BY received_at DESC, id DESC LIMIT :limite OFFSET :offset");
    foreach ($params as $key => $value) $stmt->bindValue($key, $value);
    $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $correos = $stmt->fetchAll();
} catch (Throwable $e) {
    $dbError = 'No se puede consultar crm_email_webhooks. Verifica que la tabla exista en la base de datos del hosting.';
}

function emailDirection(array $correo): string {
    $headers = json_decode($correo['headers_json'] ?? '', true);
    return (($headers['direction'] ?? '') === 'outbound') ? 'Enviado' : 'Recibido';
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">✉️ Registro de correos</h1>
    <p class="page-subtitle"><?= $total ?> correo(s) registrados</p>
</div>

<?php if ($dbError): ?>
    <div class="alert-info">⚠️ <?= htmlspecialchars($dbError) ?></div>
<?php endif; ?>

<form method="GET" class="search-bar" action="emails.php">
    <input type="text" name="buscar" class="search-input" placeholder="Buscar remitente, destinatario, asunto o contenido..." value="<?= htmlspecialchars($buscar) ?>">
    <button type="submit" class="search-btn">🔍</button>
</form>

<?php if (empty($correos)): ?>
    <div class="card"><div class="empty-state"><div class="empty-icon">📭</div><p>No hay correos que mostrar.</p></div></div>
<?php else: ?>
    <div class="email-log-list">
    <?php foreach ($correos as $correo):
        $direccion = emailDirection($correo);
        $esEnviado = $direccion === 'Enviado';
        $fecha = $correo['received_at'] ? date('d/m/Y H:i', strtotime($correo['received_at'])) : '—';
        $remitente = trim(($correo['sender_name'] ?? '') . ' <' . ($correo['sender_email'] ?? '') . '>');
        $destinatario = $correo['recipient_email'] ?? '';
        $asunto = $correo['subject'] ?: '(sin asunto)';
        $contenido = trim($correo['message_text'] ?? '');
        $contenidoCorto = mb_strimwidth($contenido, 0, 220, '...', 'UTF-8');
    ?>
        <article class="email-log-card">
            <div class="email-log-head">
                <span class="badge <?= $esEnviado ? 'badge-blue' : 'badge-green' ?>"><?= $esEnviado ? '↗ Enviado' : '↙ Recibido' ?></span>
                <time><?= htmlspecialchars($fecha) ?></time>
            </div>
            <h3><?= htmlspecialchars($asunto) ?></h3>
            <div class="email-log-address"><strong>De:</strong> <?= htmlspecialchars($remitente) ?></div>
            <div class="email-log-address"><strong>Para:</strong> <?= htmlspecialchars($destinatario ?: '—') ?></div>
            <p><?= nl2br(htmlspecialchars($contenidoCorto ?: '(sin contenido)')) ?></p>
            <details>
                <summary>Ver mensaje completo</summary>
                <div class="email-full-content"><?= nl2br(htmlspecialchars($contenido ?: '(sin contenido)')) ?></div>
            </details>
        </article>
    <?php endforeach; ?>
    </div>

    <?php if ($totalPaginas > 1): ?>
    <div class="email-pagination">
        <?php if ($pagina > 1): ?><a class="btn-sm btn-secondary" href="?buscar=<?= urlencode($buscar) ?>&pagina=<?= $pagina - 1 ?>">← Anteriores</a><?php endif; ?>
        <span>Página <?= $pagina ?> de <?= $totalPaginas ?></span>
        <?php if ($pagina < $totalPaginas): ?><a class="btn-sm btn-primary" href="?buscar=<?= urlencode($buscar) ?>&pagina=<?= $pagina + 1 ?>">Siguientes →</a><?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<style>
.email-log-list{display:grid;gap:12px;margin-top:16px}
.email-log-card{background:#fff;border:1px solid #e5e7eb;border-left:4px solid #16a34a;border-radius:10px;padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.05)}
.email-log-card h3{margin:10px 0 8px;color:#111827;font-size:.98rem}
.email-log-head{display:flex;justify-content:space-between;align-items:center;gap:10px}
.email-log-head time{color:#6b7280;font-size:.75rem}
.email-log-address{color:#374151;font-size:.8rem;margin:3px 0;overflow-wrap:anywhere}
.email-log-card p{color:#4b5563;font-size:.82rem;line-height:1.5;margin:12px 0 8px;white-space:normal}
.email-log-card details{border-top:1px solid #f3f4f6;padding-top:9px;color:#2563eb;font-size:.8rem}
.email-log-card summary{cursor:pointer;font-weight:600}
.email-full-content{color:#374151;line-height:1.5;white-space:normal;margin-top:10px;max-height:360px;overflow:auto}
.email-pagination{display:flex;justify-content:center;align-items:center;gap:14px;margin:20px 0;color:#6b7280;font-size:.82rem}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
