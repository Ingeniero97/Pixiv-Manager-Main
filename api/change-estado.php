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

$pdo = getPDO();
$ph  = implode(',', array_fill(0, count($ids), '?'));

// 1) matched
$stmt = $pdo->prepare("SELECT COUNT(*) FROM ilustraciones WHERE ORDEN IN ($ph)");
foreach ($ids as $i => $id) {
    $stmt->bindValue($i + 1, (string)$id, PDO::PARAM_STR);
}
$stmt->execute();
$matched = (int)$stmt->fetchColumn();

if ($matched === 0) {
    json_response([
        'success' => true, 'matched' => 0, 'changed' => 0, 'estado' => $estado,
        'message' => 'No se encontró ningún registro con esos ORDEN.',
    ]);
}

// 2) UPDATE
$stmt = $pdo->prepare("UPDATE ilustraciones SET Estado = ? WHERE ORDEN IN ($ph)");
$stmt->bindValue(1, $estado, PDO::PARAM_INT);
foreach ($ids as $i => $id) {
    $stmt->bindValue($i + 2, (string)$id, PDO::PARAM_STR);
}
$stmt->execute();
$changed = $stmt->rowCount();

json_response([
    'success' => true,
    'matched' => $matched,
    'changed' => $changed,
    'estado'  => $estado,
]);