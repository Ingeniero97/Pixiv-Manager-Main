<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

$page             = max(1, (int)($_GET['page']            ?? 1));
$perPage          = max(1, min(500, (int)($_GET['per_page'] ?? 50)));
$main_search      = trim((string)($_GET['main_search']      ?? ''));
$main_ugoira      = (string)($_GET['main_ugoira']           ?? '');
$secondary_search = trim((string)($_GET['secondary_search'] ?? ''));
$autor_search     = trim((string)($_GET['autor_search']     ?? ''));
$estado           = isset($_GET['estado']) ? (int)$_GET['estado'] : 1;

$ugoira_filters = $main_ugoira !== ''
    ? array_values(array_filter(array_map('trim', explode(',', $main_ugoira))))
    : [];

$main_col      = trim((string)($_GET['main_col']      ?? ''));
$secondary_col = trim((string)($_GET['secondary_col'] ?? ''));

$filters = [
    'estado' => in_array($estado, [0, 1], true) ? $estado : 1,
    'ugoira' => $ugoira_filters,
    'primary_col'   => $main_col      !== '' ? $main_col      : null,
    'secondary_col' => $secondary_col !== '' ? $secondary_col : null,
];

$pdo = getPDO();

// 1. Construir el WHERE BASE
[$baseWhereSql, $baseParams] = buildWhereClause($main_search, $filters, $secondary_search);

$whereSql = $baseWhereSql;
$params   = $baseParams;

// 2. Lógica del filtro interno inteligente (Filtrar autores)
if ($autor_search !== '') {
    // Si parece una URL (contiene http o /users/), buscamos en el enlace. Si no, buscamos por nombre.
    $isUrlSearch = (strpos($autor_search, 'http') !== false || strpos($autor_search, '/users/') !== false);

    if ($isUrlSearch) {
        $whereSql .= " AND `Contenedor_Enlace_Autor_Album` LIKE :as_link";
        $params[':as_link'] = '%' . $autor_search . '%';
    } else {
        $whereSql .= " AND `Contenedor_Autor_Album` LIKE :as_name";
        $params[':as_name'] = '%' . $autor_search . '%';
    }
}

// 3. Totales globales
$sqlTotals = "SELECT
        COUNT(*)                                                     AS total_ilustraciones,
        COUNT(DISTINCT NULLIF(`Contenedor_Enlace_Autor_Album`, ''))  AS total_autores_enlaces
    FROM ilustraciones
    WHERE $whereSql";
$stmt = $pdo->prepare($sqlTotals);
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->execute();
$totals = $stmt->fetch() ?: ['total_ilustraciones' => 0, 'total_autores_enlaces' => 0];

// 4. Conteo total de autores (Grupos por Nombre + Enlace)
$sqlCount = "SELECT COUNT(*) FROM (
    SELECT 1 FROM ilustraciones 
    WHERE $whereSql 
    GROUP BY `Contenedor_Autor_Album`, `Contenedor_Enlace_Autor_Album`
) AS t";
$stmt = $pdo->prepare($sqlCount);
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->execute();
$totalAutores = (int)$stmt->fetchColumn();

$totalPages = $totalAutores > 0 ? (int)ceil($totalAutores / $perPage) : 1;
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// 5. Obtener la página de autores (AGRUPADO POR NOMBRE Y ENLACE)
$sqlPage = "SELECT
        `Contenedor_Autor_Album` AS nombre,
        `Contenedor_Enlace_Autor_Album` AS enlace,
        COUNT(*) AS total_nombre
    FROM ilustraciones
    WHERE $whereSql
    GROUP BY `Contenedor_Autor_Album`, `Contenedor_Enlace_Autor_Album`
    ORDER BY total_nombre DESC, nombre ASC
    LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sqlPage);
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

// 6. Obtener info global por enlace (para los totales y el Badge 🔀)
$enlaces = array_filter(array_column($rows, 'enlace'));
$linkInfo = [];

if (!empty($enlaces)) {
    $inPh = implode(',', array_fill(0, count($enlaces), '?'));
    $sqlLinkInfo = "SELECT
        `Contenedor_Enlace_Autor_Album` AS enlace,
        SUM(CASE WHEN `Estado` = 1 THEN 1 ELSE 0 END) AS activos,
        SUM(CASE WHEN `Estado` = 0 THEN 1 ELSE 0 END) AS inactivos,
        GROUP_CONCAT(DISTINCT NULLIF(`Contenedor_Autor_Album`, '') SEPARATOR '|||') AS nombres_globales
    FROM ilustraciones
    WHERE `Contenedor_Enlace_Autor_Album` IN ($inPh)
    GROUP BY `Contenedor_Enlace_Autor_Album`";

    $stmtLinkInfo = $pdo->prepare($sqlLinkInfo);
    foreach (array_values($enlaces) as $i => $e) { $stmtLinkInfo->bindValue($i + 1, $e, PDO::PARAM_STR); }
    $stmtLinkInfo->execute();

    foreach ($stmtLinkInfo->fetchAll() as $r) {
        $linkInfo[$r['enlace']] = [
            'activos' => (int)$r['activos'],
            'inactivos' => (int)$r['inactivos'],
            'nombres_globales' => $r['nombres_globales'] !== ''
                ? array_values(array_filter(explode('|||', $r['nombres_globales']), static fn($s) => $s !== ''))
                : []
        ];
    }
}

// 7. Armar respuesta
$autores = [];
foreach ($rows as $r) {
    $enlace     = (string)($r['enlace'] ?? '');
    $nombre     = (string)($r['nombre'] ?? '');
    
    $activos    = $linkInfo[$enlace]['activos'] ?? 0;
    $inactivos  = $linkInfo[$enlace]['inactivos'] ?? 0;
    $nombresGlobales = $linkInfo[$enlace]['nombres_globales'] ?? [$nombre];

    $autores[] = [
        'nombre'    => $nombre,
        'nombres'   => $nombresGlobales,
        'enlace'    => $enlace,
        'total'     => (int)$r['total_nombre'], // Solo el total de la pestaña actual
        'activos'   => $activos,              // Activos del enlace
        'inactivos' => $inactivos,            // Inactivos del enlace
    ];
}

json_response([
    'success'               => true,
    'autores'               => $autores,
    'pagina_actual'         => $page,
    'per_page'              => $perPage,
    'total_paginas'         => $totalPages,
    'total_autores'         => $totalAutores,
    'total_autores_enlaces' => (int)$totals['total_autores_enlaces'],
    'total_ilustraciones'   => (int)$totals['total_ilustraciones'],
]);