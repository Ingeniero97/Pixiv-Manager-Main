<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

require_method('POST');
$data = read_json_body();

$ids = parse_ids_array($data['ids'] ?? []);
if (empty($ids)) {
    json_error('Se requiere al menos un ID', 400);
}

$estadoRaw = $data['estado'] ?? null;
if ($estadoRaw === null || !in_array((string)$estadoRaw, ['0', '1'], true)) {
    json_error('Campo "estado" inválido (debe ser 0 o 1)', 400);
}
$estado = (int)$estadoRaw;

$allowedFields = ['Contenedor_ID_Enlace', 'Contenedor_Enlace_Album_href'];
$field = (string)($data['field'] ?? 'ORDEN');
if (!in_array($field, $allowedFields, true)) {
    json_error('Campo de referencia no permitido', 400);
}

$pdo = getPDO();
$ph  = implode(',', array_fill(0, count($ids), '?'));

// --- 1) ¿Cuántos registros coinciden con esos valores? ---
$stmt = $pdo->prepare("SELECT COUNT(*) FROM ilustraciones WHERE `$field` IN ($ph)");
foreach ($ids as $i => $id) {
    $stmt->bindValue($i + 1, (string)$id, PDO::PARAM_STR);
}
$stmt->execute();
$matched = (int)$stmt->fetchColumn();

// Cortocircuito: nada que actualizar
if ($matched === 0) {
    json_response([
        'success' => true,
        'matched' => 0,
        'changed' => 0,
        'estado'  => $estado,
        'message' => 'No se encontró ningún registro con esos valores en el campo indicado.',
    ]);
}

// --- 2) Ejecutar UPDATE ---
$sql  = "UPDATE ilustraciones SET Estado = ? WHERE `$field` IN ($ph)";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(1, $estado, PDO::PARAM_INT);
foreach ($ids as $i => $id) {
    $stmt->bindValue($i + 2, (string)$id, PDO::PARAM_STR);
}
$stmt->execute();
$changed = $stmt->rowCount();  // filas cuyo Estado cambió realmente

json_response([
    'success' => true,
    'matched' => $matched,
    'changed' => $changed,
    'estado'  => $estado,
]);