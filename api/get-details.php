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
    error_log('[get-details] ' . $e->getMessage());
    http_response_code(500);
    exit('<div class="alert alert-danger">Error al cargar el registro.</div>');
}

if (!$row) {
    http_response_code(404);
    exit('<div class="alert alert-warning">Registro no encontrado.</div>');
}

// --- Helpers locales ---
$isLink = fn(?string $v): bool => is_string($v) && preg_match('#^https?://#i', $v) === 1;

$normalizarSaltos = function(?string $texto): string {
    if ($texto === null) return '';
    $limpio = str_replace(['\\n', '\\r'], ["\n", ""], $texto);
    return mb_scrub($limpio, 'UTF-8');
};

$renderVal = function(?string $val) use ($isLink): string {
    if ($val === null || $val === '') return '<span class="text-muted">—</span>';
    if ($isLink($val)) return '<a href="' . e($val) . '" target="_blank" rel="noopener noreferrer" class="text-break">' . e($val) . '</a>';
    return e($val);
};

$separarTextoBilingue = function(string $texto): string {
    $texto = preg_replace('/(?<=[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\)\]])(?=[a-zA-Z])/u', ' | ', $texto);
    $texto = preg_replace('/(?<=[a-zA-Z])(?=[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\(\[])/u', ' | ', $texto);
    return $texto;
};

$renderTagPill = function(string $tag): string {
    if (preg_match('/^(.*?[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\)\]])(\s*)([a-zA-Z].*)$/u', $tag, $matches)) {
        $asiatico = trim($matches[1]);
        $ingles = trim($matches[3]);
        $html = '<span class="badge rounded-pill border border-primary p-0 d-inline-flex align-items-stretch overflow-hidden me-2 mb-2" style="font-size:0.8rem; font-weight:500; text-align: left; background-color: transparent;">';
        $html .= '<span style="background-color: #0d6efd; color: white; padding: 0.4em 0.8em; display: flex; align-items: center;">' . e($asiatico) . '</span>';
        $html .= '<span style="background-color: rgba(13, 110, 253, 0.15); color: #0d6efd; padding: 0.4em 0.8em; display: flex; align-items: center;">';
        $html .= '<span class="fw-bold me-2 text-secondary opacity-75">|</span>' . e($ingles);
        $html .= '</span></span>';
        return $html;
    }
    $esAsiatico = preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', $tag);
    if ($esAsiatico) {
        return '<span class="badge rounded-pill border border-primary text-white me-2 mb-2" style="font-size:0.8rem; font-weight:500; background-color: #0d6efd; padding: 0.5em 0.8em; display: inline-block;">' . e($tag) . '</span>';
    } else {
        return '<span class="badge rounded-pill border border-primary text-primary me-2 mb-2" style="font-size:0.8rem; font-weight:500; background-color: rgba(13, 110, 253, 0.15); padding: 0.5em 0.8em; display: inline-block;">' . e($tag) . '</span>';
    }
};

$tag01_final = $separarTextoBilingue($normalizarSaltos((string)($row['Enlace_Tag_01'] ?? '')));
$tag02_final = implode("\n", array_map($separarTextoBilingue, explode("\n", $normalizarSaltos((string)($row['Enlace_Tag_02'] ?? '')))));

$tag03_raw = $normalizarSaltos((string)($row['Enlace_Tag_03'] ?? ''));
$tag03_pills = '';
if ($tag03_raw !== '') {
    $tags = preg_split('/[\r\n]+/u', $tag03_raw, -1, PREG_SPLIT_NO_EMPTY);
    if ($tags !== false) {
        foreach ($tags as $tag) {
            $tag = trim($tag);
            if ($tag !== '') $tag03_pills .= $renderTagPill($tag);
        }
    }
}
if ($tag03_pills === '') $tag03_pills = '<span class="text-muted">—</span>';
?>

<!-- Botón de copiar genérico para usar en las tablas -->
<?php
$copyBtn = function($texto, $type = 'texto') {
    if (empty($texto)) return '';
    return '<button class="btn btn-sm btn-link text-success p-0 ms-2 btn-copy-detail copy-hover-effect" data-copy="'.e($texto).'" data-type="'.$type.'" title="Copiar"><i class="fas fa-copy"></i></button>';
};
?>

<div class="row g-3 align-items-start">
    <!-- BARRA SUPERIOR: ORDEN Y ESTADO -->
    <div class="col-12 mb-2">
        <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded border">
            <div class="fw-bold text-secondary">
                <i class="fas fa-hashtag me-1"></i> ORDEN: 
                <span class="text-dark fs-5"><?= e((string)$row['ORDEN']) ?></span>
            </div>
            <div>
                <?php if ((int)$row['Estado'] === 1): ?>
                    <span class="badge bg-success fs-6"><i class="fas fa-check-circle me-1"></i> Activo</span>
                <?php else: ?>
                    <span class="badge bg-danger fs-6"><i class="fas fa-times-circle me-1"></i> Inactivo</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- COLUMNA IZQUIERDA: Scraper y Contenedor -->
    <div class="col-md-5 col-lg-4">
        <!-- Acordeón Scraper -->
        <div class="accordion mb-3" id="accScraper">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button py-2 fw-bold text-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#collScraper">
                        <i class="fas fa-robot me-2"></i> Scraper
                    </button>
                </h2>
                <div id="collScraper" class="accordion-collapse collapse show" data-bs-parent="#accScraper">
                    <div class="accordion-body p-0">
                        <table class="table table-sm table-striped mb-0 align-middle">
                            <tbody>
                                <tr>
                                    <td width="30" class="ps-3 text-muted text-center">#1</td>
                                    <th width="30%" class="text-secondary">Order</th>
                                    <td class="text-break pe-2"><?= $renderVal((string)$row['web_scraper_order']) ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-3 text-muted text-center">#2</td>
                                    <th class="text-secondary">Star_url</th>
                                    <td class="text-break pe-2"><?= $renderVal((string)$row['web_scraper_start_url']) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Acordeón Contenedor -->
        <div class="accordion mb-3" id="accContenedor">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button py-2 fw-bold text-secondary collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collContenedor">
                        <i class="fas fa-box me-2"></i> Contenedor
                    </button>
                </h2>
                <div id="collContenedor" class="accordion-collapse collapse" data-bs-parent="#accContenedor">
                    <div class="accordion-body p-0">
                        <table class="table table-sm table-striped mb-0 align-middle">
                            <tbody>
                                <tr><td width="30" class="ps-3 text-muted text-center">#1</td><th width="35%" class="text-secondary">Tipo</th><td class="pe-2"><?= $renderVal((string)$row['Contenedor_Tipo']) ?></td></tr>
                                <tr><td class="ps-3 text-muted text-center">#2</td><th class="text-secondary">Número Hoja</th><td class="pe-2"><?= $renderVal((string)$row['Contenedor_Numero_Hoja']) ?></td></tr>
                                <tr><td class="ps-3 text-muted text-center">#3</td><th class="text-secondary">Alt_Album</th><td class="text-break pe-2"><?= $renderVal((string)$row['Contenedor_Alt_Album']) ?></td></tr>
                                <tr>
                                    <td class="ps-3 text-muted text-center">#4</td>
                                    <th class="text-secondary">Autor_Album</th>
                                    <td class="text-break pe-2"><?= $renderVal((string)$row['Contenedor_Autor_Album']) ?><?= $copyBtn((string)$row['Contenedor_Autor_Album'], 'autor') ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-3 text-muted text-center">#5</td>
                                    <th class="text-secondary">Enlace_Autor</th>
                                    <td class="text-break pe-2"><?= $renderVal((string)$row['Contenedor_Enlace_Autor_Album']) ?><?= $copyBtn((string)$row['Contenedor_Enlace_Autor_Album'], 'enlace') ?></td>
                                </tr>
                                <tr><td class="ps-3 text-muted text-center">#6</td><th class="text-secondary">Nombre_Album</th><td class="text-break pe-2"><?= $renderVal((string)$row['Contenedor_Nombre_Album']) ?></td></tr>
                                <tr><td class="ps-3 text-muted text-center">#7</td><th class="text-secondary">Enlace_Album</th><td class="text-break pe-2"><?= $renderVal((string)$row['Contenedor_Enlace_Album']) ?></td></tr>
                                <tr>
                                    <td class="ps-3 text-muted text-center">#8</td>
                                    <th class="text-secondary">Enlace_href</th>
                                    <td class="text-break pe-2"><?= $renderVal((string)$row['Contenedor_Enlace_Album_href']) ?><?= $copyBtn((string)$row['Contenedor_Enlace_Album_href'], 'enlace') ?></td>
                                </tr>
                                <tr><td class="ps-3 text-muted text-center">#9</td><th class="text-secondary">ID_Enlace</th><td class="pe-2"><?= $renderVal((string)$row['Contenedor_ID_Enlace']) ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- COLUMNA DERECHA: Enlace -->
    <div class="col-md-7 col-lg-8">
        <div class="accordion mb-3" id="accEnlace">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button py-2 fw-bold text-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#collEnlace">
                        <i class="fas fa-link me-2"></i> Enlace (Pixiv)
                    </button>
                </h2>
                <div id="collEnlace" class="accordion-collapse collapse show" data-bs-parent="#accEnlace">
                    <div class="accordion-body p-0">
                        <table class="table table-sm table-striped mb-0 align-middle">
                            <tbody>
                                <tr><td width="30" class="ps-3 text-muted text-center">#1</td><th width="20%" class="text-secondary">Titulo</th><td class="text-break pe-2"><?= $renderVal((string)$row['Enlace_Titulo']) ?></td></tr>
                                <tr><td class="ps-3 text-muted text-center">#2</td><th class="text-secondary">Img_Original</th><td class="text-break pe-2"><?= $renderVal((string)$row['Enlace_Img_Original']) ?></td></tr>
                                <tr><td class="ps-3 text-muted text-center">#3</td><th class="text-secondary">Alt_Img</th><td class="text-break pe-2"><?= $renderVal((string)$row['Enlace_Alt_Img']) ?></td></tr>
                                <tr><td class="ps-3 text-muted text-center">#4</td><th class="text-secondary">Ugoira</th><td class="text-break pe-2"><?= $renderVal((string)$row['Enlace_Ugoira']) ?></td></tr>
                                <tr><td class="ps-3 text-muted text-center">#5</td><th class="text-secondary">Reading/Show</th><td class="text-break pe-2"><?= $renderVal((string)$row['Enlace_Reading_works_or_Show_all']) ?></td></tr>
                                <tr><td class="ps-3 text-muted text-center">#6</td><th class="text-secondary">Tags_Completo</th><td class="text-break pe-2"><?= $renderVal((string)$row['Enlace_Tags_Completo']) ?></td></tr>
                                <tr><td class="ps-3 text-muted text-center">#7</td><th class="text-secondary">Tag_01</th><td class="text-break pe-2"><?= $tag01_final !== '' ? e($tag01_final) : '<span class="text-muted">—</span>' ?></td></tr>
                                <tr><td class="ps-3 text-muted text-center">#8</td><th class="text-secondary">Tag_02</th><td class="text-break pe-2" style="line-height: 1.6;"><?= $tag02_final !== '' ? nl2br(e($tag02_final)) : '<span class="text-muted">—</span>' ?></td></tr>
                                <tr><td class="ps-3 text-muted text-center">#9</td><th class="text-secondary">Tag_03</th><td class="text-break pe-2" style="line-height: 2.2;"><div class="d-flex flex-wrap gap-2"><?= $tag03_pills ?></div></td></tr>
                                <tr><td class="ps-3 text-muted text-center">#10</td><th class="text-secondary">Fecha_01</th><td class="text-break pe-2"><?= $renderVal((string)$row['Enlace_Fecha_Formato_01']) ?></td></tr>
                                <tr><td class="ps-3 text-muted text-center">#11</td><th class="text-secondary">Fecha_02</th><td class="text-break pe-2"><?= $renderVal((string)$row['Enlace_Fecha_Formato_02']) ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>