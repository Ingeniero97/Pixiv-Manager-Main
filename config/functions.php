<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

/**
 * Columnas incluidas en la búsqueda principal (21 columnas).
 * NOTA: excluye ORDEN (filtro propio) y Enlace_Fecha_Formato_01 (redundante).
 */
const SEARCH_COLUMNS = [
    'web_scraper_order', 'web_scraper_start_url', 'Contenedor_Tipo',
    'Contenedor_Numero_Hoja', 'Contenedor_Alt_Album', 'Contenedor_Autor_Album',
    'Contenedor_Enlace_Autor_Album', 'Contenedor_Nombre_Album',
    'Contenedor_Enlace_Album', 'Contenedor_Enlace_Album_href',
    'Contenedor_ID_Enlace', 'Enlace_Titulo', 'Enlace_Img_Original',
    'Enlace_Alt_Img', 'Enlace_Ugoira', 'Enlace_Reading_works_or_Show_all',
    'Enlace_Tags_Completo', 'Enlace_Tag_01', 'Enlace_Tag_02',
    'Enlace_Tag_03', 'Enlace_Fecha_Formato_02',
];

/**
 * Agrupación visual de SEARCH_COLUMNS para el <select> de "columna de búsqueda".
 * Debe contener EXACTAMENTE las mismas 21 columnas que SEARCH_COLUMNS (solo cambia el orden).
 */
const SEARCH_COLUMN_GROUPS = [
    'Scraper' => [
        'web_scraper_order',
        'web_scraper_start_url',
    ],
    'Contenedor' => [
        'Contenedor_Tipo',
        'Contenedor_Numero_Hoja',
        'Contenedor_Alt_Album',
        'Contenedor_Autor_Album',
        'Contenedor_Enlace_Autor_Album',
        'Contenedor_Nombre_Album',
        'Contenedor_Enlace_Album',
        'Contenedor_Enlace_Album_href',
        'Contenedor_ID_Enlace',
    ],
    'Enlace' => [
        'Enlace_Titulo',
        'Enlace_Img_Original',
        'Enlace_Alt_Img',
        'Enlace_Ugoira',
        'Enlace_Reading_works_or_Show_all',
        'Enlace_Tags_Completo',
        'Enlace_Tag_01',
        'Enlace_Tag_02',
        'Enlace_Tag_03',
        'Enlace_Fecha_Formato_02',
    ],
];

/**
 * Mapeo de chk-valores UI → (columna, valor en BD).
 * 'NO_UGOIRA' NO existe en Enlace_Ugoira (solo en Reading_works),
 * se filtra por esa otra columna.
 */
const UGOIRA_FILTER_MAP = [
    'UGOIRA'           => ['Enlace_Ugoira', 'UGOIRA'],
    'NO_SHOW_ALL'      => ['Enlace_Ugoira', 'NO SHOW ALL OR READING WORKS'],
    'SOLO_UNA_IMAGEN'  => ['Enlace_Ugoira', 'SOLO UNA IMAGEN'],
    'NO_UGOIRA'        => ['Enlace_Reading_works_or_Show_all', 'NO UGOIRA'],
];

/**
 * Escapa texto para HTML respetando UTF-8.
 */
function e(?string $text): string
{
    return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Trunca texto de forma segura (multibyte) y sin saltos de línea.
 * Usado para títulos/autores largos en la tabla.
 */
function safe_truncate(?string $text, int $limit = 50, string $suffix = '...'): string
{
    if ($text === null || $text === '') {
        return '';
    }
    // Colapsar cualquier whitespace (incluye \n, \r, \t) en un espacio simple.
    $clean = preg_replace('/\s+/u', ' ', $text) ?? $text;
    if (mb_strlen($clean, 'UTF-8') <= $limit) {
        return $clean;
    }
    return mb_substr($clean, 0, $limit, 'UTF-8') . $suffix;
}

/**
 * Construye WHERE + params para la consulta de ilustraciones.
 *
 * @param string $primarySearch    Búsqueda principal (recarga de página)
 * @param array  $filters          ['estado' => int|null, 'ugoira' => string[]]
 * @param string $secondarySearch  Búsqueda AJAX (se concatena con AND)
 * @return array [string $whereSql, array $params]
 */
function buildWhereClause(string $primarySearch, array $filters, string $secondarySearch = ''): array
{
    $where  = [];
    $params = [];

    // --- Estado (1 = activos, 0 = papelera). Opcional si es null. ---
    if (isset($filters['estado']) && $filters['estado'] !== null) {
        $where[] = '`Estado` = :estado';
        $params[':estado'] = (int)$filters['estado'];
    }

    // --- Filtros Ugoira (OR entre los valores seleccionados) ---
    $ugoira = $filters['ugoira'] ?? [];
    if (is_array($ugoira) && !empty($ugoira)) {
        $orParts = [];
        foreach (array_values($ugoira) as $i => $chkVal) {
            if (!isset(UGOIRA_FILTER_MAP[$chkVal])) {
                continue;
            }
            [$col, $val] = UGOIRA_FILTER_MAP[$chkVal];
            $key = ':ug' . $i;
            $orParts[] = "`$col` = $key";
            $params[$key] = $val;
        }
        if (!empty($orParts)) {
            $where[] = '(' . implode(' OR ', $orParts) . ')';
        }
    }

    // --- Búsqueda principal (LIKE, en 1 columna o en las 21) ---
    if ($primarySearch !== '') {
        $primaryCol = $filters['primary_col'] ?? null;
        $colsToSearch = ($primaryCol && in_array($primaryCol, SEARCH_COLUMNS, true))
            ? [$primaryCol]
            : SEARCH_COLUMNS;

        $orParts = [];
        foreach ($colsToSearch as $i => $col) {
            $key = ':ps' . $i;
            $orParts[] = "`$col` LIKE $key";
            $params[$key] = '%' . $primarySearch . '%';
        }
        $where[] = '(' . implode(' OR ', $orParts) . ')';
    }

    // --- Búsqueda secundaria (AND con todo lo anterior) ---
    if ($secondarySearch !== '') {
        $secondaryCol = $filters['secondary_col'] ?? null;
        $colsToSearch = ($secondaryCol && in_array($secondaryCol, SEARCH_COLUMNS, true))
            ? [$secondaryCol]
            : SEARCH_COLUMNS;

        $orParts = [];
        foreach ($colsToSearch as $i => $col) {
            $key = ':ss' . $i;
            $orParts[] = "`$col` LIKE $key";
            $params[$key] = '%' . $secondarySearch . '%';
        }
        $where[] = '(' . implode(' OR ', $orParts) . ')';
    }

    $whereSql = empty($where) ? '1=1' : implode(' AND ', $where);
    return [$whereSql, $params];
}

/**
 * Devuelve una página de ilustraciones aplicando filtros y búsqueda.
 *
 * @return array{data: array, total: int, total_pages: int, page: int, per_page: int}
 */
function getIllustrations(
    int $page,
    int $perPage,
    string $primarySearch,
    array $filters,
    string $secondarySearch = ''
): array {
    $page    = max(1, $page);
    $perPage = max(1, min($perPage, 10000));

    [$whereSql, $params] = buildWhereClause($primarySearch, $filters, $secondarySearch);

    $pdo = getPDO();

    // Total
    $countSql = "SELECT COUNT(*) FROM `ilustraciones` WHERE $whereSql";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    // Página
    $sql = "SELECT * FROM `ilustraciones`
            WHERE $whereSql
            ORDER BY `ORDEN` DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();

    return [
        'data'        => $stmt->fetchAll(),
        'total'       => $total,
        'total_pages' => $totalPages,
        'page'        => $page,
        'per_page'    => $perPage,
    ];
}

/**
 * Estadísticas globales para los 3 badges.
 *
 * BUG FIX: ahora aplica también los filtros Ugoira (antes no).
 * El badge "Total" refleja el conjunto FILTRADO (misma semántica que el buscador).
 *
 * @return array{total:int, activos:int, inactivos:int}
 */
function getStatistics(string $primarySearch, array $filters, string $secondarySearch = ''): array
{
    // Quitamos el filtro de estado: los 3 badges necesitan ver ambos estados.
    $statsFilters = $filters;
    unset($statsFilters['estado']);

    [$whereSql, $params] = buildWhereClause($primarySearch, $statsFilters, $secondarySearch);

    $sql = "SELECT
                COUNT(*)                                        AS total,
                SUM(CASE WHEN `Estado` = 1 THEN 1 ELSE 0 END)   AS activos,
                SUM(CASE WHEN `Estado` = 0 THEN 1 ELSE 0 END)   AS inactivos
            FROM `ilustraciones`
            WHERE $whereSql";

    $stmt = getPDO()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch() ?: ['total' => 0, 'activos' => 0, 'inactivos' => 0];

    return [
        'total'     => (int)($row['total']     ?? 0),
        'activos'   => (int)($row['activos']   ?? 0),
        'inactivos' => (int)($row['inactivos'] ?? 0),
    ];
}

/**
 * Helper para paginación HTML: genera el array de páginas a mostrar
 * con elipsis (…). Lo usan header.php, tabla.php y restauracion.php.
 */
function buildPaginationRange(int $currentPage, int $totalPages, int $delta = 2): array
{
    if ($totalPages <= 1) {
        return [['page' => 1, 'active' => true, 'disabled' => false]];
    }

    $set = [1, $totalPages, $currentPage];
    for ($i = $currentPage - $delta; $i <= $currentPage + $delta; $i++) {
        if ($i >= 1 && $i <= $totalPages) {
            $set[] = $i;
        }
    }
    $set = array_values(array_unique($set));
    sort($set);

    $out  = [];
    $prev = 0;
    foreach ($set as $p) {
        if ($prev && ($p - $prev) > 1) {
            $out[] = ['dots' => true];
        }
        $out[] = ['page' => $p];
        $prev = $p;
    }
    return $out;
}

/**
 * Renderiza un <select> con las 21 columnas agrupadas por categoría.
 *
 * @param string|null $selected   Columna a preseleccionar (null/'' = "Todas las columnas")
 * @param string      $selectId   id del <select>
 * @param string      $nameAttr   valor del name ('' = sin name; el principal usa 'col')
 * @param string      $extraClass clases CSS adicionales
 */
function renderSearchColumnSelect(
    ?string $selected,
    string $selectId,
    string $nameAttr = '',
    string $extraClass = ''
): string {
    $selected = (string)($selected ?? '');
    $nameAttrHtml = $nameAttr !== '' ? ' name="' . e($nameAttr) . '"' : '';

    $html  = '<select id="' . e($selectId) . '"' . $nameAttrHtml
           . ' class="form-select form-select-sm search-col-select ' . e($extraClass) . '"'
           . ' title="Columna donde buscar">';
    $html .= '<option value="">Todas las columnas</option>';

    foreach (SEARCH_COLUMN_GROUPS as $group => $cols) {
        $html .= '<optgroup label="' . e($group) . '">';
        foreach ($cols as $col) {
            $sel = ($col === $selected) ? ' selected' : '';
            $html .= '<option value="' . e($col) . '"' . $sel . '>' . e($col) . '</option>';
        }
        $html .= '</optgroup>';
    }
    $html .= '</select>';
    return $html;
}