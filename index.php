<?php
declare(strict_types=1);

/**
 * index.php — Vista principal "Buscador".
 * Solo orquesta: parsea query, consulta, renderiza vistas parciales.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';

$items_per_page = isset($_GET['length']) ? max(1, (int)$_GET['length']) : 50;
$page           = isset($_GET['page'])   ? max(1, (int)$_GET['page'])   : 1;
$search         = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

$ugoira_raw = isset($_GET['ugoira']) ? (string)$_GET['ugoira'] : '';
$ugoira_filters = $ugoira_raw !== ''
    ? array_values(array_filter(array_map('trim', explode(',', $ugoira_raw))))
    : [];

// ── NUEVO: columnas de búsqueda (principal y secundaria) ──
$primary_col = isset($_GET['col'])  ? trim((string)$_GET['col'])  : '';
$primary_col = in_array($primary_col, SEARCH_COLUMNS, true) ? $primary_col : '';

$secondary_col = isset($_GET['scol']) ? trim((string)$_GET['scol']) : '';
$secondary_col = in_array($secondary_col, SEARCH_COLUMNS, true) ? $secondary_col : '';

$filters = [
    'estado' => 1,
    'ugoira' => $ugoira_filters,
    'primary_col'   => $primary_col   !== '' ? $primary_col   : null,
    'secondary_col' => $secondary_col !== '' ? $secondary_col : null,
];

$result = getIllustrations($page, $items_per_page, $search, $filters);
$stats  = getStatistics($search, $filters);

$view_data = [
    'mode'            => 'index',
    'items_per_page'  => $items_per_page,
    'page'            => $result['page'],
    'search'          => $search,
    'ugoira_filters'  => $ugoira_filters,
    'primary_col'     => $primary_col,
    'secondary_col'   => $secondary_col,
    'illustrations'   => $result['data'],
    'total_items'     => $result['total'],
    'total_pages'     => $result['total_pages'],
    'stats'           => $stats,
];

// ---------- Render ----------
require __DIR__ . '/views/layouts/header.php';
require __DIR__ . '/views/index/tabla.php';
require __DIR__ . '/views/index/modales.php';
require __DIR__ . '/views/layouts/footer.php';