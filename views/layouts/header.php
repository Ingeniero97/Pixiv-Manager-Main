<?php
/** @var array $view_data */
$stats          = $view_data['stats'];
$items_per_page = $view_data['items_per_page'];
$mode           = $view_data['mode'] ?? 'index';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pixiv Manager — <?= $mode === 'index' ? 'Buscador' : 'Restauración' ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">

    <script>
        // Config inyectada desde PHP — usada por main.js y autores.js
        window.APP_CONFIG = {
            itemsPerPage: <?= (int)$items_per_page ?>,
            mode: '<?= e($mode) ?>',
            apiBase: 'api/'
        };
    </script>
</head>
<body>

<div class="container main-container">

    <!-- A. HEADER con 3 badges -->
    <div class="header-section">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-2"><i class="fas fa-cube me-2"></i>Pixiv Manager</h1>
                <p class="mb-0 opacity-75">Gestión avanzada de ilustraciones</p>
            </div>
            <div class="col-md-4 text-end">
                <div class="d-flex flex-wrap justify-content-end gap-2">
                    <span class="stats-badge bg-primary text-white" id="statsTotal"
                          title="Total de registros (activos + inactivos)">
                        <i class="fas fa-database me-1"></i>
                        <?= number_format($stats['total']) ?>
                    </span>
                    <span class="stats-badge bg-success text-white" id="statsActivos"
                          title="Registros activos">
                        <i class="fas fa-check-circle me-1"></i>
                        <?= number_format($stats['activos']) ?>
                    </span>
                    <span class="stats-badge bg-danger text-white" id="statsInactivos"
                          title="Registros inactivos (papelera)">
                        <i class="fas fa-times-circle me-1"></i>
                        <?= number_format($stats['inactivos']) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- B. Navegación -->
    <div class="border-bottom">
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link <?= $mode === 'index' ? 'active' : '' ?>" href="index.php">
                    <i class="fas fa-search me-1"></i> Buscador
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $mode === 'restauracion' ? 'active' : '' ?>" href="restauracion.php">
                    <i class="fas fa-trash-restore me-1"></i> Restauración
                </a>
            </li>
        </ul>
    </div>