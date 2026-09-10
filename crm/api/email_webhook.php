<?php
require_once __DIR__ . '/../includes/db.php';

function getEnvValue($key, $default = null) {
    $sources = [
        getenv($key),
        $_ENV[$key] ?? null,
        $_SERVER[$key] ?? null,
    ];

    foreach ($sources as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            $value = trim($candidate);
            $value = preg_replace('/^"|"$/', '', $value);
            $value = preg_replace('/^\'|\'$/', '', $value);
            return $value;
        }
    }

    return $default;
}

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $envVars = parse_ini_file($envFile);
    if (is_array($envVars)) {
        foreach ($envVars as $key => $value) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

function normalizeString($value) {
    if ($value === null) {
        return null;
    }

    if (is_array($value)) {
        $value = $value[0] ?? '';
    }

    return trim((string)$value);
}

function extractEmailValue($source, $keys, $default = null) {
    foreach ($keys as $key) {
        if (isset($source[$key])) {
            $value = $source[$key];
            if (is_array($value)) {
                if (isset($value['email'])) {
                    return normalizeString($value['email']);
                }
                if (isset($value['address'])) {
                    return normalizeString($value['address']);
                }
                if (!empty($value)) {
                    return normalizeString(reset($value));
                }
            }
            $normalized = normalizeString($value);
            if ($normalized !== '') {
                return $normalized;
            }
        }
    }

    return $default;
}

function extractEmailFromRaw($rawPayload) {
    if (empty($rawPayload)) {
        return null;
    }

    $trimmed = trim($rawPayload);
    if ($trimmed === '') {
        return null;
    }

    if (preg_match('/From:\s*([^\r\n]+)/i', $trimmed, $m)) {
        return trim($m[1]);
    }

    return null;
}

function getDomainFromEmail($email) {
    $email = trim((string)$email);
    if ($email === '') {
        return null;
    }

    $at = strrpos($email, '@');
    if ($at === false || $at === strlen($email) - 1) {
        return null;
    }

    return strtolower(substr($email, $at + 1));
}

function buildEmailRecord(array $payload, $rawInput = null): ?array {
    $sender = $payload['sender'] ?? $payload['from'] ?? $payload['From'] ?? $payload['sender_email'] ?? null;
    $recipient = $payload['recipient'] ?? $payload['to'] ?? $payload['To'] ?? $payload['recipient_email'] ?? null;
    $subject = $payload['subject'] ?? $payload['Subject'] ?? null;
    $bodyText = $payload['body-plain'] ?? $payload['stripped-text'] ?? $payload['text'] ?? $payload['message'] ?? $payload['body'] ?? null;
    $bodyHtml = $payload['body-html'] ?? $payload['html'] ?? $payload['stripped-html'] ?? null;

    if (is_array($sender) && isset($sender['email'])) {
        $sender = $sender['email'];
    }
    if (is_array($recipient) && isset($recipient['email'])) {
        $recipient = $recipient['email'];
    }

    $senderEmail = extractEmailValue($payload, ['sender_email', 'From', 'from', 'sender', 'email'], null);
    $recipientEmail = extractEmailValue($payload, ['recipient_email', 'To', 'to', 'recipient', 'email'], null);

    if (!$senderEmail) {
        $senderEmail = extractEmailValue((array)$sender, ['email', 'address'], null);
    }

    if (!$recipientEmail) {
        $recipientEmail = extractEmailValue((array)$recipient, ['email', 'address'], null);
    }

    if (!$senderEmail && !empty($rawInput)) {
        $senderEmail = extractEmailFromRaw($rawInput);
    }

    if (!$senderEmail) {
        return null;
    }

    $senderName = normalizeString($payload['sender_name'] ?? $payload['from_name'] ?? $payload['From_name'] ?? null);
    if (!$senderName && is_array($sender) && isset($sender['name'])) {
        $senderName = normalizeString($sender['name']);
    }

    $subjectText = normalizeString($subject ?? $payload['subject'] ?? $payload['Subject'] ?? '');
    $textBody = normalizeString($bodyText ?? $payload['stripped-text'] ?? $payload['body_plain'] ?? $payload['mail_body'] ?? '');
    $htmlBody = normalizeString($bodyHtml ?? $payload['body_html'] ?? $payload['html'] ?? '');

    if ($textBody === '' && $htmlBody !== '') {
        $textBody = preg_replace('/<[^>]+>/', ' ', $htmlBody);
        $textBody = html_entity_decode(strip_tags($textBody), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $textBody = preg_replace('/\s+/', ' ', $textBody);
        $textBody = trim($textBody);
    }

    return [
        'source' => 'mailgun',
        'sender_email' => strtolower(trim($senderEmail)),
        'sender_name' => $senderName ?: 'Desconocido',
        'recipient_email' => strtolower(trim($recipientEmail ?: $payload['recipient_email'] ?? $payload['To'] ?? '')),
        'subject' => $subjectText ?: '(sin asunto)',
        'message_text' => $textBody ?: '(sin contenido)',
        'message_html' => $htmlBody ?: null,
        'headers_json' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        'raw_payload' => $rawInput ?: json_encode($payload, JSON_UNESCAPED_UNICODE),
        'domain' => getDomainFromEmail($recipientEmail ?: $senderEmail) ?: 'gwgreenwash.com',
    ];
}

function saveInboundEmailToDb(array $record): int {
    $db = getDB();

    $sql = "
        INSERT INTO crm_email_webhooks (
            source, domain, sender_name, sender_email, recipient_email,
            subject, message_text, message_html, headers_json, raw_payload, received_at
        ) VALUES (
            :source, :domain, :sender_name, :sender_email, :recipient_email,
            :subject, :message_text, :message_html, :headers_json, :raw_payload, NOW()
        )
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':source' => $record['source'] ?? 'generic',
        ':domain' => $record['domain'] ?? 'gwgreenwash.com',
        ':sender_name' => $record['sender_name'] ?? 'Desconocido',
        ':sender_email' => strtolower(trim((string)($record['sender_email'] ?? ''))),
        ':recipient_email' => strtolower(trim((string)($record['recipient_email'] ?? ''))),
        ':subject' => $record['subject'] ?? '(sin asunto)',
        ':message_text' => $record['message_text'] ?? '(sin contenido)',
        ':message_html' => $record['message_html'] ?? null,
        ':headers_json' => $record['headers_json'] ?? null,
        ':raw_payload' => $record['raw_payload'] ?? null,
    ]);

    return (int)$db->lastInsertId();
}

function getAllowedEmailTargets(): array {
    $raw = getEnvValue('ALLOWED_EMAIL_TARGETS', 'tecnico@gwgreenwash.com');
    if ($raw === '') {
        return [];
    }

    $parts = preg_split('/[,;\r\n]+/', $raw);
    $allowed = [];
    foreach ($parts as $part) {
        $value = strtolower(trim((string)$part));
        if ($value !== '') {
            $allowed[] = $value;
        }
    }

    return array_values(array_unique($allowed));
}

function isAllowedWebhookDomain($domain) {
    $allowed = array_filter([
        getEnvValue('ALLOWED_EMAIL_DOMAIN', 'gwgreenwash.com'),
        getEnvValue('EMAIL_WEBHOOK_DOMAIN', 'gwgreenwash.com'),
        getEnvValue('MAILGUN_DOMAIN', 'gwgreenwash.com'),
        getEnvValue('SENDGRID_DOMAIN', 'gwgreenwash.com'),
    ]);

    foreach ($allowed as $item) {
        if (strtolower(trim((string)$item)) === strtolower(trim((string)$domain))) {
            return true;
        }
    }

    return false;
}

function hasValidMailgunSignature(array $payload): bool {
    $signingKey = getEnvValue('MAILGUN_SIGNING_KEY', '');
    if ($signingKey === '') {
        return false;
    }

    $signature = $payload['signature'] ?? null;
    if (!is_array($signature)) {
        return false;
    }

    $timestamp = normalizeString($signature['timestamp'] ?? '');
    $token = normalizeString($signature['token'] ?? '');
    $provided = normalizeString($signature['signature'] ?? '');
    if ($timestamp === '' || $token === '' || $provided === '') {
        return false;
    }

    if (abs(time() - (int)$timestamp) > 900) {
        return false;
    }

    $expected = hash_hmac('sha256', $timestamp . $token, $signingKey);
    return hash_equals($expected, $provided);
}

function isAllowedWebhookTarget(array $record): bool {
    $allowedTargets = getAllowedEmailTargets();
    if (empty($allowedTargets)) {
        return true;
    }

    $recipientEmail = strtolower(trim((string)($record['recipient_email'] ?? '')));
    return in_array($recipientEmail, $allowedTargets, true);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
$payload = [];

if ($rawInput !== '') {
    if (stripos($contentType, 'application/json') !== false) {
        $decoded = json_decode($rawInput, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    } elseif (stripos($contentType, 'application/x-www-form-urlencoded') !== false || stripos($contentType, 'multipart/form-data') !== false) {
        parse_str($rawInput, $payload);
    }
}

if (empty($payload) && !empty($_POST)) {
    $payload = $_POST;
}

if (empty($payload) && $rawInput !== '') {
    $payload = json_decode($rawInput, true) ?: [];
}

if (empty($payload)) {
    error_log('email_webhook.php: payload vacío');
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No payload received']);
    exit;
}

if (!hasValidMailgunSignature($payload)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid Mailgun signature']);
    exit;
}

$record = buildEmailRecord($payload, $rawInput);
if (!$record) {
    error_log('email_webhook.php: no se pudo identificar el email remitente');
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid email payload']);
    exit;
}

$domain = strtolower(trim((string)($record['domain'] ?? 'gwgreenwash.com')));
if (!isAllowedWebhookDomain($domain)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Domain not allowed']);
    exit;
}

if (!isAllowedWebhookTarget($record)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Email not allowed',
        'allowed_targets' => getAllowedEmailTargets(),
        'recipient' => $record['recipient_email'] ?? null,
        'sender' => $record['sender_email'] ?? null,
    ]);
    exit;
}

try {
    $id = saveInboundEmailToDb($record);
    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'message' => 'Email stored successfully',
        'email_id' => $id,
        'domain' => $domain,
    ]);
} catch (Throwable $e) {
    error_log('email_webhook.php DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB save failed']);
}
