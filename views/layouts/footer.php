</div><!-- /.container.main-container -->

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Tagify para gestión de etiquetas interactivas -->
<link href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css" rel="stylesheet" type="text/css" />
<script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>

<!-- SheetJS: parseo de CSV/XLSX en el cliente (evita procesar archivos en PHP) -->
<script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>

<script src="assets/js/notifications.js"></script>

<!-- PXM core (obligatorio primero: expone window.PXM.config, .state y helpers) -->
<script src="assets/js/pxm/core.js"></script>

<!-- Módulos funcionales de la pantalla principal -->
<script src="assets/js/pxm/selection.js"></script>
<script src="assets/js/pxm/filters.js"></script>
<script src="assets/js/pxm/search.js"></script>
<script src="assets/js/pxm/render.js"></script>
<script src="assets/js/pxm/pagination.js"></script>
<script src="assets/js/pxm/row-actions.js"></script>
<script src="assets/js/pxm/bulk-actions.js"></script>
<script src="assets/js/pxm/bulk-records.js"></script>
<script src="assets/js/pxm/clear-data.js"></script>
<script src="assets/js/pxm/export.js"></script>

<!-- Delegación global (último de pxm: usa símbolos de los demás) -->
<script src="assets/js/pxm/events.js"></script>

<!-- Bootstrap y módulo de autores -->
<script src="assets/js/main.js"></script>
<script src="assets/js/autores.js"></script>

</body>
</html>