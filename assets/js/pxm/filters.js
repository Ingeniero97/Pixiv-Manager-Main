/**
 * pxm/filters.js — Filtros Ugoira y items-per-page.
 */
(function ($) {
  "use strict";

  function updateFiltrosCount() {
    const count = $(".filtro-ugoira-checkbox:checked").length;
    $("#filtrosUgoiraCount").text(count);
    $("#filtrosUgoiraBtn")
      .toggleClass("btn-outline-primary", count > 0)
      .toggleClass("btn-outline-secondary", count === 0);
  }

  function applyFiltros() {
    const seleccionados = $(".filtro-ugoira-checkbox:checked")
      .map((_, el) => $(el).val())
      .get();

    const url = new URL(window.location.href);
    if (seleccionados.length > 0) {
      url.searchParams.set("ugoira", seleccionados.join(","));
    } else {
      url.searchParams.delete("ugoira");
    }
    url.searchParams.set("page", "1");
    window.location.href = url.toString();
  }

  function clearFiltros() {
    $(".filtro-ugoira-checkbox").prop("checked", false);
    updateFiltrosCount();
  }

  function changeItemsPerPage(newValue) {
    const url = new URL(window.location.href);
    url.searchParams.set("length", String(newValue));
    url.searchParams.delete("page");
    window.location.href = url.toString();
  }

  // ── NUEVO: cambio de columna del buscador principal ──
  function onPrimaryColChange() {
    const term = $("input[name='search']").val() || "";
    if (term.trim() !== "") {
      // Submit nativo del form GET para aplicar ?search=X&col=Y
      $("#searchForm").get(0).submit();
    }
  }

  // ── NUEVO: cambio de columna del buscador secundario ──
  function onSecondaryColChange() {
    const val = $("#secondaryColSelect").val() || "";
    PXM.state.secondaryCol = val;

    const term = $("#clientSearch").val() || "";
    if (term.trim() !== "") {
      PXM.performSearch(term, 1);
    }
  }

  window.PXM = window.PXM || {};
  window.PXM.updateFiltrosCount = updateFiltrosCount;
  window.PXM.applyFiltros = applyFiltros;
  window.PXM.clearFiltros = clearFiltros;
  window.PXM.changeItemsPerPage = changeItemsPerPage;
  window.PXM.onPrimaryColChange = onPrimaryColChange;
  window.PXM.onSecondaryColChange = onSecondaryColChange;
})(jQuery);
