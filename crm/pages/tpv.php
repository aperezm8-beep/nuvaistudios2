<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$basePath    = '../';
$currentPage = 'tpv';
$pageTitle   = 'Generar Contraseña TPV';

/**
 * Genera la contraseña TPV a partir de un número de 6 dígitos.
 *
 * Algoritmo por pareja (posiciones 1-2, 3-4, 5-6):
 *   1. Intercambiar los dos dígitos de la pareja
 *   2. Sumar 1 al segundo dígito del par resultante (módulo 10, sin acarreo)
 * Finalmente añadir '9' al final.
 *
 * Ejemplo: 247801
 *   24 → 42 → 43
 *   78 → 87 → 88
 *   01 → 10 → 11
 *   Resultado: 4388119
 */
function generarClaveTPV(string $numero): array
{
    // Validar: exactamente 6 dígitos
    if (!preg_match('/^\d{6}$/', $numero)) {
        return ['ok' => false, 'error' => 'El número debe tener exactamente 6 dígitos.'];
    }

    $pasos  = [];
    $result = '';

    for ($i = 0; $i < 6; $i += 2) {
        $d1 = (int)$numero[$i];
        $d2 = (int)$numero[$i + 1];

        // Paso 1: intercambiar
        $s1 = $d2;
        $s2 = $d1;

        // Paso 2: sumar 1 al segundo (módulo 10, sin acarreo)
        $s2final = ($s2 + 1) % 10;

        $pasos[] = [
            'original'     => "{$d1}{$d2}",
            'intercambiado'=> "{$s1}{$s2}",
            'resultado'    => "{$s1}{$s2final}",
        ];

        $result .= "{$s1}{$s2final}";
    }

    $result .= '9';

    return [
        'ok'     => true,
        'clave'  => $result,
        'pasos'  => $pasos,
    ];
}

$numero    = '';
$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero = trim($_POST['numero'] ?? '');
    $resultado = generarClaveTPV($numero);
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">🔑 Generar Contraseña TPV</h1>
    <p class="page-subtitle">Introduce el número de 6 dígitos para obtener la clave</p>
</div>

<div class="tpv-wrapper">

    <!-- FORMULARIO -->
    <div class="card tpv-card">
        <form method="POST" action="tpv.php" id="tpvForm" autocomplete="off">

            <div class="tpv-input-group">
                <label class="tpv-label" for="numero">Número de 6 dígitos</label>
                <div class="tpv-input-wrap">
                    <input
                        type="text"
                        id="numero"
                        name="numero"
                        class="tpv-input"
                        placeholder="_ _ _ _ _ _"
                        maxlength="6"
                        pattern="\d{6}"
                        inputmode="numeric"
                        value="<?= htmlspecialchars($numero) ?>"
                        autofocus
                    >
                    <button type="submit" class="tpv-btn-generate">
                        <span class="tpv-btn-icon">⚙️</span> Generar
                    </button>
                </div>
                <p class="tpv-hint">Solo se admiten exactamente 6 dígitos numéricos.</p>
            </div>

        </form>
    </div>

    <!-- RESULTADO -->
    <?php if ($resultado !== null): ?>

        <?php if (!$resultado['ok']): ?>
        <div class="tpv-error">
            ⚠️ <?= htmlspecialchars($resultado['error']) ?>
        </div>

        <?php else: ?>
        <div class="tpv-result-card" id="resultCard">

            <!-- Clave generada -->
            <div class="tpv-clave-block">
                <div class="tpv-clave-label">Contraseña TPV generada</div>
                <div class="tpv-clave-value" id="claveValue"><?= htmlspecialchars($resultado['clave']) ?></div>
                <button class="tpv-copy-btn" onclick="copiarClave()" id="copyBtn">
                    📋 Copiar
                </button>
            </div>


        </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<style>
/* ── WRAPPER ──────────────────────────────────────────────── */
.tpv-wrapper {
    max-width: 580px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* ── FORMULARIO ───────────────────────────────────────────── */
.tpv-card { padding: 28px 28px 24px; }

.tpv-label {
    display: block;
    font-size: 0.82rem;
    font-weight: 700;
    color: #374151;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.tpv-input-wrap {
    display: flex;
    gap: 10px;
    align-items: stretch;
}

.tpv-input {
    flex: 1;
    font-size: 2rem;
    font-weight: 800;
    letter-spacing: 0.35em;
    text-align: center;
    padding: 14px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    color: #111827;
    background: #f9fafb;
    transition: border-color .2s, box-shadow .2s;
    font-family: 'Courier New', monospace;
}
.tpv-input:focus {
    outline: none;
    border-color: var(--green-500);
    box-shadow: 0 0 0 3px rgba(22,163,74,.15);
    background: #fff;
}
.tpv-input::placeholder { color: #d1d5db; letter-spacing: 0.2em; }

.tpv-btn-generate {
    padding: 0 28px;
    background: linear-gradient(135deg, var(--green-600), var(--green-700));
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
    transition: all .18s;
    font-family: inherit;
}
.tpv-btn-generate:hover  { filter: brightness(1.1); transform: translateY(-1px); }
.tpv-btn-generate:active { transform: translateY(0); }
.tpv-btn-icon { font-size: 1.1rem; }

.tpv-hint {
    margin: 8px 0 0;
    font-size: 0.75rem;
    color: #9ca3af;
}

/* ── ERROR ────────────────────────────────────────────────── */
.tpv-error {
    background: #fef2f2;
    border: 1.5px solid #fca5a5;
    color: #991b1b;
    padding: 14px 18px;
    border-radius: 12px;
    font-size: 0.9rem;
    font-weight: 600;
}

/* ── RESULTADO ────────────────────────────────────────────── */
.tpv-result-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 16px rgba(0,0,0,.08);
    overflow: hidden;
}

/* Bloque clave generada */
.tpv-clave-block {
    background: linear-gradient(135deg, #16a34a, #15803d);
    padding: 28px 28px 24px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}
.tpv-clave-label {
    font-size: 0.78rem;
    font-weight: 700;
    color: #bbf7d0;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}
.tpv-clave-value {
    font-size: 3rem;
    font-weight: 900;
    color: #fff;
    letter-spacing: 0.25em;
    font-family: 'Courier New', monospace;
    text-shadow: 0 2px 8px rgba(0,0,0,.2);
    line-height: 1;
}
.tpv-copy-btn {
    background: rgba(255,255,255,.2);
    color: white;
    border: 1.5px solid rgba(255,255,255,.4);
    padding: 8px 22px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    transition: all .18s;
    font-family: inherit;
}
.tpv-copy-btn:hover { background: rgba(255,255,255,.3); }


</style>

<script>
// Solo permitir dígitos en el input
document.getElementById('numero').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
});

// Submit automático al llegar a 6 dígitos
document.getElementById('numero').addEventListener('keyup', function() {
    if (this.value.length === 6) {
        // Pequeño delay para que el usuario vea los 6 dígitos antes de enviar
        setTimeout(() => document.getElementById('tpvForm').submit(), 300);
    }
});

function copiarClave() {
    const clave = document.getElementById('claveValue').textContent.trim();
    navigator.clipboard.writeText(clave).then(() => {
        const btn = document.getElementById('copyBtn');
        btn.textContent = '✅ Copiado';
        setTimeout(() => { btn.textContent = '📋 Copiar'; }, 2000);
    }).catch(() => {
        // Fallback para navegadores sin clipboard API
        const el = document.createElement('textarea');
        el.value = clave;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        const btn = document.getElementById('copyBtn');
        btn.textContent = '✅ Copiado';
        setTimeout(() => { btn.textContent = '📋 Copiar'; }, 2000);
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
