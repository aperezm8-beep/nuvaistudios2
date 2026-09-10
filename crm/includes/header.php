<?php
// includes/header.php
// $pageTitle debe estar definido antes de incluir este archivo
if (!isset($pageTitle)) $pageTitle = 'CRM GW';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | GW CRM</title>
    <link rel="stylesheet" href="<?= $basePath ?? '' ?>assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="app-wrapper">
    <!-- TOP BAR -->
    <header class="topbar">
        <button class="hamburger" id="menuToggle" aria-label="Menú">
            <span></span><span></span><span></span>
        </button>
        <div class="topbar-brand">
            <span class="brand-icon">🌿</span>
            <span class="brand-name">GW CRM</span>
        </div>
        <div class="topbar-user">
            <span class="user-avatar"><?= strtoupper(substr($_SESSION['nombre'] ?? 'U', 0, 1)) ?></span>
            <span class="user-name-short"><?= htmlspecialchars(explode(' ', $_SESSION['nombre'] ?? 'Usuario')[0]) ?></span>
        </div>
    </header>

    <!-- SIDEBAR -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <span class="brand-icon">🌿</span>
            <span>GW CRM</span>
            <button class="sidebar-close" id="sidebarClose">✕</button>
        </div>
        <div class="sidebar-user">
            <div class="user-avatar-lg"><?= strtoupper(substr($_SESSION['nombre'] ?? 'U', 0, 1)) ?></div>
            <div>
                <div class="user-fullname"><?= htmlspecialchars($_SESSION['nombre'] ?? '') ?></div>
                <div class="user-role">Administrador</div>
            </div>
        </div>

        <ul class="nav-list">
            <li class="nav-item <?= ($currentPage ?? '') === 'inicio' ? 'active' : '' ?>">
                <a href="<?= $basePath ?? '' ?>dashboard.php">
                    <span class="nav-icon">🏠</span><span>Inicio</span>
                </a>
            </li>
            <li class="nav-section-label">EXPEDIENTES</li>
            <li class="nav-item <?= ($currentPage ?? '') === 'exp-nacionales' ? 'active' : '' ?>">
                <a href="<?= $basePath ?? '' ?>pages/expedientes.php?tipo=nacional">
                    <span class="nav-icon">🇪🇸</span><span>Nacionales</span>
                </a>
            </li>
            <li class="nav-item <?= ($currentPage ?? '') === 'exp-internacionales' ? 'active' : '' ?>">
                <a href="<?= $basePath ?? '' ?>pages/expedientes.php?tipo=internacional">
                    <span class="nav-icon">🌍</span><span>Internacionales</span>
                </a>
            </li>
            <li class="nav-section-label">OPORTUNIDADES</li>
            <li class="nav-item <?= ($currentPage ?? '') === 'op-cc' ? 'active' : '' ?>">
                <a href="<?= $basePath ?? '' ?>pages/oportunidades.php?tipo=centros_comerciales">
                    <span class="nav-icon">🏬</span><span>Centros Comerciales</span>
                </a>
            </li>
            <li class="nav-item <?= ($currentPage ?? '') === 'op-parking' ? 'active' : '' ?>">
                <a href="<?= $basePath ?? '' ?>pages/oportunidades.php?tipo=parkings_publicos">
                    <span class="nav-icon">🅿️</span><span>Parkings Públicos</span>
                </a>
            </li>
            <li class="nav-section-label">INSTITUCIONES</li>
            <li class="nav-item <?= ($currentPage ?? '') === 'inst-ayuntamientos' ? 'active' : '' ?>">
                <a href="<?= $basePath ?? '' ?>pages/instituciones.php?tipo=ayuntamientos">
                    <span class="nav-icon">🏛️</span><span>Ayuntamientos</span>
                </a>
            </li>
            <li class="nav-item <?= ($currentPage ?? '') === 'inst-ccaa' ? 'active' : '' ?>">
                <a href="<?= $basePath ?? '' ?>pages/instituciones.php?tipo=ccaa">
                    <span class="nav-icon">🗺️</span><span>CC.AA.</span>
                </a>
            </li>
            <li class="nav-section-label">DOCUMENTOS</li>
            <li class="nav-item <?= ($currentPage ?? '') === 'emails' ? 'active' : '' ?>">
                <a href="<?= $basePath ?? '' ?>pages/emails.php">
                    <span class="nav-icon">✉️</span><span>Correos recibidos</span>
                </a>
            </li>
            <li class="nav-item <?= ($currentPage ?? '') === 'documentacion' ? 'active' : '' ?>">
                <a href="<?= $basePath ?? '' ?>pages/documentacion.php">
                    <span class="nav-icon">📁</span><span>Consultar Documentación</span>
                </a>
            </li>
            <li class="nav-item <?= (isset($currentPage) && $currentPage === 'tpv') ? 'active' : '' ?>">
                <a href="<?= $basePath ?? '' ?>pages/tpv.php">
                    <span class="nav-icon">🔑</span><span>Generar Contraseña TPV</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <a href="<?= $basePath ?? '' ?>logout.php" class="btn-logout">
                <span>⬅️</span> Cerrar sesión
            </a>
        </div>
    </nav>

    <!-- OVERLAY -->
    <div class="overlay" id="overlay"></div>

    <!-- MAIN CONTENT -->
    <main class="main-content">
