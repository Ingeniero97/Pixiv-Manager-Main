<?php
declare(strict_types=1);

/**
 * backend-search.php — Búsqueda secundaria por AJAX.
 * Mantiene los filtros de la URL (principal + ugoira + estado)
 * y añade el término secundario con AND.
 */

require_once __DIR__ . '/api/_helpers.php';

$search      = trim((string)($_GET['search']          ?? ''));
$page        = max(1, (int)($_GET['page']             ?? 1));
$perPage     = max(1, min(10000, (int)($_GET['per_page'] ?? 50)));
$main_search = trim((string)($_GET['main_search']     ?? ''));
$main_ugoira = (string)($_GET['main_ugoira']          ?? '');
$main_estado = isset($_GET['main_estado']) ? (int)$_GET['main_estado'] : 1;

$ugoira_filters = $main_ugoira !== ''
    ? array_values(array_filter(array_map('trim', explode(',', $main_ugoira))))
    : [];

// ── NUEVO: columnas de búsqueda (compartidas con el principal) ──
$main_col      = trim((string)($_GET['main_col']      ?? ''));
$secondary_col = trim((string)($_GET['secondary_col'] ?? ''));

$filters = [
    'estado' => in_array($main_estado, [0, 1], true) ? $main_estado : null,
    'ugoira' => $ugoira_filters,
    'primary_col'   => $main_col      !== '' ? $main_col      : null,
    'secondary_col' => $secondary_col !== '' ? $secondary_col : null,
];

$result = getIllustrations($page, $perPage, $main_search, $filters, $search);
$stats  = getStatistics($main_search, $filters, $search);

json_response([
    'success'      => true,
    'data'         => $result['data'],
    'total_result' => $result['total'],
    'total_pages'  => $result['total_pages'],
    'page'         => $result['page'],
    'per_page'     => $result['per_page'],
    'total'        => $stats['total'],
    'activos'      => $stats['activos'],
    'inactivos'    => $stats['inactivos'],
]);