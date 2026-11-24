<?php
declare(strict_types=1);

/**
 * bulk-update-autores.php
 * Reemplaza Contenedor_Autor_Album en TODAS las filas de un enlace de autor.
 *
 * Modos:
 *   - dry_run=true  → valida y cuenta sin tocar la BD (para la vista previa)
 *   - dry_run=false → aplica UPDATE masivo en transacción por chunks
 *
 * Reglas:
 *   - Una fila por cada `enlace` presente en el archivo (dedupe por enlace, gana la última).
 *   - enlace debe ser URL válida (http/https) y existir en la BD.
 *   - nombre no puede estar vacío ni superar 500 chars.
 */

require_once __DIR__ . '/_helpers.php';

require_method('POST');
$data = read_json_body();

$pairs = $data['pairs'] ?? [];
if (!is_array($pairs) || empty($pairs)) {
    json_error('Se requiere un array no vacío de pares {enlace, nombre}', 400);
}

if (count($pairs) > 10000) {
    json_error('Demasiadas filas (máx 10,000)', 400);
}

$dryRun = !empty($data['dry_run']);

// ---------- 1) Sanitizar + deduplicar ----------
$clean = [];
$invalidos = [];

foreach ($pairs as $idx => $p) {
    $enlace = trim((string)($p['enlace'] ?? ''));
    $nombre = trim((string)($p['nombre'] ?? ''));
    $fila   = $idx + 1;

    if ($enlace === '' || $nombre === '') {
        $invalidos[] = [
            'fila'    => $fila,
            'enlace'  => $enlace,
            'nombre'  => $nombre,
            'motivo'  => 'Enlace o nombre vacío',
        ];
        continue;
    }

    if (!preg_match('#^https?://#i', $enlace)) {
        $invalidos[] = [
            'fila'   => $fila,
            'enlace' => $enlace,
            'nombre' => $nombre,
            'motivo' => 'Enlace no es URL válida',
        ];
        continue;
    }

    if (mb_strlen($nombre, 'UTF-8') > 500) {
        $invalidos[] = [
            'fila'   => $fila,
            'enlace' => $enlace,
            'nombre' => $nombre,
            'motivo' => 'Nombre demasiado largo (>500)',
        ];
        continue;
    }

    // Dedupe: si el enlace ya existe, se sobreescribe (gana la última fila)
    $clean[$enlace] = [
        'fila'   => $fila,
        'enlace' => $enlace,
        'nombre' => $nombre,
    ];
}

$validPairs = array_values($clean);

if (empty($validPairs)) {
    json_response([
        'success'  => true,
        'dry_run'  => $dryRun,
        'total'    => count($pairs),
        'invalidos'=> count($invalidos),
        'detalles' => ['invalidos' => $invalidos],
        'message'  => 'No hay pares válidos para procesar.',
    ]);
}

// ---------- 2) Buscar nombres actuales en la BD ----------
$pdo = getPDO();

function fetchCurrentNames(PDO $pdo, array $enlaces): array
{
    $result = [];
    foreach (array_chunk($enlaces, 1000) as $chunk) {
        $ph = implode(',', array_fill(0, count($chunk), '?'));
        $sql = "SELECT Contenedor_Enlace_Autor_Album AS enlace,
                       GROUP_CONCAT(DISTINCT Contenedor_Autor_Album SEPARATOR '|||') AS nombres
                FROM ilustraciones
                WHERE Contenedor_Enlace_Autor_Album IN ($ph)
                GROUP BY Contenedor_Enlace_Autor_Album";
        $stmt = $pdo->prepare($sql);
        foreach ($chunk as $i => $e) {
            $stmt->bindValue($i + 1, $e, PDO::PARAM_STR);
        }
        $stmt->execute();
        foreach ($stmt->fetchAll() as $row) {
            $result[(string)$row['enlace']] = (string)$row['nombres'];
        }
    }
    return $result;
}

$currentMap = fetchCurrentNames($pdo, array_column($validPairs, 'enlace'));

// ---------- 3) Clasificar cada par ----------
$rows = [];
$countChanged = 0;
$countSame    = 0;
$countMissing = 0;

foreach ($validPairs as $p) {
    $enlace = $p['enlace'];
    $nuevo  = $p['nombre'];
    $fila   = $p['fila'];

    if (!isset($currentMap[$enlace])) {
        $countMissing++;
        $rows[] = [
            'fila'          => $fila,
            'enlace'        => $enlace,
            'nombre_nuevo'  => $nuevo,
            'nombres_actuales' => [],
            'status'        => 'no_encontrado',
            'motivo'        => 'Enlace no existe en la base de datos',
        ];
        continue;
    }

    $nombresActuales = array_values(array_filter(
        explode('|||', $currentMap[$enlace]),
        static fn($s) => $s !== ''
    ));

    if (in_array($nuevo, $nombresActuales, true) && count($nombresActuales) === 1) {
        $countSame++;
        $rows[] = [
            'fila'          => $fila,
            'enlace'        => $enlace,
            'nombre_nuevo'  => $nuevo,
            'nombres_actuales' => $nombresActuales,
            'status'        => 'sin_cambios',
            'motivo'        => 'El nombre ya es exactamente el mismo',
        ];
    } else {
        $countChanged++;
        $rows[] = [
            'fila'          => $fila,
            'enlace'        => $enlace,
            'nombre_nuevo'  => $nuevo,
            'nombres_actuales' => $nombresActuales,
            'status'        => 'cambiado',
            'motivo'        => null,
        ];
    }
}

// ---------- 4) Si es dry_run, devolver reporte ----------
if ($dryRun) {
    json_response([
        'success' => true,
        'dry_run' => true,
        'total'   => count($pairs),
        'encontrados'     => $countChanged + $countSame,
        'cambiados'       => $countChanged,
        'sin_cambios'     => $countSame,
        'no_encontrados'  => $countMissing,
        'invalidos'       => count($invalidos),
        'detalles'        => [
            'rows'     => $rows,
            'invalidos'=> $invalidos,
        ],
    ]);
}

// ---------- 5) UPDATE real (por chunks, dentro de transacción) ----------
if ($countChanged === 0) {
    json_response([
        'success' => true,
        'dry_run' => false,
        'total'   => count($pairs),
        'cambiados' => 0,
        'sin_cambios' => $countSame,
        'no_encontrados' => $countMissing,
        'invalidos' => count($invalidos),
        'message' => 'No hay cambios que aplicar.',
    ]);
}

// Solo actualizamos los que sí cambian
$toUpdate = array_values(array_filter(
    $rows,
    static fn($r) => $r['status'] === 'cambiado'
));

try {
    $pdo->beginTransaction();
    $affected = 0;

    foreach (array_chunk($toUpdate, 500) as $chunk) {
        $cases        = [];
        $caseBindings = [];   // enlace, nombre, enlace, nombre, ...
        $inEnlaces    = [];

        foreach ($chunk as $r) {
            $cases[]        = 'WHEN ? THEN ?';
            $caseBindings[] = $r['enlace'];
            $caseBindings[] = $r['nombre_nuevo'];
            $inEnlaces[]    = $r['enlace'];
        }

        $inPh = implode(',', array_fill(0, count($inEnlaces), '?'));

        $sql = "UPDATE ilustraciones
                SET Contenedor_Autor_Album = CASE Contenedor_Enlace_Autor_Album
                    " . implode(' ', $cases) . "
                    ELSE Contenedor_Autor_Album
                END
                WHERE Contenedor_Enlace_Autor_Album IN ($inPh)";

        $stmt = $pdo->prepare($sql);
        $i = 1;
        foreach ($caseBindings as $v) {
            $stmt->bindValue($i++, $v, PDO::PARAM_STR);
        }
        foreach ($inEnlaces as $v) {
            $stmt->bindValue($i++, $v, PDO::PARAM_STR);
        }
        $stmt->execute();
        $affected += $stmt->rowCount();
    }

    $pdo->commit();

    json_response([
        'success'         => true,
        'dry_run'         => false,
        'total'           => count($pairs),
        'cambiados'       => $affected,
        'sin_cambios'     => $countSame,
        'no_encontrados'  => $countMissing,
        'invalidos'       => count($invalidos),
        'detalles'        => [
            'invalidos'   => $invalidos,
            'no_encontrados' => array_values(array_filter(
                $rows,
                static fn($r) => $r['status'] === 'no_encontrado'
            )),
        ],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[bulk-update-autores] ' . $e->getMessage());
    json_error('Error al aplicar los cambios. Se revirtió todo.', 500);
}