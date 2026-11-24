/**
 * pxm/bulk-records.js — Wizard "Remplazar registros en lote" (CSV/XLSX).
 *
 * Depende de:
 *   - PXM.escapeHtml, PXM.config.API_BASE  (core.js)
 *   - PxmNotify                            (notifications.js)
 *   - XLSX (SheetJS)                       (footer.php CDN)
 *   - bootstrap.Modal                      (footer.php CDN)
 */
(function ($) {
  "use strict";

  const DB_COLUMNS = [
    { id: "Contenedor_Tipo", label: "Tipo" },
    { id: "Contenedor_Numero_Hoja", label: "Número Hoja" },
    { id: "Contenedor_Alt_Album", label: "Alt Album" },
    { id: "Contenedor_Autor_Album", label: "Autor Album" },
    { id: "Contenedor_Enlace_Autor_Album", label: "Enlace Autor" },
    { id: "Contenedor_Nombre_Album", label: "Nombre Album" },
    { id: "Contenedor_Enlace_Album", label: "Enlace Album" },
    {
      id: "Contenedor_Enlace_Album_href",
      label: "Enlace Album Href (Identificador)",
    },
    { id: "Contenedor_ID_Enlace", label: "ID Enlace" },
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
    { id: "Estado", label: "Estado (1 o 0)" },
  ];

  const bulkRecState = {
    headers: [],
    dataRows: [],
    colIdentificador: -1,
    mappedColumns: {}, // { colIndex: 'db_column_id' }
    parsing: false,
  };

  function bulkRecReset() {
    bulkRecState.headers = [];
    bulkRecState.dataRows = [];
    bulkRecState.colIdentificador = -1;
    bulkRecState.mappedColumns = {};
    $("#bulkRecFileInput").val("");
    $("#bulkRecDropZone").removeClass("d-none");
    $("#bulkRecFileInfo").addClass("d-none");
    $("#bulkRecFileInfoText").text("");
    $("#bulkRecNextFromFile").prop("disabled", true);
    $("#bulkRecPreviewBody").empty();
    bulkRecShowStep(1);
  }

  function bulkRecShowStep(step) {
    $("#bulkRecStep1, #bulkRecStep2, #bulkRecStep3").addClass("d-none");
    $("#bulkRecStep" + step).removeClass("d-none");
    $("#bulkRecFooterStep1, #bulkRecFooterStep2, #bulkRecFooterStep3").addClass(
      "d-none",
    );
    $("#bulkRecFooterStep" + step).removeClass("d-none");

    $("#bulkRecordsSteps .nav-link").each(function () {
      const s = parseInt($(this).data("step"), 10);
      $(this).removeClass("active disabled");
      if (s === step) $(this).addClass("active");
      else if (s > step) $(this).addClass("disabled");
    });

    const $dialog = $("#bulkRecordsDialog");
    $dialog.removeClass("modal-sm modal-md modal-lg modal-xl");
    if (step === 1) $dialog.addClass("modal-md");
    else if (step === 2) $dialog.addClass("modal-lg");
    else $dialog.addClass("modal-xl");
  }

  // ═══ FUNCIÓN LOCAL PARA LEER ARCHIVOS EXCEL/CSV ═══
  function parseFileToMatrix(file) {
    return new Promise((resolve, reject) => {
      if (typeof XLSX === "undefined") {
        return reject(new Error("SheetJS no está cargado. Revisa footer.php."));
      }
      if (file.size > 100 * 1024 * 1024) {
        return reject(new Error("El archivo supera 100 MB."));
      }
      const reader = new FileReader();
      reader.onerror = () => reject(new Error("No se pudo leer el archivo."));
      reader.onload = (e) => {
        try {
          const bytes = new Uint8Array(e.target.result);
          const wb = XLSX.read(bytes, { type: "array" });
          const sheet = wb.Sheets[wb.SheetNames[0]];
          const matrix = XLSX.utils.sheet_to_json(sheet, {
            header: 1,
            raw: false,
            defval: "",
            blankrows: false,
          });
          const filtered = matrix.filter((r) =>
            r.some((c) => String(c).trim() !== ""),
          );
          resolve(filtered);
        } catch (err) {
          reject(new Error("Formato de archivo no reconocido."));
        }
      };
      reader.readAsArrayBuffer(file);
    });
  }

  function initBulkRecords() {
    $(document).on("click", "#bulkRecordsBtn", function () {
      bulkRecReset();
      bootstrap.Modal.getOrCreateInstance(
        document.getElementById("bulkRecordsModal"),
      ).show();
    });

    // Cargar Archivo
    $("#bulkRecFileInput").on("change", async function () {
      const file = this.files && this.files[0];
      if (!file || bulkRecState.parsing) return;
      bulkRecState.parsing = true;

      try {
        const matrix = await parseFileToMatrix(file);
        if (matrix.length < 2) {
          PxmNotify.warning("El archivo debe tener encabezado y datos.", 5000);
          return;
        }

        bulkRecState.headers = matrix[0].map((h) => String(h || ""));
        bulkRecState.dataRows = matrix.slice(1);

        if (bulkRecState.dataRows.length > 500000) {
          bulkRecState.dataRows = bulkRecState.dataRows.slice(0, 500000);
          PxmNotify.warning("Límite de 500,000 filas alcanzado.", 5000);
        }

        // 1. Auto-detectar identificador
        let identifierIndex = -1;
        bulkRecState.headers.forEach((h, i) => {
          const hl = h.trim().toLowerCase();
          if (
            hl === "contenedor_enlace_album_href" ||
            hl === "enlace_href" ||
            hl === "url" ||
            hl === "orden"
          ) {
            if (identifierIndex === -1) identifierIndex = i;
          }
        });

        if (identifierIndex === -1) {
          PxmNotify.danger(
            "No se encontró la columna 'Contenedor_Enlace_Album_href' o 'ORDEN'.",
            8000,
          );
          return;
        }

        bulkRecState.colIdentificador = identifierIndex;

        // 2. Poblar Selector de Identificador (Solo ORDEN y Enlace_href)
        let selHtml = "";
        bulkRecState.headers.forEach((h, i) => {
          const hl = h.trim().toLowerCase();
          if (
            hl === "orden" ||
            hl === "contenedor_enlace_album_href" ||
            hl === "enlace_href"
          ) {
            selHtml += `<option value="${i}" ${i === identifierIndex ? "selected" : ""}>${h}</option>`;
          }
        });
        $("#bulkRecColIdentificador").html(selHtml);

        // 3. Construir Tabla de Mapeo (Base de Datos -> Excel)
        let mapHtml = "";

        // Generar las opciones del selector de Excel (excluyendo el identificador)
        let excelOptionsHtml = `<option value="">-- No usar --</option>`;
        bulkRecState.headers.forEach((h, i) => {
          if (i !== identifierIndex) {
            excelOptionsHtml += `<option value="${i}">${PXM.escapeHtml(h || `Columna ${i + 1}`)}</option>`;
          }
        });

        // Iterar sobre las columnas de la Base de Datos
        DB_COLUMNS.forEach((dbCol) => {
          // Ignorar columnas que son identificadores (ya se manejan arriba)
          if (
            dbCol.id === "Contenedor_Enlace_Album_href" ||
            dbCol.id === "ORDEN"
          )
            return;

          const dbColClean = dbCol.id.toLowerCase();
          const dbLabelClean = dbCol.label.toLowerCase();

          // Intentar auto-detectar si esta columna existe en el Excel
          let autoExcelIdx = "";
          bulkRecState.headers.forEach((h, i) => {
            if (i === identifierIndex) return;
            const hClean = h.trim().toLowerCase();
            if (hClean === dbColClean || hClean === dbLabelClean) {
              autoExcelIdx = i;
            }
          });

          // Construir las opciones marcando la auto-detectada
          let finalOptions = excelOptionsHtml.replace(
            `value="${autoExcelIdx}"`,
            `value="${autoExcelIdx}" selected`,
          );

          mapHtml += `
            <tr>
                <td>
                    <input type="checkbox" class="form-check-input bulk-rec-chk" value="${dbCol.id}" ${autoExcelIdx !== "" ? "checked" : ""} data-dbcol="${dbCol.id}">
                </td>
                <td class="text-start text-break small fw-bold text-primary"><code>${dbCol.id}</code></td>
                <td>
                    <select class="form-select form-select-sm bulk-rec-select" data-dbcol="${dbCol.id}">
                        ${finalOptions}
                    </select>
                </td>
            </tr>
          `;
        });

        $("#bulkRecMappingTableBody").html(mapHtml);

        // Sincronizar checkbox con el select
        $(document)
          .off("change", ".bulk-rec-select")
          .on("change", ".bulk-rec-select", function () {
            const dbcol = $(this).data("dbcol");
            const val = $(this).val();
            $(`.bulk-rec-chk[data-dbcol="${dbcol}"]`).prop(
              "checked",
              val !== "",
            );
          });

        $(document)
          .off("change", ".bulk-rec-chk")
          .on("change", ".bulk-rec-chk", function () {
            const dbcol = $(this).data("dbcol");
            const isChecked = $(this).is(":checked");
            const $select = $(`.bulk-rec-select[data-dbcol="${dbcol}"]`);
            if (!isChecked) {
              $select.val("");
            } else if ($select.val() === "") {
              // Si se marca, sugerir la primera columna de Excel disponible
              $select.val($select.find("option:not(:first)").val());
            }
          });

        $("#bulkRecFileInfoText").text(
          `${bulkRecState.dataRows.length.toLocaleString()} filas · "${file.name}"`,
        );
        $("#bulkRecFileInfo").removeClass("d-none");
        $("#bulkRecDropZone").addClass("d-none");
        $("#bulkRecNextFromFile").prop("disabled", false);
      } catch (err) {
        PxmNotify.danger(err.message || "Error al procesar archivo.", 5000);
      } finally {
        bulkRecState.parsing = false;
      }
    });

    $(document).on("click", "#bulkRecClearFileBtn", bulkRecReset);

    // Deseleccionar todas las columnas
    $(document).on("click", "#bulkRecDeselectAll", function () {
      $(".bulk-rec-chk").prop("checked", false);
    });

    // Seleccionar todas las columnas
    $(document).on("click", "#bulkRecSelectAll", function () {
      $(".bulk-rec-chk").prop("checked", true);
    });

    // Drag & Drop (reutilizando la lógica)
    const $drop = $("#bulkRecDropZone");
    $drop.on("dragover dragenter", function (e) {
      e.preventDefault();
      e.stopPropagation();
      $(this).addClass("dragover");
    });
    $drop.on("dragleave dragend drop", function (e) {
      e.preventDefault();
      e.stopPropagation();
      $(this).removeClass("dragover");
    });
    $drop.on("drop", function (e) {
      const dt = e.originalEvent.dataTransfer;
      if (!dt || !dt.files || !dt.files.length) return;
      const dataTransfer = new DataTransfer();
      dataTransfer.items.add(dt.files[0]);
      $("#bulkRecFileInput")[0].files = dataTransfer.files;
      $("#bulkRecFileInput").trigger("change");
    });
    $(document).on("click", "#bulkRecDropZone", function (e) {
      if ($(e.target).is("label") || $(e.target).closest("label").length > 0)
        return;
      $("#bulkRecFileInput")[0].click();
    });

    // Navegación
    $("#bulkRecNextFromFile").on("click", () => bulkRecShowStep(2));
    $("#bulkRecBackToFile").on("click", () => bulkRecShowStep(1));
    $("#bulkRecBackToColumns").on("click", () => bulkRecShowStep(2));

    // Vista Previa
    $("#bulkRecPreviewBtn").on("click", async function () {
      const $btn = $(this);
      const orig = $btn.html();
      $btn
        .prop("disabled", true)
        .html(
          '<span class="spinner-border spinner-border-sm me-1"></span> Analizando...',
        );

      // Obtener el mapeo manual de la tabla (DbCol -> ExcelIdx)
      let finalMap = {};
      $(".bulk-rec-chk:checked").each(function () {
        const dbCol = $(this).data("dbcol");
        const excelIdx = $(`.bulk-rec-select[data-dbcol="${dbCol}"]`).val();
        if (excelIdx !== "") {
          finalMap[dbCol] = parseInt(excelIdx, 10);
        }
      });

      if (Object.keys(finalMap).length === 0) {
        PxmNotify.warning(
          "Selecciona al menos una columna para actualizar.",
          5000,
        );
        $btn.prop("disabled", false).html(orig);
        return;
      }

      const idIndex = parseInt($("#bulkRecColIdentificador").val(), 10);
      const identifierField =
        bulkRecState.headers[idIndex].trim().toLowerCase() === "orden"
          ? "ORDEN"
          : "Contenedor_Enlace_Album_href";

      // Armar payload
      const pairs = bulkRecState.dataRows
        .map((row) => {
          const obj = {
            identifier: String(row[idIndex] || "").trim(),
            fields: {},
          };

          Object.keys(finalMap).forEach((dbCol) => {
            const excelIdx = finalMap[dbCol];
            obj.fields[dbCol] = String(row[excelIdx] || "").trim();
          });

          return obj;
        })
        .filter((p) => p.identifier !== "");

      try {
        let allDetails = [];
        let totalChanged = 0;
        let totalNotFound = 0;
        const chunkSize = 3000;

        for (let i = 0; i < pairs.length; i += chunkSize) {
          const chunk = pairs.slice(i, i + chunkSize);
          $btn.html(
            `<span class="spinner-border spinner-border-sm me-1"></span> Analizando bloque ${Math.floor(i / chunkSize) + 1}...`,
          );

          const res = await fetch(
            PXM.config.API_BASE + "bulk-update-records.php",
            {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              credentials: "same-origin",
              body: JSON.stringify({
                pairs: chunk,
                dry_run: true,
                identifier_field: identifierField,
              }),
            },
          );
          const data = await res.json();

          if (!data.success)
            throw new Error(data.message || "Error en servidor");

          totalChanged += data.changed || 0;
          totalNotFound += data.not_found || 0;
          if (data.details) allDetails = allDetails.concat(data.details);

          await new Promise((r) => setTimeout(r, 50));
        }

        // 1. Actualizar contadores reales
        $("#bulkRecCountTotal").text(pairs.length || 0);
        $("#bulkRecCountChanged").text(totalChanged || 0);
        $("#bulkRecCountNotFound").text(totalNotFound || 0);

        // 2. Obtener columnas seleccionadas
        let columnasA_Modificar = [];
        $(".bulk-rec-chk:checked").each(function () {
          columnasA_Modificar.push($(this).data("dbcol"));
        });

        // 3. Construir la tabla completa
        let tableHtml = `
            <table class="table table-sm table-bordered table-hover mb-0 text-center align-middle" style="white-space: nowrap; width: max-content;">
                <thead class="table-dark sticky-top">
                    <tr>
                        <th width="50">#</th>
                        <th>Identificador</th>
                        ${columnasA_Modificar.map((c) => `<th>${PXM.escapeHtml(c)}</th>`).join("")}
                        <th width="120">Estado</th>
                    </tr>
                </thead>
                <tbody>
        `;

        // 4. LIMITAR LA RENDERIZACIÓN A 100 FILAS para no colapsar el navegador
        const MAX_VISUAL_ROWS = 100;
        const rowsToRender = allDetails.slice(0, MAX_VISUAL_ROWS);

        if (rowsToRender.length > 0) {
          rowsToRender.forEach((r, i) => {
            const badge =
              r.status === "changed"
                ? '<span class="badge bg-success">Se actualizará</span>'
                : r.status === "not_found"
                  ? '<span class="badge bg-danger">No encontrado</span>'
                  : '<span class="badge bg-secondary">Sin cambios</span>';

            tableHtml += `<tr>
                    <td>${i + 1}</td>
                    <td class="text-break small">${PXM.escapeHtml(r.identifier)}</td>`;

            columnasA_Modificar.forEach((col) => {
              let val =
                r.fields && r.fields[col] !== undefined ? r.fields[col] : "";
              const displayVal =
                val === ""
                  ? '<span class="text-muted fst-italic">(vacío)</span>'
                  : PXM.escapeHtml(val);
              tableHtml += `<td class="text-break small">${displayVal}</td>`;
            });

            tableHtml += `<td>${badge}</td></tr>`;
          });

          // Si hay más filas, mostrar aviso
          if (allDetails.length > MAX_VISUAL_ROWS) {
            tableHtml += `<tr>
                    <td colspan="${columnasA_Modificar.length + 3}" class="table-info text-center py-2 fw-bold">
                        <i class="fas fa-info-circle me-1"></i> Mostrando los primeros ${MAX_VISUAL_ROWS} registros de ${allDetails.length.toLocaleString()}. Al hacer clic en "Aplicar", se procesarán todos.
                    </td>
                </tr>`;
          }
        } else {
          tableHtml += `<tr><td colspan="${columnasA_Modificar.length + 3}" class="text-center py-4 text-muted">No hay registros para mostrar.</td></tr>`;
        }

        tableHtml += `</tbody></table>`;

        // 5. Inyectar la tabla en el contenedor
        $("#bulkRecPreviewTableContainer").html(tableHtml);

        $("#bulkRecApplyBtn").prop("disabled", totalChanged === 0);
        bulkRecShowStep(3);
      } catch (e) {
        PxmNotify.danger(e.message || "Error de conexión", 5000);
      } finally {
        $btn.prop("disabled", false).html(orig);
      }
    });

    // Aplicar
    $("#bulkRecApplyBtn").on("click", async function () {
      if (
        !confirm(
          "¿Aplicar los cambios a la base de datos? Esta acción no se puede deshacer.",
        )
      )
        return;

      const $btn = $(this);
      const orig = $btn.html();
      $btn
        .prop("disabled", true)
        .html(
          '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...',
        );

      // ═══ FIX: Extraer correctamente el mapeo (DbCol -> ExcelIdx) ═══
      let finalMap = {};
      $(".bulk-rec-chk:checked").each(function () {
        const dbCol = $(this).data("dbcol");
        const excelIdx = $(`.bulk-rec-select[data-dbcol="${dbCol}"]`).val();
        if (excelIdx !== "") {
          finalMap[dbCol] = parseInt(excelIdx, 10);
        }
      });

      if (Object.keys(finalMap).length === 0) {
        PxmNotify.warning(
          "Selecciona al menos una columna para actualizar.",
          5000,
        );
        $btn.prop("disabled", false).html(orig);
        return;
      }

      const idIndex = parseInt($("#bulkRecColIdentificador").val(), 10);
      const identifierField =
        bulkRecState.headers[idIndex].trim().toLowerCase() === "orden"
          ? "ORDEN"
          : "Contenedor_Enlace_Album_href";

      // ═══ FIX: Armar payload usando el finalMap correcto ═══
      const pairs = bulkRecState.dataRows
        .map((row) => {
          const obj = {
            identifier: String(row[idIndex] || "").trim(),
            fields: {},
          };

          Object.keys(finalMap).forEach((dbCol) => {
            const excelIdx = finalMap[dbCol];
            obj.fields[dbCol] = String(row[excelIdx] || "").trim();
          });

          return obj;
        })
        .filter((p) => p.identifier !== "");

      try {
        let totalChanged = 0;
        let totalNotFound = 0;
        const chunkSize = 3000;

        for (let i = 0; i < pairs.length; i += chunkSize) {
          const chunk = pairs.slice(i, i + chunkSize);
          $btn.html(
            `<span class="spinner-border spinner-border-sm me-1"></span> Guardando bloque ${Math.floor(i / chunkSize) + 1} de ${Math.ceil(pairs.length / chunkSize)}...`,
          );

          const res = await fetch(
            PXM.config.API_BASE + "bulk-update-records.php",
            {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              credentials: "same-origin",
              body: JSON.stringify({
                pairs: chunk,
                dry_run: false,
                identifier_field: identifierField,
              }),
            },
          );
          const data = await res.json();

          if (!data.success)
            throw new Error(data.message || "Error en servidor");

          totalChanged += data.changed || 0;
          totalNotFound += data.not_found || 0;

          await new Promise((r) => setTimeout(r, 50));
        }

        PxmNotify.success(
          `Se actualizaron ${totalChanged} registro(s) correctamente.`,
          5000,
        );
        if (totalNotFound > 0)
          PxmNotify.warning(
            `${totalNotFound} registro(s) no se encontraron.`,
            6000,
          );

        bootstrap.Modal.getOrCreateInstance(
          document.getElementById("bulkRecordsModal"),
        ).hide();
        setTimeout(() => PXM.refreshCurrentView(), 500);
      } catch (e) {
        PxmNotify.danger(e.message || "Error de conexión", 5000);
      } finally {
        $btn.prop("disabled", false).html(orig);
      }
    });
  }

  window.PXM = window.PXM || {};
  window.PXM.initBulkRecords = initBulkRecords;
})(jQuery);
