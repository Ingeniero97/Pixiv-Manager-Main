/**
 * pxm/export.js — Configuración de columnas y motor de export a Excel.
 *
 * Depende de:
 *   - PXM.getSelectedIds, PXM.config.API_BASE, PXM.config.IS_REST,
 *     PXM.state                       (core.js)
 *   - PxmNotify                       (notifications.js)
 *   - XLSX (SheetJS)                  (footer.php CDN)
 *   - bootstrap.Modal                 (footer.php CDN)
 */
(function ($) {
  "use strict";

  // ═══ VARIABLES GLOBALES DE EXPORTACIÓN (AGRUPADAS) ═══
  const EXPORT_GROUPS = [
    {
      title: "Scraper / General",
      icon: "fa-robot",
      columns: [
        { id: "ORDEN", label: "ORDEN" },
        { id: "web_scraper_order", label: "Scraper Order" },
        { id: "web_scraper_start_url", label: "Scraper Start URL" },
        { id: "Estado", label: "Estado" },
      ],
    },
    {
      title: "Contenedor",
      icon: "fa-box",
      columns: [
        { id: "Contenedor_Tipo", label: "Tipo" },
        { id: "Contenedor_Numero_Hoja", label: "Número Hoja" },
        { id: "Contenedor_Alt_Album", label: "Alt Album" },
        { id: "Contenedor_Autor_Album", label: "Autor Album" },
        { id: "Contenedor_Enlace_Autor_Album", label: "Enlace Autor" },
        { id: "Contenedor_Nombre_Album", label: "Nombre Album" },
        { id: "Contenedor_Enlace_Album", label: "Enlace Album" },
        { id: "Contenedor_Enlace_Album_href", label: "Enlace Album Href" },
        { id: "Contenedor_ID_Enlace", label: "ID Enlace" },
      ],
    },
    {
      title: "Enlace (Pixiv)",
      icon: "fa-link",
      columns: [
        { id: "Enlace_Titulo", label: "Título" },
        { id: "Enlace_Img_Original", label: "Img Original" },
        { id: "Enlace_Alt_Img", label: "Alt Img" },
        { id: "Enlace_Ugoira", label: "Ugoira" },
        { id: "Enlace_Reading_works_or_Show_all", label: "Reading/Show" },
        { id: "Enlace_Tags_Completo", label: "Tags Completo" },
        { id: "Enlace_Tag_01", label: "Tag 01" },
        { id: "Enlace_Tag_02", label: "Tag 02" },
        { id: "Enlace_Tag_03", label: "Tag 03" },
        { id: "Enlace_Fecha_Formato_01", label: "Fecha 01" },
        { id: "Enlace_Fecha_Formato_02", label: "Fecha 02" },
      ],
    },
  ];

  // Aplanar el arreglo para obtener todos los IDs válidos por defecto
  const ALL_COLUMNS_IDS = EXPORT_GROUPS.flatMap((g) =>
    g.columns.map((c) => c.id),
  );

  let selectedColumns =
    JSON.parse(localStorage.getItem("pxm_export_columns")) || ALL_COLUMNS_IDS;
  let tag03Format = localStorage.getItem("pxm_tag03_format") || "1"; // Por defecto Opción 1

  function initExportModule() {
    // 1. Renderizar Modal (Agrupado por columnas)
    function renderColumnsModal() {
      let html = "";
      EXPORT_GROUPS.forEach((group) => {
        html += `<div class="col-md-4 mb-3">`;
        // Título de la columna (Scraper, Contenedor, Enlace)
        html += `<h6 class="fw-bold border-bottom pb-2 mb-3 text-secondary"><i class="fas ${group.icon} me-2"></i>${group.title}</h6>`;

        group.columns.forEach((c) => {
          const checked = selectedColumns.includes(c.id) ? "checked" : "";
          html += `
              <div class="form-check mb-2">
                  <input class="form-check-input export-col-chk" type="checkbox" value="${c.id}" id="chk_${c.id}" ${checked}>
                  <label class="form-check-label small" for="chk_${c.id}">${c.label}</label>
              </div>`;
        });
        html += `</div>`;
      });
      $("#columnsCheckboxList").html(html);
      $(`input[name="tag03Format"][value="${tag03Format}"]`).prop(
        "checked",
        true,
      );
    }

    $("#configColumnsBtn").on("click", function (e) {
      e.preventDefault();
      renderColumnsModal();
      bootstrap.Modal.getOrCreateInstance(
        document.getElementById("configColumnsModal"),
      ).show();
    });

    $("#selectAllCols").on("click", () =>
      $(".export-col-chk").prop("checked", true),
    );
    $("#deselectAllCols").on("click", () =>
      $(".export-col-chk").prop("checked", false),
    );

    $("#saveColumnsBtn").on("click", function () {
      selectedColumns = $(".export-col-chk:checked")
        .map((_, el) => $(el).val())
        .get();

      // Guardar formato de Tag 03
      tag03Format = $('input[name="tag03Format"]:checked').val() || "1";
      localStorage.setItem("pxm_tag03_format", tag03Format);

      if (selectedColumns.length === 0) {
        PxmNotify.warning("Debes seleccionar al menos una columna.");
        return;
      }
      localStorage.setItem(
        "pxm_export_columns",
        JSON.stringify(selectedColumns),
      );

      bootstrap.Modal.getOrCreateInstance(
        document.getElementById("configColumnsModal"),
      ).hide();
      PxmNotify.success("Configuración guardada.", 3000);
    });

    // 2. Funciones de Exportación
    $("#exportAllBtn").on("click", function (e) {
      e.preventDefault();
      startExport(null); // null significa todos
    });

    $("#exportSelectedBtn").on("click", function (e) {
      e.preventDefault();
      const ids = PXM.getSelectedIds();
      if (ids.length === 0) {
        PxmNotify.warning("No hay elementos seleccionados.");
        return;
      }
      startExport(ids);
    });

    // 3. Motor de Exportación (Lotes)
    async function startExport(idsArray) {
      const isSelected = idsArray !== null;
      const urlParams = new URLSearchParams(window.location.search);

      let baseUrl = `${PXM.config.API_BASE}export-data.php?estado=${PXM.config.IS_REST ? 0 : 1}&columns=${encodeURIComponent(JSON.stringify(selectedColumns))}`;
      if (isSelected) {
        baseUrl += `&ids=${encodeURIComponent(JSON.stringify(idsArray))}`;
      } else {
        const ms = urlParams.get("search") || "";
        const mu = urlParams.get("ugoira") || "";
        const ss = $("#clientSearch").val() || "";
        // ── NUEVO: propagar columnas de búsqueda ──
        const mc = urlParams.get("col") || "";
        const sc = $("#secondaryColSelect").val() || "";

        if (ms) baseUrl += `&main_search=${encodeURIComponent(ms)}`;
        if (mu) baseUrl += `&main_ugoira=${encodeURIComponent(mu)}`;
        if (ss) baseUrl += `&secondary_search=${encodeURIComponent(ss)}`;
        if (mc) baseUrl += `&main_col=${encodeURIComponent(mc)}`;
        if (sc) baseUrl += `&secondary_col=${encodeURIComponent(sc)}`;
      }

      const limit = 2000; // Lotes de 2000 (óptimo para 50k registros)
      let currentPage = 1;
      let allData = [];
      let totalReal = -1;
      let abortController = new AbortController();
      let cancelado = false;

      const $modal = bootstrap.Modal.getOrCreateInstance(
        document.getElementById("exportProgressModal"),
      );
      $modal.show();
      $("#exportProgressBar").css("width", "0%").text("0%");
      $("#exportProgressText").text("Iniciando descarga...");
      $("#exportEtaText").text("Calculando tiempo...");

      $("#cancelExportBtn")
        .off("click")
        .on("click", function () {
          cancelado = true;
          abortController.abort();
          $modal.hide();
          PxmNotify.warning("Exportación cancelada.");
        });

      const startTime = Date.now();

      try {
        while (totalReal === -1 || allData.length < totalReal) {
          if (cancelado) throw new Error("AbortError");

          const url = `${baseUrl}&page=${currentPage}&limit=${limit}`;
          const res = await fetch(url, {
            credentials: "same-origin",
            signal: abortController.signal,
          });
          const data = await res.json();

          if (!data.success)
            throw new Error(data.message || "Error en servidor");

          if (totalReal === -1) {
            totalReal = data.total_real;
            if (totalReal === 0) {
              $modal.hide();
              PxmNotify.warning("No hay datos para exportar.");
              return;
            }
          }

          allData = allData.concat(data.data);
          currentPage++;

          // Progreso
          const percent = Math.round((allData.length / totalReal) * 100);
          $("#exportProgressBar")
            .css("width", `${percent}%`)
            .text(`${percent}%`);
          $("#exportProgressText").text(
            `${allData.length.toLocaleString()} de ${totalReal.toLocaleString()} registros`,
          );

          const elapsed = (Date.now() - startTime) / 1000;
          const itemsPerSec = allData.length / elapsed;
          const etaSec = Math.ceil((totalReal - allData.length) / itemsPerSec);

          if (etaSec > 0) {
            const m = Math.floor(etaSec / 60);
            const s = etaSec % 60;
            $("#exportEtaText").text(
              `Tiempo restante: ${m > 0 ? m + "m " : ""}${s}s`,
            );
          }

          await new Promise((r) => setTimeout(r, 50)); // Pausa para no bloquear el hilo
        }

        // Generar Excel
        $modal.hide();
        PxmNotify.info("Generando archivo Excel, por favor espera...", 4000);

        setTimeout(() => {
          // ═══ Procesar Tag_03 según la opción seleccionada ═══
          if (
            selectedColumns.includes("Enlace_Tag_03") &&
            tag03Format === "2"
          ) {
            allData.forEach((row) => {
              if (row.Enlace_Tag_03) {
                // Separar por saltos de línea reales o literales (\n\n)
                let tags = row.Enlace_Tag_03.split(/[\r\n]+|\\n/).filter(
                  (t) => t.trim() !== "",
                );

                let formattedTags = tags.map((tag) => {
                  const regex =
                    /^(.*?[\u4e00-\u9faf\u3040-\u309f\u30a0-\u30ff\)\]])(\s*)([a-zA-Z].*)$/;
                  const match = tag.match(regex);

                  if (match) {
                    const asiatico = match[1].trim();
                    const ingles = match[3].trim();
                    return `[${asiatico}]|[${ingles}]`;
                  }
                  return tag;
                });

                // Volver a unir con el salto de línea literal
                row.Enlace_Tag_03 = formattedTags.join("\\n\\n");
              }
            });
          }

          const ws = XLSX.utils.json_to_sheet(allData);
          const wb = XLSX.utils.book_new();
          XLSX.utils.book_append_sheet(wb, ws, "Ilustraciones");
          const fecha = new Date().toISOString().slice(0, 10);
          XLSX.writeFile(wb, `ilustraciones_${fecha}.xlsx`);
          PxmNotify.success(
            `¡Exportación completa! ${allData.length.toLocaleString()} registros.`,
            6000,
          );
        }, 100);
      } catch (e) {
        $modal.hide();
        if (e.name !== "AbortError")
          PxmNotify.danger(e.message || "Error al exportar");
      }
    }
  }

  window.PXM = window.PXM || {};
  window.PXM.initExportModule = initExportModule;
})(jQuery);
