<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

$main_search      = trim((string)($_GET['main_search']      ?? ''));
$main_ugoira      = (string)($_GET['main_ugoira']           ?? '');
$secondary_search = trim((string)($_GET['secondary_search'] ?? ''));
$estado           = isset($_GET['estado']) ? (int)$_GET['estado'] : 1;
$ids_json         = $_GET['ids'] ?? '';
$columnas_json    = $_GET['columns'] ?? '[]';

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = max(1, (int)($_GET['limit'] ?? 2000));
$offset = ($page - 1) * $limit;

$ugoira_filters = $main_ugoira !== '' ? array_values(array_filter(array_map('trim', explode(',', $main_ugoira)))) : [];

$main_col      = trim((string)($_GET['main_col']      ?? ''));
$secondary_col = trim((string)($_GET['secondary_col'] ?? ''));

$filters = [
    'estado' => in_array($estado, [0, 1], true) ? $estado : 1,
    'ugoira' => $ugoira_filters,
    'primary_col'   => $main_col      !== '' ? $main_col      : null,
    'secondary_col' => $secondary_col !== '' ? $secondary_col : null,
];

$pdo = getPDO();

// 1. Preparar WHERE y Parámetros
$whereSql = "";
$params = [];

if ($ids_json !== '') {
    $ids = json_decode($ids_json, true);
    if (is_array($ids) && count($ids) > 0) {
        $inPh = implode(',', array_fill(0, count($ids), '?'));
        $whereSql = "ORDEN IN ($inPh)";
        // Aseguramos que los parámetros sean un array indexado (0, 1, 2...) con strings
        $params = array_values(array_map('strval', $ids));
    } else {
        json_response(['success' => true, 'data' => [], 'total_real' => 0]);
    }
} else {
    // buildWhereClause devuelve parámetros con nombre (ej: ':estado')
    [$whereSql, $params] = buildWhereClause($main_search, $filters, $secondary_search);
}

// 2. Contar total
$stmt = $pdo->prepare("SELECT COUNT(*) FROM ilustraciones WHERE $whereSql");
$stmt->execute($params);
$totalReal = (int)$stmt->fetchColumn();

// 3. Preparar SELECT dinámico
$columnas_permitidas = [
    'ORDEN', 'web_scraper_order', 'web_scraper_start_url', 'Contenedor_Tipo',
    'Contenedor_Numero_Hoja', 'Contenedor_Alt_Album', 'Contenedor_Autor_Album',
    'Contenedor_Enlace_Autor_Album', 'Contenedor_Nombre_Album', 'Contenedor_Enlace_Album',
    'Contenedor_Enlace_Album_href', 'Contenedor_ID_Enlace', 'Enlace_Titulo',
    'Enlace_Img_Original', 'Enlace_Alt_Img', 'Enlace_Ugoira', 'Enlace_Reading_works_or_Show_all',
    'Enlace_Tags_Completo', 'Enlace_Tag_01', 'Enlace_Tag_02', 'Enlace_Tag_03',
    'Enlace_Fecha_Formato_01', 'Enlace_Fecha_Formato_02', 'Estado'
];

$cols_seleccionadas = json_decode($columnas_json, true);
if (!is_array($cols_seleccionadas) || empty($cols_seleccionadas)) {
    $cols_seleccionadas = $columnas_permitidas;
} else {
    $cols_seleccionadas = array_intersect($cols_seleccionadas, $columnas_permitidas);
    // Fix #2: si TODAS las columnas enviadas eran inválidas, fallback a todas las permitidas
    if (empty($cols_seleccionadas)) {
        $cols_seleccionadas = $columnas_permitidas;
    }
}

$selectStr = implode(', ', array_map(fn($c) => "`$c`", $cols_seleccionadas));

// 4. Consulta Paginada (Usamos variables enteras directas para evitar conflictos con bindValue)
$sql = "SELECT $selectStr FROM ilustraciones WHERE $whereSql ORDER BY ORDEN DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params); // execute() maneja tanto parámetros con nombre como posicionales
$rows = $stmt->fetchAll();

json_response([
    'success' => true,
    'data' => $rows,
    'total_real' => $totalReal,
    'page' => $page,
    'limit' => $limit
]);