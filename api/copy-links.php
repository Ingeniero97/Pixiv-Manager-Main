<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

require_method('POST');
$data = read_json_body();

$ids = parse_ids_array($data['ids'] ?? []);
if (empty($ids)) {
    json_error('Se requiere al menos un ID', 400);
}

// Whitelist de columnas de las que se permite extraer enlaces
$allowedFields = [
    'Contenedor_Enlace_Album_href',
    'Contenedor_Enlace_Album',
    'Enlace_Img_Original',
    'Contenedor_Enlace_Autor_Album',
];
$field = (string)($data['field'] ?? 'Contenedor_Enlace_Album_href');
if (!in_array($field, $allowedFields, true)) {
    json_error('Campo no permitido', 400);
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$sql = "SELECT `ORDEN`, `$field` AS link
        FROM ilustraciones
        WHERE ORDEN IN ($placeholders)
        ORDER BY ORDEN DESC";

$stmt = getPDO()->prepare($sql);
foreach ($ids as $i => $id) {
    $stmt->bindValue($i + 1, (string)$id, PDO::PARAM_STR);
}
$stmt->execute();
$rows = $stmt->fetchAll();

$links = [];
foreach ($rows as $r) {
    $v = trim((string)($r['link'] ?? ''));
    if ($v !== '') {
        $links[] = $v;
    }
}

json_response(['success' => true, 'links' => $links]);