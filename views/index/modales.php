<?php
/** @var array $view_data */
$mode   = $view_data['mode'] ?? 'index';
$isRest = $mode === 'restauracion';

// --- Labels parametrizados del modal "Batch por IDs" ---
$batchModalTitle  = $isRest ? 'Restaurar por IDs'         : 'Eliminar por IDs';
$batchModalIcon   = $isRest ? 'fa-trash-restore'          : 'fa-skull-crossbones';
$batchModalColor  = $isRest ? 'bg-success'                : 'bg-danger';
$batchModalBtnLabel = $isRest ? 'Restaurar Seleccionados' : 'Eliminar Seleccionados';
$batchModalBtnColor = $isRest ? 'btn-success'             : 'btn-danger';
$batchModalBtnIcon  = $isRest ? 'fa-trash-restore'        : 'fa-trash';
$batchModalWarning  = $isRest
    ? 'Esta acción cambiará el estado a <strong>activo (1)</strong> para los registros seleccionados.'
    : 'Esta acción cambiará el estado a <strong>inactivo (0)</strong> para los registros seleccionados.';
?>

<!-- ============================================================ -->
<!-- MODAL: VISTA DETALLADA                                        -->
<!-- ============================================================ -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Detalles Completos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-3">Cargando detalles...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: EDITAR REGISTRO -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <!-- ═══ AQUÍ ESTÁ EL CAMBIO: Cambiar modal-lg por modal-xl ═══ -->
    <div class="modal-dialog modal-xl"> 
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Registro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="editModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-warning" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-3">Cargando formulario de edición...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: BATCH POR IDs (Eliminar / Restaurar)                   -->
<!-- ============================================================ -->
<div class="modal fade" id="batchDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header <?= $batchModalColor ?> text-white">
                <h5 class="modal-title">
                    <i class="fas <?= $batchModalIcon ?> me-2"></i><?= e($batchModalTitle) ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="batchDeleteIds">IDs (uno por línea):</label>
                    <textarea class="form-control copy-area" id="batchDeleteIds"
                              placeholder="Ejemplo:&#10;130438216&#10;124419106&#10;127669142"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="batchDeleteField">Campo de referencia:</label>
                    <select class="form-select" id="batchDeleteField">
                        <option value="Contenedor_ID_Enlace">Contenedor_ID_Enlace</option>
                        <option value="Contenedor_Enlace_Album_href">Contenedor_Enlace_Album_href</option>
                    </select>
                </div>
                <div class="alert alert-<?= $isRest ? 'success' : 'danger' ?>">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= $batchModalWarning ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn <?= $batchModalBtnColor ?>" id="confirmBatchDelete">
                    <i class="fas <?= $batchModalBtnIcon ?> me-1"></i> <?= e($batchModalBtnLabel) ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: COPIAR ENLACES                                         -->
<!-- ============================================================ -->
<div class="modal fade" id="copyLinksModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-copy me-2"></i>Enlaces Copiados</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="linksToCopy">Enlaces listos para copiar:</label>
                    <textarea class="form-control copy-area" id="linksToCopy" readonly></textarea>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Copia estos enlaces y pégalos en Excel. Cada enlace en una línea separada.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="copyToClipboard">
                    <i class="fas fa-clipboard me-1"></i> Copiar al Portapapeles
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: AUTORES                                                -->
<!-- ============================================================ -->
<div class="modal fade" id="autoresModal" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-users me-2"></i><span id="autoresModalTitle">Autores encontrados</span>
                </h5>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-light" id="bulkAutoresBtn"
                            title="Remplazar/Modificar nombres de autores en lote desde CSV o XLSX">
                        <i class="fas fa-file-import me-1"></i> Remplazar autores
                    </button>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
            </div>
            <div class="modal-body">

                <!-- Fila 1: búsqueda + acciones masivas -->
                <div class="row mb-3 g-2">
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-success text-white">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" class="form-control" id="autorSearch" placeholder="Filtrar autores...">
                            
                            <!-- ═══ Botón Pegar en Azul (btn-primary) ═══ -->
                            <button class="btn btn-primary text-white" type="button" id="pasteAutorSearch" title="Pegar desde el portapapeles">
                                <i class="fas fa-paste me-1"></i> Pegar
                            </button>
                            
                            <!-- Botón Limpiar en Rojo (btn-danger) -->
                            <button class="btn btn-danger text-white" type="button" id="clearAutorSearch" title="Limpiar">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-8 text-end">
                        <span id="autoresBadgeSeleccionados"
                              class="badge rounded-pill bg-danger me-2"
                              style="display: none; font-size: 0.85rem; padding: 0.35rem 0.6rem; vertical-align: middle;">0</span>
                        <div class="btn-group btn-group-sm me-2">
                            <button class="btn btn-outline-primary" id="selectAllAutores">
                                <i class="fas fa-check-double me-1"></i> Seleccionar todos
                            </button>
                            <button class="btn btn-outline-danger" id="deselectAllAutores">
                                <i class="fas fa-times me-1"></i> Deseleccionar
                            </button>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-success" id="openSelectedAutores" disabled>
                                <i class="fas fa-external-link-alt me-1"></i> Abrir enlaces
                            </button>
                            <button class="btn btn-primary" id="copyAutorLinks" disabled>
                                <i class="fas fa-copy me-1"></i> Copiar enlaces
                            </button>
                            
                            <!-- ═══ NEW ═══ Exportar a Excel ═══ -->
                            <button class="btn btn-outline-success dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-file-excel me-1"></i> Exportar Excel
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li>
                                    <a class="dropdown-item" href="#" id="exportAutoresAllBtn">
                                        <i class="fas fa-list me-2 text-success"></i> Exportar todos (con filtros)
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#" id="exportAutoresSelectedBtn">
                                        <i class="fas fa-check-square me-2 text-primary"></i> Exportar seleccionados
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Fila 2: resumen numérico -->
                <div class="alert alert-info py-2 mb-3">
                    <div class="row">
                        <div class="col-md-3"><strong>Total autores:</strong> <span id="totalAutoresCount">0</span></div>
                        <div class="col-md-3"><strong>Seleccionados:</strong> <span id="selectedAutoresCount">0</span></div>
                        <div class="col-md-3"><strong>Total ilustraciones:</strong> <span id="totalIlustracionesAutores">0</span></div>
                        <div class="col-md-3"><strong>Con enlace:</strong> <span id="totalConEnlace">0</span></div>
                    </div>
                </div>

                <!-- Fila 3: paginación top -->
                <div class="bg-light border rounded py-2 px-3 mb-2">
                    <div class="row align-items-center">
                        <div class="col-md-5">
                            <div class="d-flex align-items-center">
                                <span class="me-2 small">Mostrar:</span>
                                <select class="form-select form-select-sm w-auto" id="autoresPerPageTop">
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                    <option value="200">200</option>
                                </select>
                                <span class="ms-2 small">autores por página</span>
                            </div>
                        </div>
                        <div class="col-md-7 text-end">
                            <nav aria-label="Paginación autores top" class="d-inline-block">
                                <ul class="pagination pagination-sm mb-0" id="autoresPaginacionTop"></ul>
                            </nav>
                        </div>
                    </div>
                </div>

                <!-- Tabla autores -->
                <div class="table-responsive">
                    <table class="table table-hover table-striped table-bordered table-sm" id="autoresTable">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th width="40"><input type="checkbox" class="form-check-input" id="checkAllAutoresHeader"></th>
                                <th width="60" class="text-center" title="Múltiples nombres">
                                    <i class="fas fa-layer-group"></i>
                                </th>
                                <th width="50">#</th>
                                <th>Autor</th>
                                <th width="80">Editar</th>
                                <th width="100">Ilustraciones</th>
                                <th width="200">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="autoresTableBody">
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="spinner-border text-success" role="status">
                                        <span class="visually-hidden">Cargando...</span>
                                    </div>
                                    <p class="mt-2">Cargando autores...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación bottom -->
                <div class="bg-light border rounded py-2 px-3 mt-2">
                    <div class="row align-items-center">
                        <div class="col-md-5">
                            <div class="d-flex align-items-center">
                                <span class="me-2 small">Mostrar:</span>
                                <select class="form-select form-select-sm w-auto" id="autoresPerPageBottom">
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                    <option value="200">200</option>
                                </select>
                                <span class="ms-2 small">autores por página</span>
                            </div>
                        </div>
                        <div class="col-md-7 text-end">
                            <nav aria-label="Paginación autores bottom" class="d-inline-block">
                                <ul class="pagination pagination-sm mb-0" id="autoresPaginacionBottom"></ul>
                            </nav>
                            <div class="mt-1">
                                <small class="text-muted" id="autoresPaginacionInfoBottom"></small>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <div class="flex-grow-1">
                    <small class="text-muted" id="autorStats"></small>
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-success" id="refreshAutores">
                    <i class="fas fa-sync-alt me-1"></i> Actualizar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: EDITAR AUTOR                                           -->
<!-- ============================================================ -->
<div class="modal fade" id="editarAutorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Autor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">

                <div class="mb-4">
                    <label class="form-label fw-bold"><i class="fas fa-link me-1"></i> Enlace del Autor (identificador)</label>
                    <div class="d-flex align-items-center gap-2">
                        <span id="editAutorEnlaceDisplay" class="text-break flex-grow-1 text-muted">(sin enlace)</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnEditarEnlaceAutor">
                            Editar enlace
                        </button>
                    </div>
                    <input type="hidden" id="editAutorEnlaceOriginal" value="">
                </div>

                <div class="mb-4 d-none" id="bloqueEditarEnlace">
                    <label class="form-label" for="editAutorEnlaceNuevo">Nuevo Enlace del Autor</label>
                    <div class="input-group">
                        <input type="url" class="form-control" id="editAutorEnlaceNuevo"
                               placeholder="https://www.pixiv.net/en/users/XXXXX">
                        <button class="btn btn-outline-secondary" type="button" id="btnCancelarEditarEnlace" title="Cancelar">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="form-text">
                        Modifica el enlace del autor. Se aplicará a todos los registros con el enlace anterior.
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold"><i class="fas fa-list me-1"></i> Autor Encontrado</label>
                    <div class="border rounded p-2" id="editAutorListaNombres">
                        <div class="text-muted">Cargando…</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold" for="editAutorNombreNuevo">
                        <i class="fas fa-user-edit me-1"></i> Cambiar Autor
                    </label>
                    <input type="text" class="form-control" id="editAutorNombreNuevo"
                           placeholder="Nuevo nombre para todos los autores de este enlace"
                           title="Cambiar un único autor">
                    <div class="form-text">
                        Escribe un nombre para renombrar <strong>todos</strong> los autores que comparten este enlace.
                    </div>
                </div>

                <div class="alert alert-warning py-2 small mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    El <strong>enlace</strong> es el identificador. Los nombres múltiples corresponden a variantes del mismo autor.
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="guardarEditarAutor">
                    <i class="fas fa-save me-1"></i> Guardar cambios
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: DETALLE MÚLTIPLE AUTOR                                 -->
<!-- ============================================================ -->
<div class="modal fade" id="detalleMultipleAutorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-layer-group me-2"></i>Detalle Múltiple Autor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2 text-muted small">Enlace:</p>
                <p class="text-break" id="detalleMultipleEnlace">—</p>
                <hr>
                <p class="mb-2 text-muted small">Nombres registrados:</p>
                <div id="detalleMultipleLista"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="btnCopiarEnlaceMultiple">
                    <i class="fas fa-copy me-1"></i> Copiar enlace
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: DETALLE AUTOR (simple)                                 -->
<!-- ============================================================ -->
<div class="modal fade" id="detalleAutorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Detalle del Autor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="detalleAutorBody">
                <!-- Se llena dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="btnCopiarEnlaceDetalle">
                    <i class="fas fa-copy me-1"></i> Copiar enlace
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-success" id="abrirEnlaceDetalle">
                    <i class="fas fa-external-link-alt me-1"></i> Abrir enlace
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- TOOLTIP FLOTANTE: Detalle Múltiple Autor                      -->
<!-- ============================================================ -->
<div id="tooltipMultipleAutor" role="tooltip" aria-hidden="true">
    <div class="tooltip-header">
        <i class="fas fa-layer-group me-1"></i> Detalle Múltiple Autor
    </div>
    <div id="tooltipMultipleAutorLista"></div>
</div>

<!-- MODAL: REMPLAZAR AUTORES EN LOTE (CSV / XLSX) -->
<!-- NOTA: Iniciamos con modal-md para que la ventana 1 sea más pequeña -->
<div class="modal fade" id="bulkAutoresModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-md" id="bulkModalDialog">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-file-import me-2"></i>
                    Remplazar/Modificar autores en lote
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body">

                <!-- INDICADOR DE PASOS (Restaurado) -->
                <ul class="nav nav-pills nav-fill mb-4 small" id="bulkSteps">
                    <li class="nav-item">
                        <span class="nav-link active" data-step="1">
                            <i class="fas fa-file-excel me-1"></i> 1. Archivo
                        </span>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link disabled" data-step="2">
                            <i class="fas fa-columns me-1"></i> 2. Columnas
                        </span>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link disabled" data-step="3">
                            <i class="fas fa-eye me-1"></i> 3. Vista previa
                        </span>
                    </li>
                </ul>

                <!-- ═══════════ VENTANA 1: ARCHIVO ═══════════ -->
                <div id="bulkStep1">
                    <div class="text-center py-3">
                        <div class="mb-3">
                            <i class="fas fa-file-excel text-success" style="font-size: 6rem;"></i>
                        </div>
                        <h4 class="mb-3 fw-bold">Selecciona el archivo</h4>
                        <p class="text-muted mb-4">
                            El archivo debe contener <strong>dos columnas</strong>:<br>
                            el <strong>enlace del autor</strong> (URL de Pixiv) y el <strong>nombre nuevo</strong>.
                        </p>

                        <div class="mx-auto" style="max-width: 520px;">
                            <div class="bulk-file-drop" id="bulkDropZone">
                                <i class="fas fa-cloud-upload-alt text-primary mb-3" style="font-size: 2.5rem;"></i>
                                <p class="mb-3 text-muted">Arrastra tu archivo aquí o</p>
                                <label for="bulkFileInput" class="btn btn-primary mb-2">
                                    <i class="fas fa-folder-open me-1"></i> Seleccionar archivo
                                </label>
                                <input type="file" id="bulkFileInput" class="d-none"
                                       accept=".csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                                <div class="form-text small">
                                    Acepta <code>.csv</code>, <code>.xlsx</code>, <code>.xls</code>
                                    &nbsp;·&nbsp; Máx 10 MB &nbsp;·&nbsp; Máx 10,000 filas
                                </div>
                            </div>

                            <div class="alert alert-success d-flex align-items-center mt-3 mb-0 d-none" id="bulkFileInfo">
                                <i class="fas fa-check-circle me-2"></i>
                                <span id="bulkFileInfoText" class="flex-grow-1 small"></span>
                                <button type="button" class="btn-close ms-2" id="bulkClearFileBtn" aria-label="Quitar archivo"></button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══════════ VENTANA 2: COLUMNAS ═══════════ -->
                <div id="bulkStep2" class="d-none">
                    <div class="alert alert-info py-2 small mb-4">
                        <i class="fas fa-info-circle me-1"></i>
                        Confirma qué columna contiene el enlace del autor y qué columna el nombre nuevo.
                    </div>
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="bulkColEnlace">
                                <i class="fas fa-link me-1"></i> Columna: Enlace del autor
                            </label>
                            <select class="form-select" id="bulkColEnlace"></select>
                            <div class="form-text">
                                Debe contener URLs tipo <code>https://www.pixiv.net/en/users/XXXXX</code>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="bulkColNombre">
                                <i class="fas fa-user-edit me-1"></i> Columna: Nombre nuevo
                            </label>
                            <select class="form-select" id="bulkColNombre"></select>
                            <div class="form-text">Nombre que reemplazará al actual para ese enlace</div>
                        </div>
                    </div>
                    <div class="alert alert-light border py-2 small mb-0" id="bulkDetectedInfo"></div>
                </div>

                <!-- ═══════════ VENTANA 3: VISTA PREVIA ═══════════ -->
                <div id="bulkStep3" class="d-none">
                    <div class="row g-2 mb-4">
                        <!-- Tarjetas de conteo (Total, Actualizarán, etc.) se mantienen igual -->
                        <div class="col-6 col-md-3">
                            <div class="card text-center">
                                <div class="card-body p-2">
                                    <div class="h4 mb-0" id="bulkCountTotal">0</div>
                                    <small class="text-muted">Total filas</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card text-center border-success">
                                <div class="card-body p-2">
                                    <div class="h4 mb-0 text-success" id="bulkCountChanged">0</div>
                                    <small class="text-muted">Se actualizarán</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card text-center border-info">
                                <div class="card-body p-2">
                                    <div class="h4 mb-0 text-info" id="bulkCountSame">0</div>
                                    <small class="text-muted">Sin cambios</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card text-center border-danger">
                                <div class="card-body p-2">
                                    <div class="h4 mb-0 text-danger" id="bulkCountNotFound">0</div>
                                    <small class="text-muted">No encontrados</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-bordered table-hover mb-0 text-center align-middle">
                            <!-- ═══ NEW: Encabezado dinámico ═══ -->
                            <thead class="table-dark sticky-top" id="bulkRecPreviewThead">
                                <!-- Se llena desde JavaScript -->
                            </thead>
                            <tbody id="bulkRecPreviewBody"></tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- FOOTER -->
            <div class="modal-footer">
                <div id="bulkFooterStep1" class="w-100 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cerrar
                    </button>
                    <button type="button" class="btn btn-primary" id="bulkNextFromFile" disabled>
                        Siguiente <i class="fas fa-arrow-right ms-1"></i>
                    </button>
                </div>

                <div id="bulkFooterStep2" class="w-100 d-flex justify-content-between d-none">
                    <button type="button" class="btn btn-secondary" id="bulkBackToFile">
                        <i class="fas fa-arrow-left me-1"></i> Atrás
                    </button>
                    <button type="button" class="btn btn-primary" id="bulkPreviewBtn">
                        <i class="fas fa-eye me-1"></i> Vista previa
                    </button>
                </div>

                <div id="bulkFooterStep3" class="w-100 d-flex justify-content-between d-none">
                    <button type="button" class="btn btn-secondary" id="bulkBackToColumns">
                        <i class="fas fa-arrow-left me-1"></i> Atrás
                    </button>
                    <div>
                        <button type="button" class="btn btn-outline-secondary me-2" id="bulkRefreshPreviewBtn">
                            <i class="fas fa-sync-alt me-1"></i> Actualizar
                        </button>
                        <button type="button" class="btn btn-success" id="bulkApplyBtn">
                            <i class="fas fa-check me-1"></i> Aplicar cambios
                        </button>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- MODAL: PROGRESO DE EXPORTACIÓN -->
<div class="modal fade" id="exportProgressModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <i class="fas fa-file-excel text-success mb-3" style="font-size: 3rem;"></i>
                <h5 class="mb-3">Exportando autores...</h5>
                
                <div class="progress mb-3" style="height: 20px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                         id="exportProgressBar" role="progressbar" style="width: 0%;">0%</div>
                </div>
                
                <p class="mb-1 small fw-bold" id="exportProgressText">Conectando...</p>
                <p class="text-muted small mb-0" id="exportEtaText">Calculando tiempo...</p>
            </div>
            <div class="modal-footer justify-content-center p-2">
                <button type="button" class="btn btn-outline-danger btn-sm" id="cancelExportBtn">
                    <i class="fas fa-times me-1"></i> Cancelar Exportación
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: CONFIGURAR COLUMNAS DE EXPORTACIÓN -->
<div class="modal fade" id="configColumnsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="fas fa-cog me-2"></i> Configurar Columnas (24)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between mb-3">
                    <button class="btn btn-sm btn-outline-primary" id="selectAllCols">Seleccionar todas</button>
                    <button class="btn btn-sm btn-outline-secondary" id="deselectAllCols">Deseleccionar todas</button>
                </div>
                <div class="row" id="columnsCheckboxList">
                    <!-- Se llena dinámicamente con JS -->
                </div>
                <!-- ═══ NEW: Opciones de Formato para Tag_03 ═══ -->
                <div class="mt-4 pt-3 border-top">
                    <h6 class="fw-bold text-secondary mb-3"><i class="fas fa-tags me-2"></i> Formato de Tag_03 en el Excel</h6>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="tag03Format" id="tag03Opt1" value="1">
                        <label class="form-check-label small" for="tag03Opt1">
                            <strong>Opción 1 (Original):</strong> Texto tal cual viene en la base de datos.<br>
                            <span class="text-muted" style="font-size: 0.8rem;">Ej: 魅惑のふとももbewitching thighs</span>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="tag03Format" id="tag03Opt2" value="2">
                        <label class="form-check-label small" for="tag03Opt2">
                            <strong>Opción 2 (Separado):</strong> Separa el texto asiático del inglés con corchetes y barra.<br>
                            <span class="text-muted" style="font-size: 0.8rem;">Ej: [魅惑のふともも]|[bewitching thighs]</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="saveColumnsBtn">Guardar Configuración</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: REMPLAZAR REGISTROS EN LOTE (CSV / XLSX)               -->
<!-- ============================================================ -->
<div class="modal fade" id="bulkRecordsModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-md" id="bulkRecordsDialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="fas fa-file-import me-2"></i> Remplazar registros en lote
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            
            <div class="modal-body">
                <!-- Indicador de pasos -->
                <ul class="nav nav-pills nav-fill mb-4 small" id="bulkRecordsSteps">
                    <li class="nav-item"><span class="nav-link active" data-step="1"><i class="fas fa-file-excel me-1"></i> 1. Archivo</span></li>
                    <li class="nav-item"><span class="nav-link disabled" data-step="2"><i class="fas fa-columns me-1"></i> 2. Columnas</span></li>
                    <li class="nav-item"><span class="nav-link disabled" data-step="3"><i class="fas fa-eye me-1"></i> 3. Vista previa</span></li>
                </ul>

                <!-- VENTANA 1: ARCHIVO -->
                <div id="bulkRecStep1">
                    <div class="text-center py-3">
                        <i class="fas fa-file-excel text-success mb-3" style="font-size: 5rem;"></i>
                        <h4 class="mb-3 fw-bold">Selecciona el archivo</h4>
                        <p class="text-muted mb-4">
                            El archivo debe contener una columna obligatoria:<br>
                            <strong>Contenedor_Enlace_Album_href</strong> (URL del artwork) como identificador.
                        </p>
                        <div class="mx-auto" style="max-width: 520px;">
                            <div class="bulk-file-drop" id="bulkRecDropZone">
                                <i class="fas fa-cloud-upload-alt text-primary mb-3" style="font-size: 2.5rem;"></i>
                                <p class="mb-3 text-muted">Arrastra tu archivo aquí o</p>
                                <label for="bulkRecFileInput" class="btn btn-primary mb-2">
                                    <i class="fas fa-folder-open me-1"></i> Seleccionar archivo
                                </label>
                                <input type="file" id="bulkRecFileInput" class="d-none" accept=".csv,.xlsx,.xls">
                                <div class="form-text small">Acepta .csv, .xlsx, .xls · Máx 10 MB</div>
                            </div>
                            <div class="alert alert-success d-flex align-items-center mt-3 mb-0 d-none" id="bulkRecFileInfo">
                                <i class="fas fa-check-circle me-2"></i>
                                <span id="bulkRecFileInfoText" class="flex-grow-1 small"></span>
                                <button type="button" class="btn-close ms-2" id="bulkRecClearFileBtn"></button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VENTANA 2: COLUMNAS -->
                <div id="bulkRecStep2" class="d-none">
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="fas fa-magic me-1"></i> Mapea las columnas de tu archivo a las de la base de datos.
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-primary">Identificador (Obligatorio)</label>
                            <select class="form-select" id="bulkRecColIdentificador"></select>
                        </div>
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold mb-0">Mapeo de Columnas</label>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 me-1" id="bulkRecSelectAll">
                                        <i class="fas fa-check-double me-1"></i> Seleccionar todas
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger py-0" id="bulkRecDeselectAll">
                                        <i class="fas fa-times me-1"></i> Deseleccionar
                                    </button>
                                </div>
                            </div>
                            <!-- Tabla de mapeo (Base de Datos -> Excel) -->
                            <div class="table-responsive border rounded" style="max-height: 250px; overflow-y: auto;">
                                <table class="table table-sm table-hover mb-0 align-middle text-center">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th width="40">Usar</th>
                                            <th class="text-start">Columna en Base de Datos</th>
                                            <th class="text-start">Columna en Excel</th>
                                        </tr>
                                    </thead>
                                    <tbody id="bulkRecMappingTableBody">
                                        <!-- Se llena dinámicamente con JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VENTANA 3: VISTA PREVIA -->
                <div id="bulkRecStep3" class="d-none">
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-4">
                            <div class="card text-center"><div class="card-body p-2"><div class="h4 mb-0" id="bulkRecCountTotal">0</div><small class="text-muted">Total filas</small></div></div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="card text-center border-success"><div class="card-body p-2"><div class="h4 mb-0 text-success" id="bulkRecCountChanged">0</div><small class="text-muted">Se actualizarán</small></div></div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="card text-center border-danger"><div class="card-body p-2"><div class="h4 mb-0 text-danger" id="bulkRecCountNotFound">0</div><small class="text-muted">No encontrados</small></div></div>
                        </div>
                    </div>
                    
                    <!-- Contenedor limpio donde JS inyectará la tabla -->
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;" id="bulkRecPreviewTableContainer">
                    </div>
                </div>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer">
                <div id="bulkRecFooterStep1" class="w-100 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" id="bulkRecNextFromFile" disabled>Siguiente <i class="fas fa-arrow-right ms-1"></i></button>
                </div>
                <div id="bulkRecFooterStep2" class="w-100 d-flex justify-content-between d-none">
                    <button type="button" class="btn btn-secondary" id="bulkRecBackToFile"><i class="fas fa-arrow-left me-1"></i> Atrás</button>
                    <button type="button" class="btn btn-primary" id="bulkRecPreviewBtn"><i class="fas fa-eye me-1"></i> Vista previa</button>
                </div>
                <div id="bulkRecFooterStep3" class="w-100 d-flex justify-content-between d-none">
                    <button type="button" class="btn btn-secondary" id="bulkRecBackToColumns"><i class="fas fa-arrow-left me-1"></i> Atrás</button>
                    <button type="button" class="btn btn-warning" id="bulkRecApplyBtn"><i class="fas fa-save me-1"></i> Aplicar cambios</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: LIMPIAR DATOS DE COLUMNAS                              -->
<!-- ============================================================ -->
<div class="modal fade" id="clearDataModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-md" id="clearDataDialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-eraser me-2"></i> Limpiar datos de columnas
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            
            <div class="modal-body">
                <!-- Indicador de pasos -->
                <ul class="nav nav-pills nav-fill mb-4 small" id="clearDataSteps">
                    <li class="nav-item"><span class="nav-link active" data-step="1"><i class="fas fa-columns me-1"></i> 1. Columnas</span></li>
                    <li class="nav-item"><span class="nav-link disabled" data-step="2"><i class="fas fa-exclamation-triangle me-1"></i> 2. Confirmar</span></li>
                </ul>

                <!-- VENTANA 1: SELECCIONAR COLUMNAS -->
                <div id="clearDataStep1">
                    <div class="alert alert-warning py-2 small mb-3">
                        <i class="fas fa-exclamation-triangle me-1"></i> Selecciona las columnas de la base de datos que deseas vaciar (dejar en blanco).
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-secondary">Columnas:</span>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 me-1" id="clearDataSelectAll">
                                <i class="fas fa-check-double me-1"></i> Todas
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger py-0" id="clearDataDeselectAll">
                                <i class="fas fa-times me-1"></i> Ninguna
                            </button>
                        </div>
                    </div>

                    <div class="border rounded p-3 bg-light" style="max-height: 250px; overflow-y: auto;">
                        <div class="row" id="clearDataCheckboxList">
                            <!-- Se llena dinámicamente con JS -->
                        </div>
                    </div>
                </div>

                <!-- VENTANA 2: CONFIRMACIÓN -->
                <div id="clearDataStep2" class="d-none text-center py-4">
                    <i class="fas fa-exclamation-triangle text-danger mb-3" style="font-size: 4rem;"></i>
                    <h4 class="mb-3 fw-bold text-danger">¿Estás seguro?</h4>
                    <p class="text-muted mb-4">
                        Estás a punto de <strong>borrar permanentemente</strong> el contenido de <strong id="clearDataCountCols">0</strong> columna(s) en <strong id="clearDataCountRows">0</strong> registro(s).<br>
                        Esta acción no se puede deshacer.
                    </p>
                </div>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer">
                <div id="clearDataFooterStep1" class="w-100 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="clearDataNextBtn" disabled>Siguiente <i class="fas fa-arrow-right ms-1"></i></button>
                </div>
                <div id="clearDataFooterStep2" class="w-100 d-flex justify-content-between d-none">
                    <button type="button" class="btn btn-secondary" id="clearDataBackBtn"><i class="fas fa-arrow-left me-1"></i> Atrás</button>
                    <button type="button" class="btn btn-danger" id="clearDataApplyBtn"><i class="fas fa-eraser me-1"></i> Sí, borrar datos</button>
                </div>
            </div>
        </div>
    </div>
</div>