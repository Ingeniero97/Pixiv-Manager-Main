<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

require_method('POST');
$data = read_json_body();

$columns = $data['columns'] ?? [];
$ids = $data['ids'] ?? null;
$filters = $data['filters'] ?? [];

if (empty($columns) || !is_array($columns)) {
    json_error('Debes seleccionar al menos una columna.', 400);
}

// Whitelist de columnas permitidas para borrar
$permitidas = [
    'Contenedor_Tipo', 'Contenedor_Numero_Hoja', 'Contenedor_Alt_Album',
    'Contenedor_Autor_Album', 'Contenedor_Enlace_Autor_Album', 'Contenedor_Nombre_Album',
    'Contenedor_Enlace_Album', 'Contenedor_ID_Enlace', 'Enlace_Titulo',
    'Enlace_Img_Original', 'Enlace_Alt_Img', 'Enlace_Ugoira', 'Enlace_Reading_works_or_Show_all',
    'Enlace_Tags_Completo', 'Enlace_Tag_01', 'Enlace_Tag_02', 'Enlace_Tag_03',
    'Enlace_Fecha_Formato_01', 'Enlace_Fecha_Formato_02'
];

$colsToClear = array_intersect($columns, $permitidas);
if (empty($colsToClear)) {
    json_error('Ninguna de las columnas seleccionadas es válida para borrar.', 400);
}

$pdo = getPDO();
$setParts = [];
foreach ($colsToClear as $col) {
    $setParts[] = "`$col` = NULL";
}
$setSql = implode(', ', $setParts);

$whereSql = "";
$params = [];

// Si hay IDs seleccionados, borramos solo esos
if (is_array($ids) && count($ids) > 0) {
    // Procesar en lotes para no romper el query si son muchos
    $affected = 0;
    try {
        $pdo->beginTransaction();
        foreach (array_chunk($ids, 1000) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '?'));
            $sql = "UPDATE ilustraciones SET $setSql WHERE ORDEN IN ($ph)";
            $stmt = $pdo->prepare($sql);
            foreach ($chunk as $i => $id) {
                $stmt->bindValue($i + 1, (string)$id, PDO::PARAM_STR);
            }
            $stmt->execute();
            $affected += $stmt->rowCount();
        }
        $pdo->commit();
        json_response(['success' => true, 'changed' => $affected]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_error('Error al limpiar los datos.', 500);
    }
} 
// Si no hay IDs, borramos según los filtros de búsqueda actuales
else {
    $estado           = isset($filters['estado']) ? (int)$filters['estado'] : 1;
    $main_search      = trim((string)($filters['main_search']      ?? ''));
    $main_ugoira      = (string)($filters['main_ugoira']           ?? '');
    $secondary_search = trim((string)($filters['secondary_search'] ?? ''));
    // ── NUEVO: columnas de búsqueda ──
    $primary_col      = trim((string)($filters['primary_col']      ?? ''));
    $secondary_col    = trim((string)($filters['secondary_col']    ?? ''));

    $ugoira_filters = $main_ugoira !== '' ? array_values(array_filter(array_map('trim', explode(',', $main_ugoira)))) : [];
    $f_array = [
        'estado'  => in_array($estado, [0,1]) ? $estado : 1,
        'ugoira'  => $ugoira_filters,
        'primary_col'   => $primary_col   !== '' ? $primary_col   : null,
        'secondary_col' => $secondary_col !== '' ? $secondary_col : null,
    ];

    [$whereSql, $params] = buildWhereClause($main_search, $f_array, $secondary_search);
    
    $sql = "UPDATE ilustraciones SET $setSql WHERE $whereSql";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    json_response(['success' => true, 'changed' => $stmt->rowCount()]);
}