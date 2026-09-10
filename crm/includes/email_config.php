<?php
/**
 * ══════════════════════════════════════════════════════════════
 *  CONFIGURACIÓN DE EMAIL — GREEN WASH CRM
 * ══════════════════════════════════════════════════════════════
 *
 *  CONFIGURACIÓN SMTP
 *  ─────────────────────────────────────────────────────────────
 *  Rellena los datos de tu servidor de correo saliente.
 *
 *  Para Gmail:
 *    SMTP_HOST       = 'smtp.gmail.com'
 *    SMTP_PORT       = 587
 *    SMTP_ENCRYPTION = 'tls'
 *    SMTP_USER       = 'tu@gmail.com'
 *    SMTP_PASS       = 'contraseña de aplicación (no la normal)'
 *
 *  Para Outlook / Office 365:
 *    SMTP_HOST       = 'smtp.office365.com'
 *    SMTP_PORT       = 587
 *    SMTP_ENCRYPTION = 'tls'
 *
 *  Para servidor propio (cPanel, Plesk, etc.):
 *    SMTP_HOST       = 'mail.tudominio.com'
 *    SMTP_PORT       = 587  (o 465 para SSL)
 *    SMTP_ENCRYPTION = 'tls' (o 'ssl' si puerto 465)
 */

define('SMTP_HOST',       'gwgreenwash.com');
define('SMTP_PORT',       465);
define('SMTP_ENCRYPTION', 'ssl');          // 'tls', 'ssl', ''
define('SMTP_USER',       'expedientes@gwgreenwash.com');
define('SMTP_PASS',       'GWEcologico2026*');
define('SMTP_FROM',       'expedientes@gwgreenwash.com');
define('SMTP_FROM_NAME',  'GW');
define('SMTP_REPLY_TO',   'expedientes@gwgreenwash.com');

/**
 * ══════════════════════════════════════════════════════════════
 *  FASES Y SU MAPEO
 * ══════════════════════════════════════════════════════════════
 *  IDs de fases de la base de datos:
 *    88 → Fase previa
 *    24 → Fase 1: Envío 1ª documentación
 *    25 → Fase 2: Envío 2ª documentación
 *    81 → Fase 3: Precontrato
 *    82 → Fase 4: Contrato
 */
define('FASE_PREVIA',       88);
define('FASE_1DOC',         24);
define('FASE_2DOC',         25);
define('FASE_PRECONTRATO',  81);
define('FASE_CONTRATO',     82);

// Tipos de envío de documentación
const TIPOS_DOC = [
    '1doc'       => ['label' => '1ª Documentación', 'fase' => 24, 'asunto' => 'Información sobre la franquicia Green Wash'],
    '2doc'       => ['label' => '2ª Documentación', 'fase' => 25, 'asunto' => 'Completamos la información de Green Wash'],
    'precontrato'=> ['label' => 'Precontrato',       'fase' => 81, 'asunto' => 'Precontrato — Franquicia Green Wash'],
    'contrato'   => ['label' => 'Contrato',          'fase' => 82, 'asunto' => 'Contrato — Franquicia Green Wash'],
];

/**
 * ══════════════════════════════════════════════════════════════
 *  CONFIGURACIÓN POR MODALIDAD
 * ══════════════════════════════════════════════════════════════
 *
 *  Para cada modalidad (ID de m_modalidad_implantacion) y cada
 *  fase de documentación, defines:
 *
 *  '1doc' => [
 *    'template'    => 'fase1',         // archivo en email_html/ (sin .html)
 *    'nombreMod'   => 'PARKING SUBTERRÁNEO', // texto que aparece en el email
 *    'linkDossier' => 'https://...',   // URL del dossier PDF (fase 1)
 *    'linkVideo'   => 'https://...',   // URL del vídeo (fase 1 tiene 1 video)
 *    'linkVideo1'  => 'https://...',   // URL vídeo 1 (fase 2)
 *    'linkVideo2'  => 'https://...',   // URL vídeo 2 (solo template 2videos)
 *  ]
 *
 *  Plantillas disponibles:
 *    'fase1'         → expediente1 (1ª doc: dossier + 1 video)
 *    'fase2_1video'  → expediente2 (2ª doc: 1 video)
 *    'fase2_2videos' → expediente2-2videos (2ª doc: 2 videos)
 *
 *  IMPORTANTE: Sustituye los enlaces '#' por las URLs reales.
 */

const MODALIDAD_EMAIL_CONFIG = [

    // ── 25: PARKING SUBTERRÁNEO ────────────────────────────────
    25 => [
        '1doc' => [
            'template'    => 'fase1',
            'nombreMod'   => 'PARKING SUBTERRÁNEO',
            'linkDossier' => 'https://greenwashcrm.es/storage/docs/Parking Subterraneo 2026.pdf',   // ← Sustituir por URL real del dossier
            'linkVideo'   => '#',   // ← Sustituir por URL real del vídeo
        ],
        '2doc' => [
            'template'    => 'fase2_2videos',
            'nombreMod'   => 'PARKING SUBTERRÁNEO',
            'linkVideo1'  => '#',   // ← Sustituir por URL real del vídeo 1
            'linkVideo2'  => '#',   // ← Sustituir por URL real del vídeo 2
        ],
        'precontrato' => [
            'template'    => 'fase1',   // Adaptar si hay plantilla específica
            'nombreMod'   => 'PARKING SUBTERRÁNEO',
            'linkDossier' => '#',
            'linkVideo'   => '#',
        ],
        'contrato' => [
            'template'    => 'fase1',
            'nombreMod'   => 'PARKING SUBTERRÁNEO',
            'linkDossier' => '#',
            'linkVideo'   => '#',
        ],
    ],

    // ── 26: INDUSTRIAL / LOCAL ────────────────────────────────
    26 => [
        '1doc' => [
            'template'    => 'fase1',
            'nombreMod'   => 'INDUSTRIAL / LOCAL',
            'linkDossier' => 'https://greenwashcrm.es/storage/docs/Local Industrial 2026.pdf',
            'linkVideo'   => '#',
        ],
        '2doc' => [
            'template'    => 'fase2_2videos',
            'nombreMod'   => 'INDUSTRIAL / LOCAL',
            'linkVideo1'  => '#',
            'linkVideo2'  => '#',
        ],
        'precontrato' => [
            'template'    => 'fase1',
            'nombreMod'   => 'INDUSTRIAL / LOCAL',
            'linkDossier' => '#',
            'linkVideo'   => '#',
        ],
        'contrato' => [
            'template'    => 'fase1',
            'nombreMod'   => 'INDUSTRIAL / LOCAL',
            'linkDossier' => '#',
            'linkVideo'   => '#',
        ],
    ],

    // ── 27: PARKING SUPERFICIE ───────────────────────────────
    27 => [
        '1doc' => [
            'template'    => 'fase1',
            'nombreMod'   => 'PARKING SUPERFICIE',
            'linkDossier' => 'https://greenwashcrm.es/storage/docs/Parking Superficie 2026.pdf',
            'linkVideo'   => '#',
        ],
        '2doc' => [
            'template'    => 'fase2_2videos',
            'nombreMod'   => 'PARKING SUPERFICIE',
            'linkVideo1'  => '#',
            'linkVideo2'  => '#',
        ],
        'precontrato' => [
            'template'    => 'fase1',
            'nombreMod'   => 'PARKING SUPERFICIE',
            'linkDossier' => '#',
            'linkVideo'   => '#',
        ],
        'contrato' => [
            'template'    => 'fase1',
            'nombreMod'   => 'PARKING SUPERFICIE',
            'linkDossier' => '#',
            'linkVideo'   => '#',
        ],
    ],

    // ── 28: TALLER ────────────────────────────────────────────
    28 => [
        '1doc' => [
            'template'    => 'fase1',
            'nombreMod'   => 'TALLER',
            'linkDossier' => '#',
            'linkVideo'   => '#',
        ],
        '2doc' => [
            'template'    => 'fase2_1video',
            'nombreMod'   => 'TALLER',
            'linkVideo1'  => '#',
        ],
        'precontrato' => [
            'template'    => 'fase1',
            'nombreMod'   => 'TALLER',
            'linkDossier' => '#',
            'linkVideo'   => '#',
        ],
        'contrato' => [
            'template'    => 'fase1',
            'nombreMod'   => 'TALLER',
            'linkDossier' => '#',
            'linkVideo'   => '#',
        ],
    ],

    // ── 31: UNIDAD MÓVIL ─────────────────────────────────────
    31 => [
        '1doc' => [
            'template'    => 'fase1',
            'nombreMod'   => 'UNIDAD MÓVIL',
            'linkDossier' => '#',
            'linkVideo'   => '#',
        ],
        '2doc' => [
            'template'    => 'fase2_1video',
            'nombreMod'   => 'UNIDAD MÓVIL',
            'linkVideo1'  => '#',
        ],
        'precontrato' => [
            'template'    => 'fase1',
            'nombreMod'   => 'UNIDAD MÓVIL',
            'linkDossier' => '#',
            'linkVideo'   => '#',
        ],
        'contrato' => [
            'template'    => 'fase1',
            'nombreMod'   => 'UNIDAD MÓVIL',
            'linkDossier' => '#',
            'linkVideo'   => '#',
        ],
    ],
];

/**
 * Devuelve la config para una modalidad y tipo de doc.
 * Si no existe config específica, usa una genérica.
 */
function getEmailConfig(int $idModalidad, string $tipo): array
{
    $cfg = MODALIDAD_EMAIL_CONFIG[$idModalidad][$tipo] ?? null;
    if ($cfg) return $cfg;

    // Config genérica de fallback
    return [
        'template'    => 'fase1',
        'nombreMod'   => 'FRANQUICIA GREEN WASH',
        'linkDossier' => '#',
        'linkVideo'   => '#',
    ];
}
