/**
 * pxm/search-col-dropdown.js — Opción A: dropdown custom con input de búsqueda.
 * Solo se carga si SEARCH_COL_UI === 'dropdown'.
 */
(function ($) {
  "use strict";

  function initDropdowns() {
    $(".search-col-dropdown").each(function () {
      const $wrap   = $(this);
      const $hidden = $wrap.find(".search-col-value");
      const $label  = $wrap.find(".search-col-label");
      const $filter = $wrap.find(".search-col-filter");
      const $list   = $wrap.find(".search-col-list");
      if (!$hidden.length || !$list.length) return;

      // Click en una opción
      $list.on("click", ".search-col-option, .dropdown-item", function (e) {
        e.preventDefault();
        const value = $(this).data("value") || "";
        const text  = value === "" ? "Todas las columnas" : value;

        $hidden.val(value).trigger("change");
        $label.text(text);
        $list.find(".dropdown-item").removeClass("active");
        $(this).addClass("active");

        // Cerrar dropdown
        const $btn = $wrap.find('[data-bs-toggle="dropdown"]');
        if ($btn.length && window.bootstrap) {
          bootstrap.Dropdown.getOrCreateInstance($btn[0]).hide();
        }

        // Reset del filtro para la próxima apertura
        $filter.val("");
        $list.find("li").show();
      });

      // Filtro de texto
      $filter.on("input", function () {
        const q = String($(this).val()).trim().toLowerCase();
        if (q === "") {
          $list.find("li").show();
          return;
        }
        $list.find("li").each(function () {
          const $li = $(this);
          if ($li.hasClass("search-col-group-title")) return;
          const $opt = $li.find(".dropdown-item");
          if (!$opt.length) return;
          const val = String($opt.data("value") || "").toLowerCase();
          const txt = $opt.text().toLowerCase();
          $li.toggle(val.includes(q) || txt.includes(q));
        });
      });

      // Focus automático al abrir
      $wrap.on("shown.bs.dropdown", function () {
        $filter.trigger("focus");
      });
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initDropdowns);
  } else {
    initDropdowns();
  }
})(jQuery);