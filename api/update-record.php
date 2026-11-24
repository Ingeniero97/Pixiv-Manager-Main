<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

require_method('POST');
$data  = read_json_body();
$orden = $data['orden'] ?? $data['ORDEN'] ?? null;
if ($orden === null || $orden === '' || !is_numeric((string)$orden)) {
    json_error('ORDEN requerido y numérico', 400);
}

// Whitelist estricta (nunca incluye ORDEN)
$editable = [
    'Contenedor_Tipo', 'Contenedor_Numero_Hoja', 'Contenedor_Alt_Album',
    'Contenedor_Autor_Album', 'Contenedor_Enlace_Autor_Album', 'Contenedor_Nombre_Album',
    'Contenedor_Enlace_Album', 'Contenedor_Enlace_Album_href', 'Contenedor_ID_Enlace',
    'Enlace_Titulo', 'Enlace_Img_Original', 'Enlace_Alt_Img', 'Enlace_Ugoira',
    'Enlace_Reading_works_or_Show_all', 'Enlace_Tags_Completo', 'Enlace_Tag_01',
    'Enlace_Tag_02', 'Enlace_Tag_03', 'Enlace_Fecha_Formato_01', 'Enlace_Fecha_Formato_02',
    'Estado',
];

// Aceptar tanto {orden, fields:{...}} como {orden, ...campos}
$fields = is_array($data['fields'] ?? null) ? $data['fields'] : $data;

$set    = [];
$params = [':orden' => (string)$orden];

foreach ($editable as $col) {
    if (!array_key_exists($col, $fields)) continue;
    $val = $fields[$col];
    if (is_array($val) || is_object($val)) continue;

    // Estado: normalizar a 0/1
    if ($col === 'Estado') {
        if (!in_array((string)$val, ['0', '1'], true)) {
            json_error('Estado debe ser 0 o 1', 400);
        }
        $set[]            = "`$col` = :estado";
        $params[':estado'] = (string)$val;
        continue;
    }

    $key          = ':' . $col;
    $set[]        = "`$col` = $key";
    $params[$key] = $val === null ? null : (string)$val;
}

if (empty($set)) {
    json_error('No hay campos válidos para actualizar', 400);
}

$sql = 'UPDATE ilustraciones SET ' . implode(', ', $set) . ' WHERE ORDEN = :orden';
$stmt = getPDO()->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, $v === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
}
$stmt->execute();

json_response([
    'success' => true,
    'updated' => $stmt->rowCount(),
]);