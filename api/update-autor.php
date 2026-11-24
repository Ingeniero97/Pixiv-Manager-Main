<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

require_method('POST');
$data = read_json_body();

$enlace_original = trim((string)($data['enlace_original'] ?? ''));
$nombre_nuevo    = trim((string)($data['nombre_nuevo']    ?? ''));
$enlace_nuevo    = trim((string)($data['enlace_nuevo']    ?? ''));

if ($enlace_original === '') {
    json_error('Enlace original requerido', 400);
}
if ($nombre_nuevo === '' && $enlace_nuevo === '') {
    json_error('Debes cambiar el nombre o el enlace del autor', 400);
}
if ($enlace_nuevo !== '' && $enlace_nuevo === $enlace_original) {
    json_error('El nuevo enlace es igual al actual', 400);
}

/**
 * BUG FIX
 * --------
 * Antes, "mismo enlace" se hardcodeaba a 0 → la UI mostraba "0 mismo enlace"
 * incluso cuando NO se cambiaba el enlace (debería mostrar el total del grupo).
 *
 * Ahora se calcula ANTES del UPDATE, desglosado por estado (buscador / restauración):
 *
 *   - autor_actualizado  = registros cuyo nombre va a cambiar
 *   - autor_mismo        = registros que ya tenían el nombre nuevo (o no se cambia nombre)
 *   - enlace_actualizado = registros cuyo enlace va a cambiar (0 si no se cambia)
 *   - enlace_mismo       = registros que conservan su enlace (todos si no se cambia)
 */
function contarGrupo(
    PDO $pdo,
    string $enlaceOrig,
    string $nombreNuevo,
    string $enlaceNuevo,
    int $estado
): array {
    // Total del grupo
    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM ilustraciones
        WHERE Contenedor_Enlace_Autor_Album = :e AND Estado = :s
    ');
    $stmt->bindValue(':e', $enlaceOrig, PDO::PARAM_STR);
    $stmt->bindValue(':s', $estado,     PDO::PARAM_INT);
    $stmt->execute();
    $total = (int)$stmt->fetchColumn();

    // Autor mismo: cuántos YA tienen el nombre nuevo (no necesitan UPDATE real)
    if ($nombreNuevo !== '') {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) FROM ilustraciones
            WHERE Contenedor_Enlace_Autor_Album = :e
              AND Estado = :s
              AND Contenedor_Autor_Album = :n
        ');
        $stmt->bindValue(':e', $enlaceOrig,  PDO::PARAM_STR);
        $stmt->bindValue(':s', $estado,      PDO::PARAM_INT);
        $stmt->bindValue(':n', $nombreNuevo, PDO::PARAM_STR);
        $stmt->execute();
        $autorMismo = (int)$stmt->fetchColumn();
    } else {
        $autorMismo = $total; // no se cambia nombre → todos son "mismo autor"
    }

    // Enlace mismo: si NO se cambia enlace → todos conservan el actual.
    // Si SÍ se cambia → ninguno de este grupo tiene ya el nuevo (se buscó por el viejo).
    $enlaceMismo = ($enlaceNuevo === '') ? $total : 0;

    return [
        'autor_actualizado'  => $total - $autorMismo,
        'autor_mismo'        => $autorMismo,
        'enlace_actualizado' => $total - $enlaceMismo,
        'enlace_mismo'       => $enlaceMismo,
    ];
}

$pdo = getPDO();

// Contar ANTES de cualquier UPDATE
$countsBuscador     = contarGrupo($pdo, $enlace_original, $nombre_nuevo, $enlace_nuevo, 1);
$countsRestauracion = contarGrupo($pdo, $enlace_original, $nombre_nuevo, $enlace_nuevo, 0);

// UPDATE dinámico (solo las columnas que cambian)
$set    = [];
$params = [':enlace_original' => $enlace_original];

if ($nombre_nuevo !== '') {
    $set[]                    = '`Contenedor_Autor_Album` = :nombre_nuevo';
    $params[':nombre_nuevo']  = $nombre_nuevo;
}
if ($enlace_nuevo !== '') {
    $set[]                    = '`Contenedor_Enlace_Autor_Album` = :enlace_nuevo';
    $params[':enlace_nuevo']  = $enlace_nuevo;
}

$sql = 'UPDATE ilustraciones SET ' . implode(', ', $set)
     . ' WHERE Contenedor_Enlace_Autor_Album = :enlace_original';

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->execute();

json_response([
    'success' => true,
    'message' => 'Autor actualizado',
    'detalles' => [
        'buscador'     => $countsBuscador,
        'restauracion' => $countsRestauracion,
    ],
]);