<?php
/**
 * EMAIL TEMPLATES — GREEN WASH CRM
 *
 * Carga las plantillas HTML reales y sustituye las variables.
 * Las plantillas están en includes/email_html/
 */

require_once __DIR__ . '/email_config.php';
require_once __DIR__ . '/SmtpMailer.php';

/**
 * Construye el HTML del email para un expediente y tipo de envío.
 */
function buildEmailHtml(string $tipo, array $exp): string
{
    $idModalidad = (int)($exp['ID_MODALIDAD'] ?? 0);
    $cfg         = getEmailConfig($idModalidad, $tipo);
    $template    = $cfg['template'] ?? 'fase1';

    $file = __DIR__ . '/email_html/' . $template . '.html';
    if (!file_exists($file)) {
        throw new RuntimeException("Plantilla de email no encontrada: {$file}");
    }

    $html = file_get_contents($file);

    // Variables de sustitución
    $nombre = trim(($exp['NOMBRE'] ?? '') . ' ' . ($exp['APELLIDOS'] ?? ''));
    $vars = [
        '{nombreCliente}'  => htmlspecialchars($nombre ?: 'Cliente'),
        '{nombreModalidad}'=> htmlspecialchars($cfg['nombreMod'] ?? ($exp['NOMBREMODALIDAD'] ?? '')),
        '{linkDossier}'    => $cfg['linkDossier']  ?? '#',
        '{linkVideo}'      => $cfg['linkVideo']    ?? '#',
        '{linkVideo1}'     => $cfg['linkVideo1']   ?? '#',
        '{linkVideo2}'     => $cfg['linkVideo2']   ?? '#',
    ];

    return str_replace(array_keys($vars), array_values($vars), $html);
}

/**
 * Devuelve el asunto del email.
 */
function getEmailSubjectForTipo(string $tipo, array $exp): string
{
    $base = TIPOS_DOC[$tipo]['asunto'] ?? 'Documentación Green Wash';
    return $base;
}

/**
 * Devuelve el ID de fase que debe quedar tras enviar un tipo de doc.
 */
function getFaseParaTipo(string $tipo): ?int
{
    return TIPOS_DOC[$tipo]['fase'] ?? null;
}

/**
 * Envía el email usando SmtpMailer con la configuración definida.
 * Devuelve ['ok'=>bool, 'error'=>string|null]
 */
function enviarEmailDocumentacion(string $tipo, array $exp): array
{
    if (empty($exp['EMAIL'])) {
        return ['ok' => false, 'error' => 'El expediente no tiene email'];
    }

    try {
        $html    = buildEmailHtml($tipo, $exp);
        $asunto  = getEmailSubjectForTipo($tipo, $exp);
        $nombre  = trim(($exp['NOMBRE'] ?? '') . ' ' . ($exp['APELLIDOS'] ?? ''));
        $toEmail = trim($exp['EMAIL']);

        $mailer = new SmtpMailer();
        $mailer->host       = SMTP_HOST;
        $mailer->port       = SMTP_PORT;
        $mailer->encryption = SMTP_ENCRYPTION;
        $mailer->username   = SMTP_USER;
        $mailer->password   = SMTP_PASS;
        $mailer->from       = SMTP_FROM;
        $mailer->fromName   = SMTP_FROM_NAME;
        $mailer->replyTo    = SMTP_REPLY_TO;

        $mailer->send($toEmail, $nombre, $asunto, $html);
        return ['ok' => true];

    } catch (RuntimeException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => 'Error SMTP: ' . $e->getMessage()];
    }
}
