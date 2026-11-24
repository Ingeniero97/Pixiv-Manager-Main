/**
 * pxm/events.js — Delegación global de eventos de la pantalla principal.
 *
 * Último archivo de la carpeta pxm/ en cargarse: consume símbolos de todos
 * los módulos anteriores. NO inicializa los wizards (bulk-records,
 * clear-data, export): eso lo hace main.js con PXM.initBulkRecords(),
 * PXM.initClearData() y PXM.initExportModule().
 *
 * Depende de:
 *   - PXM.*                                        (todos los módulos)
 *   - PxmNotify                                     (notifications.js)
 */
(function ($) {
  "use strict";

  function init() {
    // --- Items por página ---
    $("#itemsPerPageTop, #itemsPerPageBottom").on("change", function () {
      PXM.changeItemsPerPage($(this).val());
    });

    // --- Limpiar búsqueda principal ---
    $("#clearSearchBtn").on("click", function () {
      const url = new URL(window.location.href);
      url.searchParams.delete("search");
      url.searchParams.set("page", "1");
      window.location.href = url.toString();
    });

    // --- Filtros Ugoira ---
    PXM.updateFiltrosCount();
    $(document).on("change", ".filtro-ugoira-checkbox", PXM.updateFiltrosCount);
    $("#aplicarFiltrosUgoira").on("click", PXM.applyFiltros);
    $("#clearFiltrosUgoira").on("click", PXM.clearFiltros);

    // --- Columnas de búsqueda (principal y secundario) ---
    $("#primaryColSelect").on("change", PXM.onPrimaryColChange);
    $("#secondaryColSelect").on("change", PXM.onSecondaryColChange);

    // --- Selección ---
    $("#selectAllButton").on("click", PXM.selectAll);
    $("#deselectAllButton").on("click", PXM.deselectAll);
    $("#checkAllHeader").on("change", function () {
      if ($(this).is(":checked")) PXM.selectAll();
      else PXM.deselectAll();
    });

    $(document).on("change", ".row-checkbox", PXM.updateSelectionUI);

    // --- Acciones de fila (delegadas) ---
    $(document).on("click", ".view-details", function () {
      PXM.loadDetails($(this).data("id"));
    });
    $(document).on("click", ".edit-item", function () {
      PXM.loadEditForm($(this).data("id"));
    });
    $(document).on("click", ".deactivate-item", function () {
      const id = $(this).data("id");
      if (PxmNotify.confirmSingle("desactivar", "", id)) {
        PXM.changeRecordEstado(id, 0);
      }
    });
    $(document).on("click", ".activate-item", function () {
      const id = $(this).data("id");
      const title = $(this).data("title") || "";
      if (PxmNotify.confirmSingle("restaurar", title, id)) {
        PXM.changeRecordEstado(id, 1);
      }
    });
    $(document).on("click", ".copy-link", function () {
      const link = $(this).data("link");
      if (!link) {
        PxmNotify.warning("Este registro no tiene enlace.");
        return;
      }
      PXM.copy(link);
      PxmNotify.copySuccess(1);
    });

    // --- Acciones masivas ---
    $("#copyLinksBtn").on("click", PXM.copySelectedLinks);
    $("#deactivateSelectedBtn").on("click", PXM.changeSelectedEstado);
    $("#confirmBatchDelete").on("click", PXM.batchByIds);

    // --- Modal copiar enlaces: botón "Copiar al portapapeles" ---
    $("#copyToClipboard").on("click", function () {
      const $ta = $("#linksToCopy");
      $ta.trigger("select");
      PXM.fallbackCopy($ta.val());
      const n = ($ta.val() || "").split("\n").filter(Boolean).length;
      PxmNotify.copySuccess(n);
    });

    // --- Búsqueda secundaria (AJAX + debounce) ---
    $("#clientSearch").on("input", function () {
      clearTimeout(PXM.state.searchTimeout);
      const term = $(this).val();
      PXM.state.secondarySearch = term;
      PXM.state.searchTimeout = setTimeout(
        () => PXM.performSearch(term, 1),
        300,
      );
    });

    $("#clearClientSearch").on("click", function () {
      $("#clientSearch").val("");
      PXM.state.secondarySearch = "";
      // La columna secundaria se respeta: solo se limpia el texto.
      // (Si el usuario quiere "Todas", cambia el <select> a la opción vacía.)
      const hasParams =
        new URLSearchParams(window.location.search).toString() !== "";
      if (hasParams) {
        window.location.reload();
      } else {
        PXM.performSearch("", 1);
      }
    });

    // Paginación por AJAX cuando hay búsqueda secundaria activa
    $(document).on(
      "click",
      "#paginationTop .page-link, #paginationBottom .page-link",
      function (e) {
        const page = parseInt($(this).data("page"), 10);
        if (!page) return;
        if (!PXM.state.secondarySearch) return;
        e.preventDefault();
        if (page === PXM.state.currentPage) return;
        PXM.state.currentPage = page;
        PXM.performSearch(PXM.state.secondarySearch, page);
      },
    );

    PXM.updateSelectionUI();

    // --- EDIT FORM SUBMIT (delegado) ---
    $(document).on("submit", "#editRecordForm", async function (e) {
      e.preventDefault();
      const $form = $(this);
      const orden = $form.data("orden");
      if (!orden) {
        PxmNotify.danger("Falta ORDEN en el formulario.");
        return;
      }

      const fields = {};
      $form.find("input[name], textarea[name], select[name]").each(function () {
        const name = $(this).attr("name");
        if (!name || name === "ORDEN") return;
        fields[name] = $(this).val();
      });

      // ═══ EXTRAER DATOS DE TAGIFY ═══
      const tagify03 = $form.data("tagify03");
      if (tagify03) {
        // Extraemos los valores y los unimos con el texto literal \n\n
        const tagsArray = tagify03.value.map((t) => t.value);
        fields["Enlace_Tag_03"] = tagsArray.join("\\n\\n");
      }

      const $btn = $form.find("#saveEditBtn");
      const orig = $btn.html();
      $btn
        .html(
          '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...',
        )
        .prop("disabled", true);

      try {
        const res = await fetch(PXM.config.API_BASE + "update-record.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "same-origin",
          body: JSON.stringify({ orden, fields }),
        });
        const data = await res.json();

        if (data.success) {
          PxmNotify.recordUpdated();
          bootstrap.Modal.getOrCreateInstance(
            document.getElementById("editModal"),
          ).hide();
          setTimeout(() => PXM.refreshCurrentView(), 300);
        } else {
          PxmNotify.danger(data.message || "Error al actualizar.");
        }
      } catch (err) {
        PxmNotify.connectionError("guardar");
      } finally {
        $btn.html(orig).prop("disabled", false);
      }
    });

    // ═══ Botones de copiar en el modal de Detalles ═══
    $(document).on("click", ".btn-copy-detail", function (e) {
      e.preventDefault();
      const textToCopy = $(this).data("copy");
      const type = $(this).data("type");

      if (textToCopy) {
        // Copiar al portapapeles
        if (typeof PXM.copy === "function") {
          PXM.copy(textToCopy);
        } else {
          navigator.clipboard.writeText(textToCopy);
        }

        // Armar el mensaje según el tipo
        let label = "Texto";
        if (type === "autor") {
          label = "Autor";
        } else if (type === "enlace") {
          label = "Enlace";
        }

        // Envolver el valor en <strong> para la negrita
        const msg = `${label} copiado: <strong>${PXM.escapeHtml(textToCopy)}</strong>`;

        // Mostrar la notificación
        if (typeof PxmNotify !== "undefined") {
          PxmNotify.success(msg, 3000);
        } else {
          showNotification(msg, "success", 3000);
        }
      }
    });

    // ═══ NEW: Vista previa en vivo de Tag_03 en el formulario de Editar ═══
    $(document).on("input", "#input_tag03", function () {
      const texto = $(this).val();
      const $preview = $("#preview_tag03");
      $preview.empty();

      if (!texto.trim()) return;

      // Separar por saltos de línea reales o literales (\n)
      const tags = texto.split(/[\r\n]+|\\n/).filter((t) => t.trim() !== "");

      tags.forEach((tag) => {
        const tagLimpio = tag.trim();

        // Separar texto asiático de inglés
        let htmlPill = "";
        const regexMixto =
          /^(.*?[\u4e00-\u9faf\u3040-\u309f\u30a0-\u30ff\)\]])(\s*)([a-zA-Z].*)$/;
        const match = tagLimpio.match(regexMixto);

        if (match) {
          const asiatico = match[1].trim();
          const ingles = match[3].trim();
          htmlPill = `<span class="badge rounded-pill border border-primary p-0 d-inline-flex align-items-stretch overflow-hidden" style="font-size:0.8rem; font-weight:500; background-color: transparent;">
                    <span style="background-color: #0d6efd; color: white; padding: 0.4em 0.8em; display: flex; align-items: center;">${PXM.escapeHtml(asiatico)}</span>
                    <span style="background-color: rgba(13, 110, 253, 0.15); color: #0d6efd; padding: 0.4em 0.8em; display: flex; align-items: center;">
                        <span class="fw-bold me-2 text-secondary opacity-75">|</span>${PXM.escapeHtml(ingles)}
                    </span>
                </span>`;
        } else {
          const esAsiatico = /[\u4e00-\u9faf\u3040-\u309f\u30a0-\u30ff]/.test(
            tagLimpio,
          );
          if (esAsiatico) {
            htmlPill = `<span class="badge rounded-pill border border-primary text-white" style="font-size:0.8rem; font-weight:500; background-color: #0d6efd; padding: 0.5em 0.8em;">${PXM.escapeHtml(tagLimpio)}</span>`;
          } else {
            htmlPill = `<span class="badge rounded-pill border border-primary text-primary" style="font-size:0.8rem; font-weight:500; background-color: rgba(13, 110, 253, 0.15); padding: 0.5em 0.8em;">${PXM.escapeHtml(tagLimpio)}</span>`;
          }
        }
        $preview.append(htmlPill);
      });
    });

    // Disparar el evento al abrir el modal para que se vea la vista previa inicial
    $(document).on("shown.bs.modal", "#editModal", function () {
      $("#input_tag03").trigger("input");
    });
  }

  window.PXM = window.PXM || {};
  window.PXM.init = init;
})(jQuery);
