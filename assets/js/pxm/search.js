/**
 * pxm/search.js — Búsqueda secundaria (AJAX, sin recargar).
 *
 * Se apoya en:
 *   - PXM.state, PXM.escapeHtml, PXM.setLoading, PXM.numberFormat  (core.js)
 *   - PXM.renderServerResults, PXM.updateServerPagination          (render.js / pagination.js)
 *   - PXM.updateHeaderBadges                                       (core.js)
 */
(function ($) {
  "use strict";

  async function performSearch(searchTerm, page) {
    if (PXM.state.loading) return;
    PXM.setLoading(true);

    const $tbody = $("#resultsTable tbody");
    $tbody.html(`
            <tr>
                <td colspan="11" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Buscando...</span>
                    </div>
                    <p class="mt-3">Filtrando resultados: "${PXM.escapeHtml(searchTerm || "todos")}"</p>
                </td>
            </tr>
        `);

    const urlParams = new URLSearchParams(window.location.search);
    const main_search = urlParams.get("search") || "";
    const main_ugoira = urlParams.get("ugoira") || "";

    let url =
      `backend-search.php?search=${encodeURIComponent(searchTerm)}` +
      `&page=${page}` +
      `&per_page=${PXM.state.itemsPerPage}` +
      `&main_estado=${PXM.config.IS_REST ? 0 : 1}`;

    if (main_search) url += `&main_search=${encodeURIComponent(main_search)}`;
    if (main_ugoira) url += `&main_ugoira=${encodeURIComponent(main_ugoira)}`;
    // ── NUEVO: propagar la columna del principal y la del secundario ──
    if (PXM.state.primaryCol) {
      url += `&main_col=${encodeURIComponent(PXM.state.primaryCol)}`;
    }
    if (PXM.state.secondaryCol) {
      url += `&secondary_col=${encodeURIComponent(PXM.state.secondaryCol)}`;
    }

    try {
      const res = await fetch(url, { credentials: "same-origin" });
      if (!res.ok) throw new Error("HTTP " + res.status);
      const data = await res.json();

      if (!data.success)
        throw new Error(data.message || "Error en la búsqueda");

      PXM.renderServerResults(data.data || []);
      PXM.updateServerPagination(
        data.total_pages || 1,
        data.page || page,
        data.total_result || 0,
      );

      if (typeof data.total !== "undefined") {
        PXM.updateHeaderBadges(
          data.total,
          data.activos || 0,
          data.inactivos || 0,
        );
      }

      let statsText = `Mostrando ${data.data ? data.data.length : 0} de ${PXM.numberFormat(data.total_result)} registros`;
      if (searchTerm) statsText += ` (Filtro secundario: "${searchTerm}")`;
      $("#clientSearchStats").text(statsText);
    } catch (error) {
      $tbody.html(`
                <tr>
                    <td colspan="11" class="text-center py-4">
                        <i class="fas fa-exclamation-triangle fa-2x text-danger mb-3"></i>
                        <h5>Error al filtrar</h5>
                        <p class="text-muted">${PXM.escapeHtml(error.message)}</p>
                        <button class="btn btn-primary mt-2" onclick="location.reload()">
                            <i class="fas fa-sync-alt me-1"></i> Recargar
                        </button>
                    </td>
                </tr>
            `);
      PxmNotify.danger("Error al filtrar: " + PXM.escapeHtml(error.message));
    } finally {
      PXM.setLoading(false);
    }
  }

  /**
   * Fix #5: refresca la tabla actual sin recargar la página.
   * Preserva:
   *   - filtros de la URL (search, ugoira)
   *   - página actual (state.currentPage, sincronizado con la URL en core.js
   *     y con la respuesta del backend tras cada performSearch)
   *   - búsqueda secundaria (state.secondarySearch)
   * Si hay una búsqueda en curso, espera 100ms y reintenta (evita race condition).
   */
  function refreshCurrentView() {
    if (PXM.state.loading) {
      setTimeout(refreshCurrentView, 100);
      return;
    }
    PXM.performSearch(PXM.state.secondarySearch, PXM.state.currentPage);
  }

  window.PXM = window.PXM || {};
  window.PXM.performSearch = performSearch;
  window.PXM.refreshCurrentView = refreshCurrentView;
})(jQuery);
