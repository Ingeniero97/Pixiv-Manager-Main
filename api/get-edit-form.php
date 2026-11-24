<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

$id = $_GET['id'] ?? '';
if (!is_string($id) || $id === '') {
    http_response_code(400);
    exit('<div class="alert alert-danger">ID inválido.</div>');
}

try {
    $stmt = getPDO()->prepare('SELECT * FROM ilustraciones WHERE ORDEN = :id LIMIT 1');
    $stmt->bindValue(':id', $id, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch();
} catch (Throwable $e) {
    error_log('[get-edit-form] ' . $e->getMessage());
    http_response_code(500);
    exit('<div class="alert alert-danger">Error al cargar el formulario.</div>');
}

if (!$row) {
    http_response_code(404);
    exit('<div class="alert alert-warning">Registro no encontrado.</div>');
}
?>

<form id="editRecordForm" data-orden="<?= e((string)$row['ORDEN']) ?>" autocomplete="off">
    <input type="hidden" name="ORDEN" value="<?= e((string)$row['ORDEN']) ?>">

    <!-- BARRA SUPERIOR: ORDEN Y ESTADO -->
    <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded border mb-3">
        <div class="fw-bold text-secondary">
            <i class="fas fa-hashtag me-1"></i> ORDEN: 
            <span class="text-dark fs-5"><?= e((string)$row['ORDEN']) ?></span>
        </div>
        <div class="d-flex align-items-center gap-2" style="width: 200px;">
            <label class="fw-bold text-secondary mb-0 text-nowrap">Estado:</label>
            <select class="form-select form-select-sm" name="Estado">
                <option value="1" <?= (int)$row['Estado'] === 1 ? 'selected' : '' ?>>Activo (1)</option>
                <option value="0" <?= (int)$row['Estado'] === 0 ? 'selected' : '' ?>>Inactivo (0)</option>
            </select>
        </div>
    </div>

    <div class="row g-3 align-items-start">
        <!-- COLUMNA IZQUIERDA: Scraper y Contenedor -->
        <div class="col-md-5 col-lg-4">
            <!-- Acordeón Scraper -->
            <div class="accordion mb-3" id="editAccScraper">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button py-2 fw-bold text-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#editCollScraper">
                            <i class="fas fa-robot me-2"></i> Scraper
                        </button>
                    </h2>
                    <div id="editCollScraper" class="accordion-collapse collapse show" data-bs-parent="#editAccScraper">
                        <div class="accordion-body p-0">
                            <table class="table table-sm table-striped mb-0 align-middle">
                                <tbody>
                                    <tr>
                                        <td width="30" class="ps-3 text-muted text-center">#1</td>
                                        <th width="30%" class="text-secondary">Order</th>
                                        <td class="pe-2"><input type="text" class="form-control form-control-sm" name="web_scraper_order" value="<?= e((string)$row['web_scraper_order']) ?>"></td>
                                    </tr>
                                    <tr>
                                        <td class="ps-3 text-muted text-center">#2</td>
                                        <th class="text-secondary">Star_url</th>
                                        <td class="pe-2"><input type="text" class="form-control form-control-sm" name="web_scraper_start_url" value="<?= e((string)$row['web_scraper_start_url']) ?>"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Acordeón Contenedor -->
            <div class="accordion mb-3" id="editAccContenedor">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button py-2 fw-bold text-secondary collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#editCollContenedor">
                            <i class="fas fa-box me-2"></i> Contenedor
                        </button>
                    </h2>
                    <div id="editCollContenedor" class="accordion-collapse collapse" data-bs-parent="#editAccContenedor">
                        <div class="accordion-body p-0">
                            <table class="table table-sm table-striped mb-0 align-middle">
                                <tbody>
                                    <tr><td width="30" class="ps-3 text-muted text-center">#1</td><th width="30%" class="text-secondary">Tipo</th><td class="pe-2"><input type="text" class="form-control form-control-sm" name="Contenedor_Tipo" value="<?= e((string)$row['Contenedor_Tipo']) ?>"></td></tr>
                                    <tr><td class="ps-3 text-muted text-center">#2</td><th class="text-secondary">Número Hoja</th><td class="pe-2"><input type="text" class="form-control form-control-sm" name="Contenedor_Numero_Hoja" value="<?= e((string)$row['Contenedor_Numero_Hoja']) ?>"></td></tr>
                                    <tr><td class="ps-3 text-muted text-center">#3</td><th class="text-secondary">Alt_Album</th><td class="pe-2"><input type="text" class="form-control form-control-sm" name="Contenedor_Alt_Album" value="<?= e((string)$row['Contenedor_Alt_Album']) ?>"></td></tr>
                                    <tr><td class="ps-3 text-muted text-center">#4</td><th class="text-secondary">Autor_Album</th><td class="pe-2"><input type="text" class="form-control form-control-sm" name="Contenedor_Autor_Album" value="<?= e((string)$row['Contenedor_Autor_Album']) ?>"></td></tr>
                                    <tr><td class="ps-3 text-muted text-center">#5</td><th class="text-secondary">Enlace_Autor</th><td class="pe-2"><input type="text" class="form-control form-control-sm" name="Contenedor_Enlace_Autor_Album" value="<?= e((string)$row['Contenedor_Enlace_Autor_Album']) ?>"></td></tr>
                                    <tr><td class="ps-3 text-muted text-center">#6</td><th class="text-secondary">Nombre_Album</th><td class="pe-2"><input type="text" class="form-control form-control-sm" name="Contenedor_Nombre_Album" value="<?= e((string)$row['Contenedor_Nombre_Album']) ?>"></td></tr>
                                    <tr><td class="ps-3 text-muted text-center">#7</td><th class="text-secondary">Enlace_Album</th><td class="pe-2"><input type="text" class="form-control form-control-sm" name="Contenedor_Enlace_Album" value="<?= e((string)$row['Contenedor_Enlace_Album']) ?>"></td></tr>
                                    <tr><td class="ps-3 text-muted text-center">#8</td><th class="text-secondary">Enlace_href</th><td class="pe-2"><input type="text" class="form-control form-control-sm" name="Contenedor_Enlace_Album_href" value="<?= e((string)$row['Contenedor_Enlace_Album_href']) ?>"></td></tr>
                                    <tr><td class="ps-3 text-muted text-center">#9</td><th class="text-secondary">ID_Enlace</th><td class="pe-2"><input type="text" class="form-control form-control-sm" name="Contenedor_ID_Enlace" value="<?= e((string)$row['Contenedor_ID_Enlace']) ?>"></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- COLUMNA DERECHA: Enlace -->
        <div class="col-md-7 col-lg-8">
            <div class="accordion mb-3" id="editAccEnlace">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button py-2 fw-bold text-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#editCollEnlace">
                            <i class="fas fa-link me-2"></i> Enlace (Pixiv)
                        </button>
                    </h2>
                    <div id="editCollEnlace" class="accordion-collapse collapse show" data-bs-parent="#editAccEnlace">
                        <div class="accordion-body p-0">
                            <table class="table table-sm table-striped mb-0 align-middle">
                                <tbody>
                                    <tr><td width="30" class="ps-3 text-muted text-center">#1</td><th width="20%" class="text-secondary">Titulo</th><td class="pe-2"><input type="text" class="form-control form-control-sm" name="Enlace_Titulo" value="<?= e((string)$row['Enlace_Titulo']) ?>"></td></tr>
                                    <tr><td class="ps-3 text-muted text-center">#2</td><th class="text-secondary">Img_Original</th><td class="pe-2"><input type="text" class="form-control form-control-sm" name="Enlace_Img_Original" value="<?= e((string)$row['Enlace_Img_Original']) ?>"></td></tr>
                                    <tr><td class="ps-3 text-muted text-center">#3</td><th class="text-secondary">Alt_Img</th><td class="pe-2"><input type="text" class="form-control form-control-sm" name="Enlace_Alt_Img" value="<?= e((string)$row['Enlace_Alt_Img']) ?>"></td></tr>
                                    <tr>
                                        <td class="ps-3 text-muted text-center">#4</td>
                                        <th class="text-secondary">Ugoira</th>
                                        <td class="pe-2">
                                            <input type="text" class="form-control form-control-sm" name="Enlace_Ugoira" value="<?= e((string)$row['Enlace_Ugoira']) ?>" list="ugoira-options">
                                            <datalist id="ugoira-options">
                                                <option value="UGOIRA">
                                                <option value="NO SHOW ALL OR READING WORKS">
                                                <option value="SOLO UNA IMAGEN">
                                            </datalist>
                                        </td>
                                    </tr>
                                    <tr><td class="ps-3 text-muted text-center">#5</td><th class="text-secondary">Reading/Show</th><td class="pe-2"><input type="text" class="form-control form-control-sm" name="Enlace_Reading_works_or_Show_all" value="<?= e((string)$row['Enlace_Reading_works_or_Show_all']) ?>"></td></tr>
                                    <tr><td class="ps-3 text-muted text-center">#6</td><th class="text-secondary">Tags_Completo</th><td class="pe-2"><textarea class="form-control form-control-sm" name="Enlace_Tags_Completo" rows="2"><?= e((string)$row['Enlace_Tags_Completo']) ?></textarea></td></tr>
                                    <tr><td class="ps-3 text-muted text-center">#7</td><th class="text-secondary">Tag_01</th><td class="pe-2"><input type="text" class="form-control form-control-sm" name="Enlace_Tag_01" value="<?= e((string)$row['Enlace_Tag_01']) ?>"></td></tr>
                                    <tr><td class="ps-3 text-muted text-center">#8</td><th class="text-secondary">Tag_02</th><td class="pe-2"><textarea class="form-control form-control-sm" name="Enlace_Tag_02" rows="2"><?= e((string)$row['Enlace_Tag_02']) ?></textarea></td></tr>
                                    <tr>
                                        <td class="ps-3 text-muted text-center">#9</td>
                                        <th class="text-secondary">Tag_03</th>
                                        <td class="pe-2">
                                            <div class="d-flex align-items-start gap-2">
                                                <div class="flex-grow-1">
                                                    <?php
                                                    $tag03_val = (string)$row['Enlace_Tag_03'];
                                                    // Convertir saltos de línea en comas para Tagify
                                                    $tag03_val = preg_replace('/\R+|\\\\n/', ', ', $tag03_val);
                                                    ?>
                                                    <input name="Enlace_Tag_03" id="input_tag03" 
                                                           class="form-control form-control-sm" 
                                                           value="<?= e($tag03_val) ?>">
                                                </div>
                                                <!-- Botones de Quitar, Copiar y Pegar en Vertical -->
                                                <div class="btn-group-vertical shadow-sm" role="group">
                                                    <button type="button" class="btn btn-outline-danger btn-sm" id="btn-clear-tag03" title="Quitar todas las etiquetas">
                                                        <i class="fas fa-trash-alt me-1"></i> Quitar
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-copy-tag03" title="Copiar etiquetas">
                                                        <i class="fas fa-copy me-1"></i> Copiar
                                                    </button>
                                                    <button type="button" class="btn btn-outline-info btn-sm" id="btn-paste-tag03" title="Pegar etiquetas">
                                                        <i class="fas fa-paste me-1"></i> Pegar
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr><td class="ps-3 text-muted text-center">#10</td><th class="text-secondary">Fecha_01</th><td class="pe-2"><input type="text" class="form-control form-control-sm" name="Enlace_Fecha_Formato_01" value="<?= e((string)$row['Enlace_Fecha_Formato_01']) ?>"></td></tr>
                                    <tr><td class="ps-3 text-muted text-center">#11</td><th class="text-secondary">Fecha_02</th><td class="pe-2"><input type="text" class="form-control form-control-sm" name="Enlace_Fecha_Formato_02" value="<?= e((string)$row['Enlace_Fecha_Formato_02']) ?>"></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FOOTER: Botones -->
    <div class="d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
            <i class="fas fa-times me-1"></i> Cancelar
        </button>
        <button type="submit" class="btn btn-warning btn-sm" id="saveEditBtn">
            <i class="fas fa-save me-1"></i> Guardar cambios
        </button>
    </div>
</form>