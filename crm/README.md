# 🌿 Green Wash CRM

Aplicación web CRM para la gestión de expedientes, oportunidades e instituciones de Green Wash.

## Estructura de archivos

```
crm-greenwash/
├── index.php               ← Login (punto de entrada)
├── dashboard.php           ← Página de inicio con expedientes recientes
├── logout.php
├── includes/
│   ├── db.php              ← Conexión PDO a MySQL
│   ├── auth.php            ← Autenticación segura
│   ├── header.php          ← Menú y layout
│   └── footer.php
├── pages/
│   ├── expedientes.php     ← Nacionales e internacionales
│   ├── oportunidades.php   ← Centros comerciales y parkings
│   ├── instituciones.php   ← Ayuntamientos y CC.AA.
│   └── documentacion.php   ← Consulta y descarga de documentos
├── api/
│   ├── expedientes.php     ← AJAX: cargar más expedientes
│   └── download.php        ← Descarga/visualización de documentos
├── assets/
│   ├── css/style.css       ← Estilos (mobile-first)
│   └── js/app.js           ← Lógica de interfaz
└── storage/
    └── docs/               ← Documentos físicos (copiarlos aquí)
```

## Instalación

### 1. Requisitos
- PHP 8.0+
- MySQL 5.7+ / MariaDB
- Extensión PDO y PDO_MySQL habilitadas

### 2. Base de datos
```sql
-- Importar el archivo crmgw.sql:
mysql -u root -p crmgw < crmgw.sql
```

### 3. Configuración
Editar `includes/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'crmgw');
define('DB_USER', 'greenusr');     // tu usuario MySQL
define('DB_PASS', 'tu_contraseña');
```

### 4. Contraseñas de usuarios
Las contraseñas actuales en la BD usan el formato **ASP.NET Identity (PBKDF2)**,
que no es compatible directamente con PHP `password_verify()`.

**Para crear contraseñas desde PHP**, ejecutar este script una vez:
```php
<?php
require_once 'includes/db.php';
$db = getDB();
$hash = password_hash('nueva_contraseña', PASSWORD_BCRYPT);
$stmt = $db->prepare("UPDATE m_usuarios SET Password = ? WHERE User = 'gestyde'");
$stmt->execute([$hash]);
echo "Contraseña actualizada.";
```

O crear usuarios nuevos directamente desde la BD con contraseñas PHP:
```sql
INSERT INTO m_usuarios (IdRol, User, Password, Nombre, Apellido1, Activo)
VALUES (1, 'admin', '$2y$...hash_generado...', 'Admin', 'Green Wash', 1);
```

### 5. Documentos
Copiar los documentos del servidor original a `storage/docs/`.
Ajustar `BASE_DOCS_PATH` en `api/download.php` si se usa otra ruta.

### 6. HTTPS (producción)
En `includes/auth.php`, cambiar:
```php
'secure' => true,   // Activar en producción con HTTPS
```

---

## Seguridad implementada

✅ **SQL Injection**: Todas las consultas usan PDO con prepared statements  
✅ **XSS**: htmlspecialchars() en todas las salidas  
✅ **Session Fixation**: session_regenerate_id() al hacer login  
✅ **CSRF**: Token en el formulario de login  
✅ **Fuerza bruta**: sleep(1) en login fallido  
✅ **Cookies seguras**: httponly=true, samesite=Strict  
✅ **Path traversal**: realpath() + verificación de directorio en descargas  
✅ **Autenticación**: requireLogin() en cada página protegida  

## Webhook de WhatsApp

El endpoint `api/whatsapp_webhook.php` recibe mensajes de texto de WhatsApp Cloud API de Meta y crea un expediente con teléfono, nombre, mensaje, origen `WhatsApp` y fase inicial `88`.

1. Ejecutar `migracion_v6_whatsapp.sql` en la misma base de datos del CRM.
2. Definir en el servidor las variables `WHATSAPP_VERIFY_TOKEN` y `WHATSAPP_APP_SECRET`.
3. En Meta Developers configurar la URL `https://TU-DOMINIO/api/whatsapp_webhook.php`, el mismo token de verificación y suscribirse al campo `messages`.
4. Probar enviando un mensaje de texto al número de WhatsApp Business. Los mensajes repetidos de Meta se ignoran mediante `MESSAGE_ID`.

El endpoint acepta `GET` para la verificación de Meta y `POST` para los eventos. Para producción se recomienda mantener definido `WHATSAPP_APP_SECRET`, activar HTTPS y no incluir secretos en archivos públicos.

## Registro de correos con Mailgun

El endpoint `api/email_webhook.php` recibe los eventos de correo de Mailgun y los guarda en la tabla `crm_email_webhooks`. No crea expedientes. Los correos enviados desde el CRM continúan usando `SmtpMailer` y se registran como salientes en la misma tabla.

1. En Mailgun configura la ruta del dominio de recepción para reenviar los mensajes de `tecnico@gwgreenwash.com` a `https://TU-DOMINIO/api/email_webhook.php`.
2. Configura en el servidor estas variables:
    - `MAILGUN_SIGNING_KEY`: clave de firma de Mailgun.
    - `MAILGUN_DOMAIN`: dominio de recepción configurado en Mailgun, normalmente `gwgreenwash.com`.
    - `ALLOWED_EMAIL_TARGETS`: `tecnico@gwgreenwash.com`.
3. Mailgun debe tener publicados en DNS los registros MX del dominio de recepción.
4. Consulta los mensajes desde **Correos recibidos** en el menú del CRM, sin acceder a phpMyAdmin.

## Páginas disponibles

| Página | Ruta |
|--------|------|
| Login | `/index.php` |
| Inicio | `/dashboard.php` |
| Expedientes Nacionales | `/pages/expedientes.php?tipo=nacional` |
| Expedientes Internacionales | `/pages/expedientes.php?tipo=internacional` |
| Oportunidades CC | `/pages/oportunidades.php?tipo=centros_comerciales` |
| Oportunidades Parking | `/pages/oportunidades.php?tipo=parkings_publicos` |
| Ayuntamientos | `/pages/instituciones.php?tipo=ayuntamientos` |
| CC.AA. | `/pages/instituciones.php?tipo=ccaa` |
| Documentación | `/pages/documentacion.php` |

## Notas de desarrollo

- **Generar contraseña TPV**: pendiente de implementar (marcado como "Próximamente" en el menú)
- **"Cargar más"**: funciona por AJAX sin recargar la página
- **Búsqueda**: filtra en tiempo real (con debounce de 500ms)
- **Diseño**: mobile-first, optimizado para pantallas de teléfono
