<?php
/** @var array $view_data */
$mode           = $view_data['mode'] ?? 'index';
$isRest         = $mode === 'restauracion';
$items_per_page = (int)$view_data['items_per_page'];
$page           = (int)$view_data['page'];
$search         = (string)$view_data['search'];
$ugoira_filters = (array)$view_data['ugoira_filters'];
$illustrations  = (array)$view_data['illustrations'];
$total_items    = (int)$view_data['total_items'];
$total_pages    = (int)$view_data['total_pages'];
$per_page_options = [50, 100, 200, 500, 1000, 2000, 3000, 4000, 5000, 10000];

// --- Variaciones index vs restauracion ---
$actionClass  = $isRest ? 'activate-item' : 'deactivate-item';
$actionIcon   = $isRest ? 'fa-check'      : 'fa-trash';
$actionTitle  = $isRest ? 'Activar (Restaurar)' : 'Desactivar (Eliminar)';
$actionColor  = $isRest ? 'btn-success'   : 'btn-danger';
$bulkLabel    = $isRest ? 'Restaurar'     : 'Eliminar';
$bulkIcon     = $isRest ? 'fa-trash-restore' : 'fa-times-circle';
$bulkColor    = $isRest ? 'btn-success'   : 'btn-danger';
$bulkIdsLabel = $isRest ? 'Restaurar por IDs' : 'Eliminar por IDs';
$bulkIdsIcon  = $isRest ? 'fa-trash-restore'  : 'fa-skull-crossbones';

// --- Base de URL para paginación (sin page) ---
$base_params = $_GET;
unset($base_params['page']);

$ugoiraLabels = [
    'UGOIRA'          => 'UGOIRA',
    'NO_SHOW_ALL'     => 'NO SHOW ALL',
    'SOLO_UNA_IMAGEN' => 'SOLO IMAGEN',
];

$hasActiveFilter = ($search !== '' || !empty($ugoira_filters));
$paginationInfoText = 'Página ' . $page . ' de ' . $total_pages . ' • '
    . number_format($total_items)
    . ($hasActiveFilter ? ' resultados filtrados' : ' registros en total');

// --- Closure para renderizar paginación (idéntica arriba/abajo) ---
$renderPagination = function (int $cur, int $total, array $baseParams): string {
    if ($total <= 1) {
        return '<li class="page-item disabled"><span class="page-link"><i class="fas fa-angle-double-left"></i></span></li>'
             . '<li class="page-item disabled"><span class="page-link"><i class="fas fa-angle-left"></i></span></li>'
             . '<li class="page-item active"><span class="page-link">1</span></li>'
             . '<li class="page-item disabled"><span class="page-link"><i class="fas fa-angle-right"></i></span></li>'
             . '<li class="page-item disabled"><span class="page-link"><i class="fas fa-angle-double-right"></i></span></li>';
    }
    $mk = function (int $n, string $label, bool $disabled = false, bool $dots = false, bool $active = false) use ($baseParams): string {
        if ($dots) {
            return '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        if ($disabled) {
            return '<li class="page-item disabled"><span class="page-link">' . $label . '</span></li>';
        }
        $p = $baseParams; $p['page'] = $n;
        return '<li class="page-item' . ($active ? ' active' : '') . '">'
             . '<a class="page-link" href="?' . e(http_build_query($p)) . '">' . $label . '</a></li>';
    };

    $html = '';
    $html .= $cur > 1
        ? $mk(1, '<i class="fas fa-angle-double-left"></i>')
        : $mk(1, '<i class="fas fa-angle-double-left"></i>', true);
    $html .= $cur > 1
        ? $mk($cur - 1, '<i class="fas fa-angle-left"></i>')
        : $mk(1, '<i class="fas fa-angle-left"></i>', true);

    $range = buildPaginationRange($cur, $total);
    foreach ($range as $item) {
        if (!empty($item['dots'])) {
            $html .= $mk(0, '…', false, true);
        } else {
            $p = (int)$item['page'];
            $html .= $mk($p, (string)$p, false, false, $p === $cur);
        }
    }

    $html .= $cur < $total
        ? $mk($cur + 1, '<i class="fas fa-angle-right"></i>')
        : $mk($total, '<i class="fas fa-angle-right"></i>', true);
    $html .= $cur < $total
        ? $mk($total, '<i class="fas fa-angle-double-right"></i>')
        : $mk($total, '<i class="fas fa-angle-double-right"></i>', true);
    return $html;
};
?>

<!-- ============================================================ -->
<!-- C. PANEL DE BÚSQUEDA PRINCIPAL                                -->
<!-- ============================================================ -->
<div class="search-section">
    <form method="GET" action="" id="searchForm">
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="input-group">
                    <span class="input-group-text bg-primary text-white"><i class="fas fa-search"></i></span>
                    <?= renderSearchColumnSelect($view_data['primary_col'] ?? '', 'primaryColSelect', 'col') ?>
                    <input type="text" name="search" class="form-control"
                        placeholder="Buscar en todas las columnas..."
                        value="<?= e($search) ?>" autocomplete="off">
                    <?php if ($search !== ''): ?>
                        <button type="button" class="btn btn-outline-danger" id="clearSearchBtn" title="Limpiar búsqueda">
                            <i class="fas fa-times"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="d-flex flex-wrap gap-2 justify-content-end align-items-center">
                    <div class="dropdown" id="filtrosUgoiraDropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                type="button" id="filtrosUgoiraBtn"
                                data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                aria-expanded="false">
                            <i class="fas fa-filter me-1"></i>
                            Filtros <span id="filtrosUgoiraCount" class="badge bg-primary ms-1"><?= count($ugoira_filters) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end p-2" style="min-width: 260px;">
                            <li class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input filtro-ugoira-checkbox" type="checkbox"
                                           value="UGOIRA" id="filtroUgoiraUGOIRA"
                                           <?= in_array('UGOIRA', $ugoira_filters, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="filtroUgoiraUGOIRA">
                                        <i class="fas fa-play text-info me-1"></i> UGOIRA
                                    </label>
                                </div>
                            </li>
                            <li class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input filtro-ugoira-checkbox" type="checkbox"
                                           value="NO_SHOW_ALL" id="filtroUgoiraNoShowAll"
                                           <?= in_array('NO_SHOW_ALL', $ugoira_filters, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="filtroUgoiraNoShowAll">
                                        <i class="fas fa-book text-warning me-1"></i> NO SHOW ALL
                                    </label>
                                </div>
                            </li>
                            <li class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input filtro-ugoira-checkbox" type="checkbox"
                                           value="SOLO_UNA_IMAGEN" id="filtroUgoiraSoloUnaImagen"
                                           <?= in_array('SOLO_UNA_IMAGEN', $ugoira_filters, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="filtroUgoiraSoloUnaImagen">
                                        <i class="fas fa-image text-success me-1"></i> SOLO IMAGEN
                                    </label>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li class="d-flex justify-content-between">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="clearFiltrosUgoira">
                                    <i class="fas fa-eraser me-1"></i> Limpiar
                                </button>
                                <button type="button" class="btn btn-sm btn-primary" id="aplicarFiltrosUgoira">
                                    <i class="fas fa-check me-1"></i> Aplicar
                                </button>
                            </li>
                        </ul>
                    </div>
                    <a href="?" class="btn btn-sm btn-outline-danger" title="Quitar todos los filtros">
                        <i class="fas fa-redo me-1"></i> Restablecer
                    </a>
                </div>
            </div>
        </div>
        <input type="hidden" name="length" value="<?= $items_per_page ?>">
    </form>

    <?php
    // --- Píldoras de filtros activos ---
    $active_filters = [];
    foreach ($ugoira_filters as $chk) {
        if (isset($ugoiraLabels[$chk])) {
            $active_filters[] = [
                'name' => 'Filtro', 'value' => $ugoiraLabels[$chk],
                'param' => 'ugoira', 'raw' => $chk,
            ];
        }
    }
    if ($search !== '') {
        $active_filters[] = ['name' => 'Búsqueda', 'value' => $search, 'param' => 'search', 'raw' => $search];
    }
    ?>
    <?php if (!empty($active_filters)): ?>
        <div class="row mt-3">
            <div class="col-12">
                <div class="d-flex flex-wrap align-items-center">
                    <span class="me-2 text-muted">Filtros activos:</span>
                    <?php foreach ($active_filters as $f): ?>
                        <?php
                        $params = $_GET;
                        if ($f['param'] === 'ugoira') {
                            $remaining = array_values(array_filter($ugoira_filters, fn($v) => $v !== $f['raw']));
                            if (empty($remaining)) unset($params['ugoira']);
                            else $params['ugoira'] = implode(',', $remaining);
                        } else {
                            unset($params[$f['param']]);
                        }
                        $params['page'] = 1;
                        ?>
                        <span class="filter-active">
                            <?= e($f['name']) ?>: <strong><?= e($f['value']) ?></strong>
                            <a href="?<?= e(http_build_query($params)) ?>" class="filter-remove" title="Quitar filtro">
                                <i class="fas fa-times"></i>
                            </a>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- D. TOOLBAR 1 — SELECCIÓN                                      -->
<!-- ============================================================ -->
<div class="bg-light border-bottom py-2 px-3">
    <div class="row align-items-center">
        <div class="col-md-6">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <span>Mostrar:</span>
                <select class="form-select form-select-sm w-auto" id="itemsPerPageTop">
                    <?php foreach ($per_page_options as $opt): ?>
                        <option value="<?= $opt ?>" <?= $items_per_page === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
                <span>registros por página</span>
                <span class="ms-3 text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Mostrando <span id="toolbarShowingCount"><?= count($illustrations) ?></span>
                    de <span id="toolbarTotalCount"><?= number_format($total_items) ?></span> registros
                </span>
            </div>
        </div>
        <div class="col-md-6 text-end">
            <span id="badgeSeleccionados" class="badge rounded-pill bg-danger me-2"
                  style="display: none; font-size: 0.85rem; padding: 0.35rem 0.6rem; vertical-align: middle;">0</span>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary" id="selectAllButton">
                    <i class="fas fa-check-double me-1"></i> Seleccionar todos
                </button>
                <button class="btn btn-outline-secondary" id="deselectAllButton" disabled>
                    <i class="fas fa-times me-1"></i> Deseleccionar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- E. TOOLBAR 2 — ACCIONES MASIVAS + BÚSQUEDA SECUNDARIA         -->
<!-- ============================================================ -->
<div class="bg-light border-bottom py-2 px-3">
    <div class="row align-items-center">
        <div class="col-md-4">
                        <div class="btn-group btn-group-sm">
                <!-- Botones principales (Siempre visibles) -->
                <button class="btn btn-primary" id="copyLinksBtn" disabled title="Copiar enlaces seleccionados">
                    <i class="fas fa-copy me-1"></i> Copiar
                </button>
                <button class="btn <?= $bulkColor ?>" id="deactivateSelectedBtn" disabled title="<?= e($bulkLabel) ?> seleccionados">
                    <i class="fas <?= $bulkIcon ?> me-1"></i> <?= e($bulkLabel) ?>
                </button>
                
                <!-- Menú desplegable de Opciones Avanzadas -->
                <button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-cogs me-1"></i> Opciones
                </button>
                <ul class="dropdown-menu shadow-sm" style="min-width: 260px;">
                    
                    <!-- ═══ SECCIÓN: EXPORTAR A EXCEL ═══ -->
                    <li>
                        <h6 class="dropdown-header text-success fw-bold">
                            <i class="fas fa-file-excel me-1"></i> Exportar a Excel
                        </h6>
                    </li>
                    <li>
                        <a class="dropdown-item ps-4" href="#" id="exportAllBtn">
                            <i class="fas fa-list me-2 text-muted"></i> Exportar todos (con filtros)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item ps-4" href="#" id="exportSelectedBtn">
                            <i class="fas fa-check-square me-2 text-muted"></i> Exportar seleccionados
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item ps-4" href="#" id="configColumnsBtn">
                            <i class="fas fa-cog me-2 text-muted"></i> Configurar columnas
                        </a>
                    </li>
                    
                    <li><hr class="dropdown-divider"></li>
                    
                    <!-- ═══ SECCIÓN: DATOS ═══ -->
                    <li>
                        <h6 class="dropdown-header text-warning fw-bold">
                            <i class="fas fa-database me-1"></i> Datos
                        </h6>
                    </li>
                    <li>
                        <a class="dropdown-item ps-4" href="#" id="bulkRecordsBtn">
                            <i class="fas fa-file-import me-2 text-muted"></i> Remplazar en lote (Excel/CSV)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item ps-4" href="#" id="clearDataBtn">
                            <i class="fas fa-eraser me-2 text-muted"></i> Limpiar datos de columnas
                        </a>
                    </li>                    
                </ul>
            </div>
        </div>

        <div class="col-md-4">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-info text-white"><i class="fas fa-filter"></i></span>
                <?= renderSearchColumnSelect($view_data['secondary_col'] ?? '', 'secondaryColSelect') ?>
                <input type="text" class="form-control" id="clientSearch"
                    placeholder="Filtrar resultados actuales..." autocomplete="off">
                <button class="btn btn-outline-secondary" type="button" id="clearClientSearch">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="form-text text-center mt-1">
                <small id="clientSearchStats">
                    Mostrando <?= count($illustrations) ?> de <?= number_format($total_items) ?> registros
                </small>
            </div>
        </div>

        <div class="col-md-4 text-end">
            <button class="btn <?= $bulkColor ?> btn-sm" id="batchDeleteBtn"
                    data-bs-toggle="modal" data-bs-target="#batchDeleteModal">
                <i class="fas <?= $bulkIdsIcon ?> me-1"></i> <?= e($bulkIdsLabel) ?>
            </button>
            <button class="btn btn-success btn-sm rounded-circle ms-2" id="autoresBtn"
                    title="Ver autores"
                    style="width: 38px; height: 38px; position: relative;">
                <i class="fas fa-users"></i>
            </button>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- F. PAGINACIÓN SUPERIOR                                        -->
<!-- ============================================================ -->
<div class="pagination-top-container">
    <div class="row align-items-center">
        <div class="col-md-6">
            <small class="text-muted" id="paginationInfoTop">
                <i class="fas fa-list-ol me-1"></i><?= e($paginationInfoText) ?>
            </small>
        </div>
        <div class="col-md-6">
            <nav aria-label="Paginación superior" class="d-flex justify-content-end">
                <ul class="pagination pagination-sm mb-0" id="paginationTop">
                    <?= $renderPagination($page, $total_pages, $base_params) ?>
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- G. TABLA DE RESULTADOS                                        -->
<!-- ============================================================ -->
<div class="table-container">
    <div class="table-responsive">
        <table id="resultsTable" class="table table-hover table-striped table-bordered">
            <thead class="table-dark">
                <tr>
                    <th width="40"><input type="checkbox" class="form-check-input" id="checkAllHeader"></th>
                    <th width="70">ORDEN</th>
                    <th width="80">Tipo</th>
                    <th width="80">Hoja</th>
                    <th width="120">Estado</th>
                    <th>Autor</th>
                    <th>Título</th>
                    <th width="80">Imagen</th>
                    <th width="100">Ugoira</th>
                    <th width="150">Fecha</th>
                    <th width="180">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($illustrations)): ?>
                    <tr>
                        <td colspan="11" class="text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h4>No se encontraron resultados</h4>
                            <p class="text-muted"><?= $hasActiveFilter ? 'Prueba con otros términos de búsqueda' : 'La tabla está vacía' ?></p>
                            <?php if ($hasActiveFilter): ?>
                                <a href="?" class="btn btn-primary mt-2">
                                    <i class="fas fa-sync-alt me-1"></i> Quitar filtros
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($illustrations as $item): ?>
                        <?php
                        $orden    = (int)$item['ORDEN'];
                        $estado   = (int)$item['Estado'];
                        $titulo   = $item['Enlace_Titulo'] ?: 'Sin título';
                        $autor    = (string)($item['Contenedor_Autor_Album'] ?? '');
                        $album    = (string)($item['Contenedor_Nombre_Album'] ?? '');
                        $autorUrl = (string)($item['Contenedor_Enlace_Autor_Album'] ?? '');
                        $img      = (string)($item['Enlace_Img_Original'] ?? '');
                        $ugoira   = (string)($item['Enlace_Ugoira'] ?? '');
                        $fecha1   = (string)($item['Enlace_Fecha_Formato_01'] ?? '');
                        $fecha2   = (string)($item['Enlace_Fecha_Formato_02'] ?? '');
                        $linkHref = (string)($item['Contenedor_Enlace_Album_href'] ?? '');

                        $hasImg = $img !== '' && $img !== 'NO IMG-ORIGINAL POR UGOIRA';
                        ?>
                        <tr data-id="<?= $orden ?>" data-estado="<?= $estado ?>">
                            <td>
                                <input type="checkbox" class="form-check-input row-checkbox" value="<?= $orden ?>">
                            </td>
                            <td><strong><?= $orden ?></strong></td>
                            <td>
                                <?php if ($item['Contenedor_Tipo'] === 'R-18'): ?>
                                    <span class="badge bg-danger">R-18</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= e((string)($item['Contenedor_Tipo'] ?? '')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= e((string)($item['Contenedor_Numero_Hoja'] ?? '')) ?></td>
                            <td>
                                <?php if ($estado === 1): ?>
                                    <span class="badge badge-estado badge-activo">
                                        <i class="fas fa-check-circle me-1"></i> Activo
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-estado badge-inactivo">
                                        <i class="fas fa-times-circle me-1"></i> Inactivo
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($autor !== ''): ?>
                                    <div class="d-flex flex-column">
                                        <a href="<?= e($autorUrl !== '' ? $autorUrl : '#') ?>"
                                           target="_blank" rel="noopener noreferrer"
                                           class="text-decoration-none"
                                           title="<?= e($autor) ?>">
                                            <?= e(safe_truncate($autor, 30)) ?>
                                        </a>
                                        <?php if ($album !== ''): ?>
                                            <small class="text-muted" title="<?= e($album) ?>">
                                                <?= e(safe_truncate($album, 30)) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td title="<?= e($titulo) ?>">
                                <?= e(safe_truncate($titulo, 50)) ?>
                            </td>
                            <td class="text-center">
                                <?php if ($hasImg): ?>
                                    <a href="<?= e($img) ?>" target="_blank" rel="noopener noreferrer"
                                       class="btn btn-sm btn-outline-primary btn-action" title="Ver imagen">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Sin img</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($ugoira !== ''): ?>
                                    <span class="badge bg-info" title="<?= e($ugoira) ?>">
                                        <?= e(mb_substr($ugoira, 0, 20, 'UTF-8')) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted" title="<?= e($fecha1) ?>">
                                    <?= e($fecha2) ?>
                                </small>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-info btn-sm btn-action view-details"
                                            data-id="<?= $orden ?>" title="Ver detalles">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-warning btn-sm btn-action edit-item"
                                            data-id="<?= $orden ?>" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn <?= $actionColor ?> btn-sm btn-action <?= $actionClass ?>"
                                            data-id="<?= $orden ?>"
                                            data-title="<?= e($titulo) ?>"
                                            title="<?= e($actionTitle) ?>">
                                        <i class="fas <?= $actionIcon ?>"></i>
                                    </button>
                                    <button class="btn btn-primary btn-sm btn-action copy-link"
                                            data-link="<?= e($linkHref) ?>" title="Copiar enlace">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================ -->
<!-- F. PAGINACIÓN INFERIOR                                        -->
<!-- ============================================================ -->
<div class="pagination-container">
    <div class="row align-items-center">
        <div class="col-md-6">
            <div class="d-flex align-items-center">
                <span class="me-2">Mostrar:</span>
                <select class="form-select form-select-sm w-auto" id="itemsPerPageBottom">
                    <?php foreach ($per_page_options as $opt): ?>
                        <option value="<?= $opt ?>" <?= $items_per_page === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="ms-2">registros por página</span>
            </div>
        </div>
        <div class="col-md-6">
            <nav aria-label="Paginación inferior" class="d-flex justify-content-end">
                <ul class="pagination pagination-sm mb-0" id="paginationBottom">
                    <?= $renderPagination($page, $total_pages, $base_params) ?>
                </ul>
            </nav>
            <div class="text-end mt-1">
                <small class="text-muted" id="paginationInfoBottom"><?= e($paginationInfoText) ?></small>
            </div>
        </div>
    </div>
</div>