/**
 * pxm/clear-data.js — Wizard "Limpiar datos de columnas".
 *
 * Depende de:
 *   - PXM.getSelectedIds, PXM.config.API_BASE, PXM.config.IS_REST,
 *     PXM.state, PXM.performSearch    (core.js / search.js)
 *   - PxmNotify                       (notifications.js)
 *   - bootstrap.Modal                 (footer.php CDN)
 */
(function ($) {
  "use strict";

  // Lista de columnas "limpiables". Se excluyen los identificadores.
  const CLEARABLE_COLUMNS = [
    { id: "Contenedor_Tipo" },
    { id: "Contenedor_Numero_Hoja" },
    { id: "Contenedor_Alt_Album" },
    { id: "Contenedor_Autor_Album" },
    { id: "Contenedor_Enlace_Autor_Album" },
    { id: "Contenedor_Nombre_Album" },
    { id: "Contenedor_Enlace_Album" },
    { id: "Contenedor_ID_Enlace" },
    { id: "Enlace_Titulo" },
    { id: "Enlace_Img_Original" },
    { id: "Enlace_Alt_Img" },
    { id: "Enlace_Ugoira" },
    { id: "Enlace_Reading_works_or_Show_all" },
    { id: "Enlace_Tags_Completo" },
    { id: "Enlace_Tag_01" },
    { id: "Enlace_Tag_02" },
    { id: "Enlace_Tag_03" },
    { id: "Enlace_Fecha_Formato_01" },
    { id: "Enlace_Fecha_Formato_02" },
  ];

  function initClearData() {
    $(document).on("click", "#clearDataBtn", function (e) {
      e.preventDefault();

      // Renderizar checkboxes
      let html = "";
      CLEARABLE_COLUMNS.forEach((c) => {
        // No permitir limpiar ORDEN ni el Enlace_href (identificadores)
        if (c.id === "ORDEN" || c.id === "Contenedor_Enlace_Album_href") return;
        html += `
          <div class="col-md-6 mb-2">
            <div class="form-check">
              <input class="form-check-input clear-data-chk" type="checkbox" value="${c.id}" id="clr_${c.id}">
              <label class="form-check-label small text-break" for="clr_${c.id}">
                <code>${c.id}</code>
              </label>
            </div>
          </div>`;
      });
      $("#clearDataCheckboxList").html(html);

      // Resetear a paso 1
      $("#clearDataStep1, #clearDataFooterStep1").removeClass("d-none");
      $("#clearDataStep2, #clearDataFooterStep2").addClass("d-none");
      $("#clearDataSteps .nav-link").removeClass("active disabled");
      $("#clearDataSteps .nav-link[data-step='1']").addClass("active");
      $("#clearDataSteps .nav-link[data-step='2']").addClass("disabled");
      $("#clearDataNextBtn").prop("disabled", true);

      bootstrap.Modal.getOrCreateInstance(
        document.getElementById("clearDataModal"),
      ).show();
    });

    // Sincronizar botón de siguiente
    $(document).on("change", ".clear-data-chk", function () {
      const count = $(".clear-data-chk:checked").length;
      $("#clearDataNextBtn").prop("disabled", count === 0);
    });

    $(document).on("click", "#clearDataSelectAll", () =>
      $(".clear-data-chk").prop("checked", true).trigger("change"),
    );
    $(document).on("click", "#clearDataDeselectAll", () =>
      $(".clear-data-chk").prop("checked", false).trigger("change"),
    );

    // Navegación
    $("#clearDataNextBtn").on("click", function () {
      const cols = $(".clear-data-chk:checked").length;

      // Calcular registros afectados (Seleccionados o Todos los filtrados)
      let countRows = 0;
      const ids = PXM.getSelectedIds();

      if (ids.length > 0) {
        countRows = ids.length;
      } else {
        // Leer total filtrado del DOM
        const totalText = $("#toolbarTotalCount").text() || "0";
        countRows = parseInt(totalText.replace(/,/g, ""), 10) || 0;
      }

      $("#clearDataCountCols").text(cols);
      $("#clearDataCountRows").text(countRows.toLocaleString());

      $("#clearDataStep1, #clearDataFooterStep1").addClass("d-none");
      $("#clearDataStep2, #clearDataFooterStep2").removeClass("d-none");

      $("#clearDataSteps .nav-link").removeClass("active");
      $("#clearDataSteps .nav-link[data-step='1']").addClass("disabled");
      $("#clearDataSteps .nav-link[data-step='2']").removeClass(
        "disabled active",
      );
    });

    $("#clearDataBackBtn").on("click", function () {
      $("#clearDataStep1, #clearDataFooterStep1").removeClass("d-none");
      $("#clearDataStep2, #clearDataFooterStep2").addClass("d-none");

      $("#clearDataSteps .nav-link").removeClass("disabled");
      $("#clearDataSteps .nav-link[data-step='1']").addClass("active");
      $("#clearDataSteps .nav-link[data-step='2']").removeClass("active");
    });

    // Aplicar
    $("#clearDataApplyBtn").on("click", async function () {
      const $btn = $(this);
      const orig = $btn.html();
      $btn
        .prop("disabled", true)
        .html(
          '<span class="spinner-border spinner-border-sm me-1"></span> Borrando...',
        );

      const columns = $(".clear-data-chk:checked")
        .map((_, el) => $(el).val())
        .get();
      const ids = PXM.getSelectedIds();

      const urlParams = new URLSearchParams(window.location.search);
      const payload = {
        columns: columns,
        ids: ids.length > 0 ? ids : null,
        filters: {
          estado: PXM.config.IS_REST ? 0 : 1,
          main_search: urlParams.get("search") || "",
          main_ugoira: urlParams.get("ugoira") || "",
          secondary_search: $("#clientSearch").val() || "",
          // ── NUEVO: propagar columnas de búsqueda ──
          primary_col: urlParams.get("col") || "",
          secondary_col: $("#secondaryColSelect").val() || "",
        },
      };

      try {
        const res = await fetch(PXM.config.API_BASE + "clear-data.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "same-origin",
          body: JSON.stringify(payload),
        });
        const data = await res.json();

        if (!data.success) throw new Error(data.message || "Error");

        PxmNotify.success(
          `Se limpiaron ${data.changed} registro(s) correctamente.`,
          5000,
        );

        bootstrap.Modal.getOrCreateInstance(
          document.getElementById("clearDataModal"),
        ).hide();

        // Fix #5: refrescar la tabla vía AJAX sin recargar la página
        PXM.refreshCurrentView();
      } catch (e) {
        PxmNotify.danger(e.message || "Error de conexión", 5000);
      } finally {
        $btn.prop("disabled", false).html(orig);
      }
    });
  }

  window.PXM = window.PXM || {};
  window.PXM.initClearData = initClearData;
})(jQuery);
