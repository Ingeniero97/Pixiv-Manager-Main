/**
 * pxm/search-col-tomselect.js — Opción C: Tom Select.
 * Solo se carga si SEARCH_COL_UI === 'tomselect' (y el CDN correspondiente).
 */
(function ($) {
  "use strict";

  function initTomSelects() {
    if (typeof TomSelect === "undefined") {
      console.error("[search-col-tomselect] Tom Select no está cargado.");
      return;
    }

    $('select[data-role="search-col-tomselect"]').each(function () {
      const $sel = $(this);
      if ($sel[0].tomselect) return; // ya inicializado

      const ts = new TomSelect(this, {
        create: false,
        allowEmptyOption: true,
        placeholder: "Todas las columnas",
        maxItems: 1,
        sortField: { field: "text", direction: "asc" },
        onInitialize: function () {
          this.control_input.setAttribute("placeholder", "Buscar columna...");
        },
        onChange: function () {
          // TomSelect sincroniza el <select> oculto y dispara change nativo.
          // Nada extra que hacer: los handlers de jQuery ya escuchan.
        },
      });
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initTomSelects);
  } else {
    initTomSelects();
  }
})(jQuery);