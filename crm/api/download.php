<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$db = getDB();
$id     = (int)($_GET['id'] ?? 0);
$action = ($_GET['action'] ?? 'view') === 'download' ? 'download' : 'view';

if (!$id) {
    http_response_code(400);
    exit('ID de documento no válido.');
}

// Obtener documento — solo documentos activos y visibles
$stmt = $db->prepare("
    SELECT d.*, c.permanente
    FROM m_documentos d
    JOIN m_carpetas c ON d.IDCARPETA = c.IDCARPETA
    WHERE d.IDDOCUMENTO = :id AND d.ACTIVO = 1 AND d.VISIBLE = 1
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    exit('Documento no encontrado.');
}

// RUTA DEL ARCHIVO
// Las rutas en la BD son Windows absolutas. Necesitamos mapearlas a la ruta Linux del servidor.
// Convención: se asume que los archivos están en un directorio accesible.
// Ajustar BASE_DOCS_PATH según la instalación real.
define('BASE_DOCS_PATH', __DIR__ . '/../storage/docs/');

// Extraer nombre del archivo de la ruta BD (que es Windows)
$rutaBd   = $doc['RUTA'] ?? '';
$filename = basename(str_replace('\\', '/', $rutaBd));

// Intentar construir ruta local
$localPath = BASE_DOCS_PATH . $filename;

// Si el archivo no existe localmente, intentar con NOMBREDOCUMENTO
if (!file_exists($localPath)) {
    $localPath = BASE_DOCS_PATH . ($doc['NOMBREDOCUMENTO'] ?? '');
}

if (!file_exists($localPath)) {
    // En desarrollo: devolver placeholder de "archivo no disponible"
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Archivo no disponible en este servidor. Ruta original: ' . htmlspecialchars($rutaBd));
}

// ── SEGURIDAD: verificar que el path real está dentro del directorio permitido ──
$realPath = realpath($localPath);
$realBase = realpath(BASE_DOCS_PATH);
if ($realPath === false || strpos($realPath, $realBase) !== 0) {
    http_response_code(403);
    exit('Acceso no autorizado.');
}

// ── EXTENSIÓN Y MIME TYPE ────────────────
$ext = strtolower($doc['Extension'] ?? pathinfo($filename, PATHINFO_EXTENSION));
$mimeMap = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'mp4'  => 'video/mp4',
    'mov'  => 'video/quicktime',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'doc'  => 'application/msword',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

// ── ENVIAR ARCHIVO ───────────────────────
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($realPath));
header('Cache-Control: private, max-age=3600');

if ($action === 'download') {
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $doc['NOMBREDOCUMENTO'] ?? $filename);
    header('Content-Disposition: attachment; filename="' . $safeName . '"');
} else {
    header('Content-Disposition: inline; filename="' . basename($realPath) . '"');
}

readfile($realPath);
exit;
