<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

require_method('POST');
$data = read_json_body();

$pairs = $data['pairs'] ?? [];
$identifier_field = $data['identifier_field'] ?? 'Contenedor_Enlace_Album_href';

// Validar campo identificador
if (!in_array($identifier_field, ['ORDEN', 'Contenedor_Enlace_Album_href'])) {
    json_error('Campo identificador no válido.', 400);
}

if (!is_array($pairs) || empty($pairs)) {
    json_error('Se requiere un array de registros.', 400);
}
if (count($pairs) > 10000) {
    json_error('Máximo 10,000 registros por lote.', 400);
}

$dryRun = !empty($data['dry_run']);
$pdo = getPDO();

// Columnas que se permiten actualizar (Whitelist)
$permitidas = [
    'Contenedor_Tipo', 'Contenedor_Numero_Hoja', 'Contenedor_Alt_Album',
    'Contenedor_Autor_Album', 'Contenedor_Enlace_Autor_Album', 'Contenedor_Nombre_Album',
    'Contenedor_Enlace_Album', 'Contenedor_ID_Enlace', 'Enlace_Titulo',
    'Enlace_Img_Original', 'Enlace_Alt_Img', 'Enlace_Ugoira', 'Enlace_Reading_works_or_Show_all',
    'Enlace_Tags_Completo', 'Enlace_Tag_01', 'Enlace_Tag_02', 'Enlace_Tag_03',
    'Enlace_Fecha_Formato_01', 'Enlace_Fecha_Formato_02', 'Estado'
];

// 1. Deduplicar y limpiar
$clean = [];
$invalidos = 0;

foreach ($pairs as $p) {
    $id = trim((string)($p['identifier'] ?? ''));
    $fields = $p['fields'] ?? [];
    
    if ($id === '' || !is_array($fields)) {
        $invalidos++;
        continue;
    }
    
    // Validar que el identificador sea URL (si no es ORDEN)
    if ($identifier_field === 'Contenedor_Enlace_Album_href' && !preg_match('#^https?://#i', $id)) {
        $invalidos++;
        continue;
    }

    $clean[$id] = []; 
    foreach ($fields as $col => $val) {
        if (in_array($col, $permitidas, true)) {
            $clean[$id][$col] = $val;
        }
    }
}

$validPairs = [];
foreach ($clean as $id => $fields) {
    if (!empty($fields)) {
        $validPairs[$id] = $fields;
    }
}

if (empty($validPairs)) {
    json_response(['success' => true, 'total' => count($pairs), 'changed' => 0, 'not_found' => 0, 'details' => []]);
}

// 2. Verificar cuáles existen en la BD
$ids = array_keys($validPairs);
$existentes = [];
foreach (array_chunk($ids, 1000) as $chunk) {
    $ph = implode(',', array_fill(0, count($chunk), '?'));
    $stmt = $pdo->prepare("SELECT `$identifier_field` FROM ilustraciones WHERE `$identifier_field` IN ($ph)");
    $stmt->execute($chunk);
    $existentes = array_merge($existentes, $stmt->fetchAll(PDO::FETCH_COLUMN));
}

$notFoundCount = count($ids) - count($existentes);
$foundIds = array_flip($existentes);

$details = [];
$toUpdate = [];
foreach ($validPairs as $id => $fields) {
    if (isset($foundIds[$id])) {
        // ═══ NEW: Devolvemos los campos a modificar para la vista previa ═══
        $details[] = ['identifier' => $id, 'status' => 'changed', 'fields' => $fields]; 
        $toUpdate[$id] = $fields;
    } else {
        $details[] = ['identifier' => $id, 'status' => 'not_found', 'fields' => []];
    }
}

// Si es dry run, devolver reporte
if ($dryRun) {
    json_response([
        'success' => true,
        'total' => count($pairs),
        'changed' => count($toUpdate),
        'not_found' => $notFoundCount,
        'details' => $details
    ]);
}

// 3. Ejecutar UPDATE masivo usando CASE WHEN
if (empty($toUpdate)) {
    json_response(['success' => true, 'changed' => 0, 'not_found' => $notFoundCount, 'details' => $details]);
}

try {
    $pdo->beginTransaction();
    $affected = 0;

    foreach (array_chunk($toUpdate, 500, true) as $chunk) {
        $bindings = [];
        $chunkIds = array_keys($chunk);

        $columnsToUpdate = [];
        foreach ($chunk as $id => $fields) {
            foreach ($fields as $col => $val) {
                $columnsToUpdate[$col][] = ['id' => $id, 'val' => $val];
            }
        }

        $setParts = [];
        foreach ($columnsToUpdate as $col => $items) {
            $caseSql = "`$col` = CASE `$identifier_field` ";
            foreach ($items as $item) {
                $caseSql .= "WHEN ? THEN ? ";
                $bindings[] = $item['id'];
                $bindings[] = $item['val'];
            }
            $caseSql .= "ELSE `$col` END";
            $setParts[] = $caseSql;
        }

        $sql = "UPDATE ilustraciones SET " . implode(', ', $setParts) . " WHERE `$identifier_field` IN (";
        $sql .= implode(',', array_fill(0, count($chunkIds), '?')) . ")";
        
        foreach ($chunkIds as $id) $bindings[] = $id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $affected += $stmt->rowCount();
    }

    $pdo->commit();
    json_response(['success' => true, 'changed' => $affected, 'not_found' => $notFoundCount, 'details' => $details]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[bulk-update-records] ' . $e->getMessage());
    json_error('Error al aplicar los cambios. Se revirtió todo.', 500);
}