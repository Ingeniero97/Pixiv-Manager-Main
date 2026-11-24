/**
 * autores.js — Módulo de Autores (modal completo)
 * - Carga paginada por AJAX
 * - Búsqueda interna de autores
 * - Selección masiva (abrir / copiar)
 * - Edición de autor (nombre / enlace)
 * - Detalle simple y múltiple
 * - Tooltip flotante de múltiples nombres
 * - Remplazo masivo de autores desde CSV/XLSX (nuevo)
 *
 * Depende de:
 *   - window.APP_CONFIG.mode  ('index' | 'restauracion')
 *   - window.__pxmEscapeHtml / __pxmCopy / __pxmNotify  (main.js + notifications.js)
 *   - XLSX (SheetJS) para parseo de archivos en el cliente
 */
(function ($) {
  "use strict";

  const CONFIG = window.APP_CONFIG || {};
  const API_BASE = CONFIG.apiBase || "api/";
  const MODE = CONFIG.mode || "index";

  // Si main.js ya expuso helpers, usarlos; si no, se declaran aquí.
  const escapeHtml =
    window.__pxmEscapeHtml ||
    function (text) {
      if (text === null || text === undefined) return "";
      return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    };

  const copyToClipboard =
    window.__pxmCopy ||
    function (text) {
      if (!text) return;
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).catch(() => {});
      }
    };

  const showNotification =
    window.__pxmNotify ||
    function (message, type = "info", duration = 5000) {
      const $alert = $(`
            <div class="alert alert-${escapeHtml(type)} alert-dismissible fade show position-fixed"
                 role="alert"
                 style="top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 480px;">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
      $("body").append($alert);
      setTimeout(() => $alert.alert("close"), duration);
    };

  const numberFormat = (n) => Number(n || 0).toLocaleString("en-US");

  // ============================================================
  // ESTADO (autores)
  // ============================================================
  const st = {
    autoresData: [],
    selectedAutores: new Set(),
    paginaActual: 1,
    perPage: 50,
    totalPaginas: 1,
    totalRegistros: 0,
    totalEnlaces: 0,
    totalIlustraciones: 0,
    searchTimeout: null,
  };

  // ═════════════════════════════════════════════════════════════
  // ═══ NEW ═══ ESTADO (bulk autores: CSV/XLSX)                ═══
  // ═════════════════════════════════════════════════════════════
  const bulkState = {
    headers: [],
    dataRows: [],
    colEnlace: -1,
    colNombre: -1,
    preview: [],
    summary: null,
    parsing: false,
  };

  // ============================================================
  // CARGA DE AUTORES
  // ------------------------------------------------------------
  // Contrato del endpoint:
  //   GET api/get-autores.php
  //     ?page=<N>&per_page=<M>
  //     &main_search=<primaria>
  //     &main_ugoira=<csv>
  //     &secondary_search=<secundaria>
  //     &autor_search=<filtro interno>
  //     &estado=<0|1>
  //
  // Respuesta:
  //   { success:true, autores:[{nombre, nombres:[], enlace, total}],
  //     pagina_actual, per_page, total_paginas, total_autores,
  //     total_autores_enlaces, total_ilustraciones }
  // ============================================================
  async function loadPage(page) {
    $("#autoresTableBody").html(`
            <tr>
                <td colspan="7" class="text-center py-4">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2">Cargando autores...</p>
                </td>
            </tr>
        `);

    try {
      const params = new URLSearchParams(window.location.search);
      const main_search = params.get("search") || "";
      const main_ugoira = params.get("ugoira") || "";
      const secondary = $("#clientSearch").val() || "";
      const autorSearch = $("#autorSearch").val() || "";
      const main_col = params.get("col") || "";
      const secondary_col = $("#secondaryColSelect").val() || "";

      let url =
        `${API_BASE}get-autores.php?page=${page}` +
        `&per_page=${st.perPage}` +
        `&estado=${MODE === "restauracion" ? 0 : 1}` +
        `&_t=${Date.now()}`;

      if (main_search) url += `&main_search=${encodeURIComponent(main_search)}`;
      if (main_ugoira) url += `&main_ugoira=${encodeURIComponent(main_ugoira)}`;
      if (secondary)
        url += `&secondary_search=${encodeURIComponent(secondary)}`;
      if (autorSearch)
        url += `&autor_search=${encodeURIComponent(autorSearch)}`;
      if (main_col) url += `&main_col=${encodeURIComponent(main_col)}`;
      if (secondary_col)
        url += `&secondary_col=${encodeURIComponent(secondary_col)}`;

      const res = await fetch(url, { credentials: "same-origin" });
      const data = await res.json();

      if (!data.success)
        throw new Error(data.message || "Error al cargar autores");

      st.autoresData = data.autores || [];
      st.paginaActual = data.pagina_actual || page;
      st.perPage = data.per_page || st.perPage;
      st.totalPaginas = data.total_paginas || 1;
      st.totalRegistros = data.total_autores || 0;
      st.totalEnlaces = data.total_autores_enlaces || 0;
      st.totalIlustraciones = data.total_ilustraciones || 0;

      let titulo = "Autores encontrados";
      if (main_search || main_ugoira || secondary) titulo += " (Filtrados)";
      $("#autoresModalTitle").text(titulo);

      $("#autoresPerPageTop, #autoresPerPageBottom").val(st.perPage);

      renderTable();
      updateStats();
      renderPagination();
    } catch (error) {
      $("#autoresTableBody").html(`
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <i class="fas fa-exclamation-triangle fa-2x text-danger mb-3"></i>
                        <h6>Error al cargar autores</h6>
                        <p class="text-muted">${escapeHtml(error.message)}</p>
                    </td>
                </tr>
            `);
    }
  }

  // ============================================================
  // RENDER TABLA
  // ============================================================
  function renderTable() {
    if (!st.autoresData.length) {
      $("#autoresTableBody").html(`
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <i class="fas fa-users fa-2x text-muted mb-3"></i>
                        <h6>No se encontraron autores</h6>
                    </td>
                </tr>
            `);
      return;
    }

    const offset = (st.paginaActual - 1) * st.perPage;

    let html = "";
    st.autoresData.forEach((autor, index) => {
      const num = offset + index + 1;
      const nombre = autor.nombre || "(sin nombre)";
      const nombres = autor.nombres || [nombre];
      const totalNombres = nombres.length;
      const esMultiple = totalNombres > 1;
      const enlace = autor.enlace || "";
      const total = autor.total || 0;

      const nombreEsc = escapeHtml(nombre);
      const enlaceEsc = enlace ? escapeHtml(enlace) : "";
      const nombresEsc = escapeHtml(JSON.stringify(nombres));
      const uniqueId = enlace + "|||" + nombre;
      const esSeleccionado = st.selectedAutores.has(uniqueId);

      html += `
            <tr class="${esSeleccionado ? "selected-row" : ""}">
                <td class="text-center">
                    <input type="checkbox" class="form-check-input autor-checkbox"
                            data-enlace="${enlaceEsc}"
                            data-nombre="${nombreEsc}"
                            ${esSeleccionado ? "checked" : ""}
                            ${!enlace ? 'disabled title="Sin enlace"' : ""}>
                </td>
                <td class="text-center">
                    ${
                      esMultiple
                        ? `<button class="btn btn-sm btn-outline-info btn-detalle-multiple"
                                   data-enlace="${enlaceEsc}"
                                   data-nombres='${nombresEsc}'
                                   title="Ver los ${totalNombres} nombres con este enlace">
                              <i class="fas fa-layer-group"></i>
                           </button>`
                        : '<span class="text-muted">—</span>'
                    }
                </td>
                <td class="text-center">${num}</td>
                <td>
                    <strong>${nombreEsc}</strong>
                    ${
                      esMultiple
                        ? `<span class="badge bg-info ms-1 badge-multiple-autor"
                                 data-nombres='${nombresEsc}' style="cursor: help;">
                              [+${totalNombres - 1} ${totalNombres - 1 === 1 ? "autor" : "autores"}]
                           </span>`
                        : ""
                    }
                    <!-- ═══ Botón Copiar en la tabla (CON TEXTO) ═══ -->
                    <button class="btn btn-sm btn-outline-success ms-1 btn-copy-author" 
                            data-nombre="${nombreEsc}" title="Copiar autor">
                        <i class="fas fa-copy me-1"></i> Copiar autor
                    </button>

                    ${!enlace ? ' <span class="badge bg-warning text-dark">Sin enlace</span>' : ""}
                </td>
                <td class="text-center">
                    <button class="btn btn-warning btn-sm editar-autor"
                            data-enlace="${enlaceEsc}"
                            data-nombre="${nombreEsc}"
                            data-nombres='${nombresEsc}'
                            title="Editar autor">
                        <i class="fas fa-edit"></i>
                    </button>
                </td>
                <td class="text-center">
                    <span class="badge bg-info" style="font-size: 0.9rem;">${escapeHtml(total)}</span>
                </td>
                <td class="text-center">
                    <div class="btn-group btn-group-sm">
                        ${
                          enlace
                            ? `<button class="btn btn-success btn-sm abrir-autor" data-enlace="${enlaceEsc}" title="Abrir enlace">
                                  <i class="fas fa-external-link-alt"></i>
                               </button>`
                            : `<button class="btn btn-secondary btn-sm" disabled title="Sin enlace">
                                  <i class="fas fa-external-link-alt"></i>
                               </button>`
                        }
                        <button class="btn btn-info btn-sm ver-detalle-autor"
                                data-enlace="${enlaceEsc}"
                                data-nombre="${nombreEsc}"
                                data-nombres='${nombresEsc}'
                                data-total="${escapeHtml(total)}"
                                title="Ver detalle">
                            <i class="fas fa-info-circle"></i>
                        </button>
                        ${
                          enlace
                            ? `<button class="btn btn-primary btn-sm copiar-enlace-autor" data-enlace="${enlaceEsc}" title="Copiar enlace">
                                  <i class="fas fa-copy"></i>
                               </button>`
                            : `<button class="btn btn-secondary btn-sm" disabled title="Sin enlace">
                                  <i class="fas fa-copy"></i>
                               </button>`
                        }
                        <button class="btn btn-warning btn-sm filtrar-autor" data-nombre="${nombreEsc}" title="Filtrar por este autor">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </td>
            </tr>`;
    });

    $("#autoresTableBody").html(html);
  }

  // ============================================================
  // PAGINACIÓN
  // ============================================================
  function renderPagination() {
    const $top = $("#autoresPaginacionTop");
    const $bottom = $("#autoresPaginacionBottom");

    const makeLi = (
      page,
      label,
      { disabled = false, dots = false, active = false } = {},
    ) => {
      if (dots)
        return '<li class="page-item disabled"><span class="page-link">…</span></li>';
      if (disabled)
        return `<li class="page-item disabled"><span class="page-link">${label}</span></li>`;
      return `<li class="page-item${active ? " active" : ""}">
                        <a class="page-link" href="#" data-page="${page}">${label}</a>
                    </li>`;
    };

    if (st.totalPaginas <= 1) {
      const single =
        makeLi(1, '<i class="fas fa-angle-double-left"></i>', {
          disabled: true,
        }) +
        makeLi(1, '<i class="fas fa-angle-left"></i>', { disabled: true }) +
        makeLi(1, "1", { active: true }) +
        makeLi(1, '<i class="fas fa-angle-right"></i>', { disabled: true }) +
        makeLi(1, '<i class="fas fa-angle-double-right"></i>', {
          disabled: true,
        });
      $top.html(single);
      $bottom.html(single);
      $("#autoresPaginacionInfoBottom").text(
        `Página 1 de 1 • ${numberFormat(st.totalRegistros)} nombres`,
      );
      return;
    }

    const delta = 2;
    const set = new Set([1, st.totalPaginas, st.paginaActual]);
    for (let i = st.paginaActual - delta; i <= st.paginaActual + delta; i++) {
      if (i >= 1 && i <= st.totalPaginas) set.add(i);
    }
    const sorted = Array.from(set).sort((a, b) => a - b);
    const range = [];
    let prev = 0;
    sorted.forEach((p) => {
      if (prev && p - prev > 1) range.push({ dots: true });
      range.push({ page: p });
      prev = p;
    });

    let html = "";
    html +=
      st.paginaActual > 1
        ? makeLi(1, '<i class="fas fa-angle-double-left"></i>')
        : makeLi(1, '<i class="fas fa-angle-double-left"></i>', {
            disabled: true,
          });
    html +=
      st.paginaActual > 1
        ? makeLi(st.paginaActual - 1, '<i class="fas fa-angle-left"></i>')
        : makeLi(1, '<i class="fas fa-angle-left"></i>', { disabled: true });
    range.forEach((item) => {
      if (item.dots) html += makeLi(0, "…", { dots: true });
      else
        html += makeLi(item.page, String(item.page), {
          active: item.page === st.paginaActual,
        });
    });
    html +=
      st.paginaActual < st.totalPaginas
        ? makeLi(st.paginaActual + 1, '<i class="fas fa-angle-right"></i>')
        : makeLi(st.totalPaginas, '<i class="fas fa-angle-right"></i>', {
            disabled: true,
          });
    html +=
      st.paginaActual < st.totalPaginas
        ? makeLi(st.totalPaginas, '<i class="fas fa-angle-double-right"></i>')
        : makeLi(st.totalPaginas, '<i class="fas fa-angle-double-right"></i>', {
            disabled: true,
          });

    $top.html(html);
    $bottom.html(html);
    $("#autoresPaginacionInfoBottom").text(
      `Página ${st.paginaActual} de ${st.totalPaginas} • ${numberFormat(st.totalRegistros)} nombres`,
    );
  }

  // ============================================================
  // ESTADÍSTICAS / SELECCIÓN UI
  // ============================================================
  function updateStats() {
    $("#totalAutoresCount").text(numberFormat(st.totalRegistros));
    $("#totalIlustracionesAutores").text(numberFormat(st.totalIlustraciones));
    $("#totalConEnlace").text(numberFormat(st.totalEnlaces));

    let stats = `Mostrando ${st.autoresData.length} nombres en esta página`;
    if (st.totalRegistros > 0)
      stats += ` • Total: ${numberFormat(st.totalRegistros)} nombres`;
    if ($("#autorSearch").val()) stats += " (Filtrados)";
    $("#autorStats").text(stats);

    updateSelectionUI();
  }

  function updateSelectionUI() {
    const count = st.selectedAutores.size;
    $("#selectedAutoresCount").text(count);

    const $badge = $("#autoresBadgeSeleccionados");
    if (count > 0) $badge.text(count).css("display", "inline-flex");
    else $badge.css("display", "none");

    $("#openSelectedAutores, #copyAutorLinks").prop("disabled", count === 0);

    const $all = $("#autoresTableBody .autor-checkbox:not(:disabled)");
    const $chkd = $all.filter(":checked");
    $("#checkAllAutoresHeader").prop(
      "checked",
      $all.length > 0 && $chkd.length === $all.length,
    );
  }

  function selectAll() {
    st.autoresData.forEach((a) => {
      if (a && a.enlace) {
        // ═══ FIX: Guardar Enlace + Nombre para que renderTable() lo reconozca ═══
        st.selectedAutores.add(a.enlace + "|||" + a.nombre);
      }
    });
    renderTable();
    updateSelectionUI();
  }

  function deselectAll() {
    st.selectedAutores.clear();
    renderTable();
    updateSelectionUI();
  }

  // ============================================================
  // EDITAR AUTOR
  // ============================================================
  function openEditModal(enlace, nombre, nombresJson) {
    let nombres = [];
    try {
      nombres =
        typeof nombresJson === "string"
          ? JSON.parse(nombresJson)
          : nombresJson || [];
    } catch (_) {
      nombres = nombre ? [nombre] : [];
    }

    $("#editAutorEnlaceOriginal")
      .data("enlace", enlace || "")
      .val(enlace || "");

    const $lista = $("#editAutorListaNombres");
    if (!nombres.length) {
      $lista.html('<div class="text-muted">No hay nombres registrados.</div>');
    } else {
      $lista.html(
        nombres
          .map(
            (n, i) =>
              `<div class="autor-nombre-item">
                    <span class="numero">#${i + 1}</span>
                    <strong>${escapeHtml(n)}</strong>
                 </div>`,
          )
          .join(""),
      );
    }

    $("#editAutorNombreNuevo").val("");
    $("#editAutorEnlaceNuevo").val("");
    $("#bloqueEditarEnlace").addClass("d-none");

    if (enlace) {
      $("#editAutorEnlaceDisplay")
        .removeClass("text-muted")
        .html(
          `<a href="${escapeHtml(enlace)}" target="_blank" rel="noopener noreferrer">${escapeHtml(enlace)}</a>`,
        );
      $("#btnEditarEnlaceAutor")
        .text("Editar enlace")
        .removeClass("btn-outline-primary")
        .addClass("btn-outline-warning");
    } else {
      $("#editAutorEnlaceDisplay").addClass("text-muted").text("(sin enlace)");
      $("#btnEditarEnlaceAutor")
        .text("Agregar enlace")
        .removeClass("btn-outline-warning")
        .addClass("btn-outline-primary");
    }

    bootstrap.Modal.getOrCreateInstance(
      document.getElementById("editarAutorModal"),
    ).show();
  }

  async function saveEditAutor() {
    const enlaceOriginal = $("#editAutorEnlaceOriginal").data("enlace") || "";
    const nombreNuevo = $("#editAutorNombreNuevo").val().trim();
    const enlaceNuevo = $("#editAutorEnlaceNuevo").val().trim();

    if (!enlaceOriginal) {
      showNotification("Se requiere un enlace de autor para editar", "warning");
      return;
    }
    if (!nombreNuevo && !enlaceNuevo) {
      showNotification(
        "Debes cambiar el nombre o el enlace del autor",
        "warning",
      );
      return;
    }
    if (enlaceNuevo && enlaceNuevo === enlaceOriginal) {
      showNotification("El nuevo enlace es igual al actual", "info");
      return;
    }
    if (!confirm("¿Confirmas actualizar los registros del autor?")) return;

    const $btn = $("#guardarEditarAutor");
    const originalHtml = $btn.html();
    $btn
      .html(
        '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...',
      )
      .prop("disabled", true);

    try {
      const res = await fetch(API_BASE + "update-autor.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({
          enlace_original: enlaceOriginal,
          nombre_nuevo: nombreNuevo,
          enlace_nuevo: enlaceNuevo,
        }),
      });
      const data = await res.json();

      if (!data.success) {
        showNotification(
          "Error: " + (data.message || "Error desconocido"),
          "danger",
        );
        return;
      }

      const b = (data.detalles && data.detalles.buscador) || {};
      const r = (data.detalles && data.detalles.restauracion) || {};

      const bAutor = b.autor_actualizado || 0;
      const bEnl = b.enlace_actualizado || 0;
      const bMA = b.autor_mismo || 0;
      const bME = b.enlace_mismo || 0;
      const rAutor = r.autor_actualizado || 0;
      const rEnl = r.enlace_actualizado || 0;
      const rMA = r.autor_mismo || 0;
      const rME = r.enlace_mismo || 0;

      const htmlMsg = `
                <div style="text-align:left;">
                    <div style="font-weight:600; margin-bottom:6px;">
                        <i class="fas fa-user-edit me-1"></i> Autor actualizado
                    </div>
                    <div style="margin-bottom:6px;">
                        <div style="font-weight:600;"><i class="fas fa-search me-1"></i> Buscador</div>
                        <div style="padding-left:18px;">
                            <div>${bAutor} Se actualizó autor.</div>
                            <div>${bMA} Mismo autor.</div>
                            <div>${bEnl} Se actualizó enlace.</div>
                            <div>${bME} Mismo enlace.</div>
                        </div>
                    </div>
                    <div>
                        <div style="font-weight:600;"><i class="fas fa-trash-restore me-1"></i> Restauración</div>
                        <div style="padding-left:18px;">
                            <div>${rAutor} Se actualizó autor.</div>
                            <div>${rMA} Mismo autor.</div>
                            <div>${rEnl} Se actualizó enlace.</div>
                            <div>${rME} Mismo enlace.</div>
                        </div>
                    </div>
                </div>`;

      showNotification(htmlMsg, "success", 8000);
      bootstrap.Modal.getOrCreateInstance(
        document.getElementById("editarAutorModal"),
      ).hide();

      // Refrescar tabla de autores
      loadPage(st.paginaActual);
    } catch (e) {
      showNotification("Error de conexión: " + e.message, "danger");
    } finally {
      $btn.html(originalHtml).prop("disabled", false);
    }
  }

  // ============================================================
  // DETALLE SIMPLE / MÚLTIPLE
  // ============================================================
  function openDetalleSimple(enlace, nombre, total) {
    const html = `
            <div class="text-center mb-4">
                <i class="fas fa-user-circle fa-4x text-info"></i>
            </div>
            <table class="table table-bordered">
                <tr>
                    <th width="140">Enlace:</th>
                    <td>${
                      enlace
                        ? `<a href="${escapeHtml(enlace)}" target="_blank" rel="noopener noreferrer">${escapeHtml(enlace)}</a>`
                        : '<span class="text-muted">No disponible</span>'
                    }</td>
                </tr>
                <tr>
                    <th>Nombre:</th>
                    <td>
                        <strong>${escapeHtml(nombre)}</strong>
                        <!-- ═══ Botón Copiar en Detalle Simple (Verde) ═══ -->
                        <button class="btn btn-sm btn-success ms-2 btn-copy-author" 
                                data-nombre="${escapeHtml(nombre)}" title="Copiar autor">
                            <i class="fas fa-copy me-1"></i> Copiar autor
                        </button>
                    </td>
                </tr>
                <tr>
                    <th>Total ilustraciones:</th>
                    <td><span class="badge bg-info fs-6">${escapeHtml(total)}</span></td>
                </tr>
            </table>`;
    $("#detalleAutorBody").html(html);
    $("#abrirEnlaceDetalle").data("enlace", enlace);
    $("#detalleAutorModal").data("enlace", enlace);
    bootstrap.Modal.getOrCreateInstance(
      document.getElementById("detalleAutorModal"),
    ).show();
  }

  function openDetalleMultiple(enlace, nombresJson) {
    let nombres = [];
    try {
      nombres =
        typeof nombresJson === "string"
          ? JSON.parse(nombresJson)
          : nombresJson || [];
    } catch (_) {
      nombres = [];
    }

    $("#detalleMultipleEnlace").html(
      enlace
        ? `<a href="${escapeHtml(enlace)}" target="_blank" rel="noopener noreferrer">${escapeHtml(enlace)}</a>`
        : '<span class="text-muted">No disponible</span>',
    );

    let html = nombres.length
      ? nombres
          .map(
            (n, i) =>
              `<div class="autor-nombre-item d-flex justify-content-between align-items-center">
                    <div>
                        <span class="numero">#${i + 1}</span>
                        <strong>${escapeHtml(n)}</strong>
                    </div>
                    <!-- ═══ Botón Copiar en Detalle Múltiple (Verde) ═══ -->
                    <button class="btn btn-sm btn-success btn-copy-author" 
                            data-nombre="${escapeHtml(n)}" title="Copiar autor">
                        <i class="fas fa-copy me-1"></i> Copiar autor
                    </button>
                 </div>`,
          )
          .join("")
      : '<div class="text-muted">Sin nombres registrados.</div>';

    $("#detalleMultipleLista").html(html);
    $("#detalleMultipleAutorModal").data("enlace", enlace);
    bootstrap.Modal.getOrCreateInstance(
      document.getElementById("detalleMultipleAutorModal"),
    ).show();
  }

  // ============================================================
  // ABRIR / COPIAR ENLACES DE AUTORES SELECCIONADOS
  // ============================================================
  function openSelectedLinks() {
    // En openSelectedLinks() y copySelectedLinks()
    const enlaces = Array.from(
      new Set(
        st.autoresData
          .filter(
            (a) =>
              st.selectedAutores.has(a.enlace + "|||" + a.nombre) && a.enlace,
          )
          .map((a) => a.enlace),
      ),
    );

    if (!enlaces.length) {
      showNotification("No hay enlaces para abrir", "warning");
      return;
    }

    if (enlaces.length === 1) {
      window.open(enlaces[0], "_blank", "noopener,noreferrer");
      return;
    }

    const mainWindow = window.open("", "_blank");
    if (!mainWindow) {
      showNotification(
        "Permite ventanas emergentes para abrir múltiples enlaces",
        "warning",
      );
      return;
    }

    const html = `<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Abriendo ${enlaces.length} enlaces</title>
<style>
body{font-family:system-ui;padding:20px;background:#f5f5f5}
.container{max-width:800px;margin:0 auto;background:#fff;padding:20px;border-radius:8px}
.header{background:#28a745;color:#fff;padding:10px;border-radius:5px;margin-bottom:20px}
.enlace{padding:10px;margin:5px 0;background:#f8f9fa;border-left:3px solid #28a745;word-break:break-all}
.actual{background:#fff3cd;border-left-color:#ffc107}
.contador{font-size:20px;color:#28a745;margin:20px 0;text-align:center}
</style></head><body><div class="container">
<div class="header"><h2>Abriendo enlaces de autores</h2></div>
<div class="contador" id="contador">Preparando...</div>
<div id="lista"></div></div>
<script>
const enlaces=${JSON.stringify(enlaces).replace(/</g, "\\u003c")};
let i=0;
function refresh(){
  document.getElementById('lista').innerHTML=enlaces.map((e,idx)=>{
    const cls=idx===i?'enlace actual':'enlace';
    const est=idx<i?'✓':(idx===i?'→':'');
    return '<div class="'+cls+'">'+est+' '+(idx+1)+'. <a href="'+e+'" target="_blank" rel="noopener noreferrer">'+e+'</a></div>';
  }).join('');
  document.getElementById('contador').textContent='Procesando '+(i+1)+' de '+enlaces.length;
}
function next(){
  if(i<enlaces.length){window.location.href=enlaces[i];refresh();i++;setTimeout(next,1500);}
  else{document.getElementById('contador').textContent='¡Completado!';}
}
refresh();setTimeout(next,800);
<\/script></body></html>`;

    mainWindow.document.write(html);
    mainWindow.document.close();
    showNotification(`Abriendo ${enlaces.length} enlaces`, "success");
  }

  function copySelectedLinks() {
    const enlaces = Array.from(
      new Set(
        st.autoresData
          // ═══ FIX: Verificar el uniqueId (Enlace + Nombre) ═══
          .filter(
            (a) =>
              st.selectedAutores.has(a.enlace + "|||" + a.nombre) && a.enlace,
          )
          .map((a) => a.enlace),
      ),
    );

    if (!enlaces.length) {
      showNotification("No hay enlaces para copiar", "warning");
      return;
    }

    copyToClipboard(enlaces.join("\n"));

    const preview =
      enlaces.length > 3
        ? enlaces.slice(0, 3).join("\n") + `\n... y ${enlaces.length - 3} más`
        : enlaces.join("\n");

    showNotification(
      `<strong>${enlaces.length} enlace(s) copiado(s)</strong><br>
             <small class="text-muted">${escapeHtml(preview).replace(/\n/g, "<br>")}</small>`,
      "success",
      5000,
    );
  }

  // ============================================================
  // TOOLTIP FLOTANTE (múltiples nombres)
  // ============================================================
  function showTooltip($badge) {
    let nombres = [];
    try {
      const raw = $badge.attr("data-nombres");
      nombres = typeof raw === "string" ? JSON.parse(raw) : raw || [];
    } catch (_) {
      nombres = [];
    }

    const html = nombres.length
      ? nombres
          .map(
            (n, i) =>
              `<div><span class="numero">#${i + 1}</span><strong>${escapeHtml(n)}</strong></div>`,
          )
          .join("")
      : '<div class="text-muted">Sin nombres registrados.</div>';
    $("#tooltipMultipleAutorLista").html(html);

    const $tt = $("#tooltipMultipleAutor");
    const offset = $badge.offset();
    const badgeH = $badge.outerHeight();
    const ttW = 320;
    const ttH = $tt.outerHeight() || 200;

    let top = offset.top + badgeH + 8;
    let left = offset.left;

    if (left + ttW > $(window).width() - 20)
      left = $(window).width() - ttW - 20;
    if (top + ttH > $(window).height() - 20) top = offset.top - ttH - 8;

    $tt.css({ top: top + "px", left: left + "px", display: "block" });
  }

  function hideTooltip() {
    $("#tooltipMultipleAutor").hide();
  }

  // ═════════════════════════════════════════════════════════════
  // ═══ REMPLAZAR AUTORES EN LOTE (CSV / XLSX) — 3 ventanas    ═══
  // ═════════════════════════════════════════════════════════════

  function bulkReset() {
    bulkState.headers = [];
    bulkState.dataRows = [];
    bulkState.colEnlace = -1;
    bulkState.colNombre = -1;
    bulkState.preview = [];
    bulkState.summary = null;

    // Ventana 1
    $("#bulkFileInput").val("");
    $("#bulkDropZone").removeClass("d-none");
    $("#bulkFileInfo").addClass("d-none");
    $("#bulkFileInfoText").text("");
    $("#bulkNextFromFile").prop("disabled", true);

    // Ventana 2
    $("#bulkColEnlace, #bulkColNombre").empty();
    $("#bulkDetectedInfo").empty();

    // Ventana 3
    $("#bulkPreviewBody").empty();
    $("#bulkApplyBtn").prop("disabled", false);

    bulkShowStep(1);
  }

  function bulkShowStep(step) {
    // 1. Alternar visibilidad de ventanas
    $("#bulkStep1, #bulkStep2, #bulkStep3").addClass("d-none");
    $("#bulkStep" + step).removeClass("d-none");

    // 2. Alternar visibilidad de footers
    $("#bulkFooterStep1, #bulkFooterStep2, #bulkFooterStep3").addClass(
      "d-none",
    );
    $("#bulkFooterStep" + step).removeClass("d-none");

    // 3. Actualizar Stepper (Píldoras)
    $("#bulkSteps .nav-link").each(function () {
      const s = parseInt($(this).data("step"), 10);
      $(this).removeClass("active disabled");
      if (s === step) $(this).addClass("active");
      else if (s > step) $(this).addClass("disabled");
    });

    // 4. Redimensionar el modal dinámicamente según la ventana
    const $dialog = $("#bulkModalDialog");
    $dialog.removeClass("modal-sm modal-md modal-lg modal-xl");

    if (step === 1) {
      $dialog.addClass("modal-md"); // Ventana 1: Pequeño (Archivo)
    } else if (step === 2) {
      $dialog.addClass("modal-lg"); // Ventana 2: Mediano (Columnas)
    } else {
      $dialog.addClass("modal-xl"); // Ventana 3: Ancho (Vista previa)
    }
  }

  function bulkParseFile(file) {
    return new Promise((resolve, reject) => {
      if (typeof XLSX === "undefined") {
        return reject(
          new Error(
            "SheetJS no está cargado. Revisa el <script> en footer.php.",
          ),
        );
      }
      if (file.size > 10 * 1024 * 1024) {
        return reject(new Error("El archivo supera 10 MB."));
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

  function bulkDetectColumns(headers, dataRows) {
    let bestEnlace = -1;
    let bestNombre = -1;
    const colCount = headers.length;

    for (let c = 0; c < colCount; c++) {
      const sample = dataRows
        .slice(0, 20)
        .map((r) => String(r[c] || "").trim());
      const urls = sample.filter((v) => /^https?:\/\//i.test(v)).length;
      if (urls >= sample.length * 0.5 && bestEnlace === -1) {
        bestEnlace = c;
      }
    }
    for (let c = 0; c < colCount; c++) {
      if (c === bestEnlace) continue;
      const sample = dataRows
        .slice(0, 20)
        .map((r) => String(r[c] || "").trim());
      const texts = sample.filter(
        (v) => v !== "" && !/^https?:\/\//i.test(v),
      ).length;
      if (texts >= sample.length * 0.5) {
        bestNombre = c;
        break;
      }
    }
    return { colEnlace: bestEnlace, colNombre: bestNombre };
  }

  function bulkPopulateColumnSelectors() {
    const $enlace = $("#bulkColEnlace").empty();
    const $nombre = $("#bulkColNombre").empty();

    bulkState.headers.forEach((h, i) => {
      const label =
        h && String(h).trim() !== "" ? String(h).trim() : `(Columna ${i + 1})`;
      $enlace.append($("<option>").val(i).text(label));
      $nombre.append($("<option>").val(i).text(label));
    });

    if (bulkState.colEnlace >= 0) $enlace.val(bulkState.colEnlace);
    if (bulkState.colNombre >= 0) $nombre.val(bulkState.colNombre);

    $("#bulkDetectedInfo").html(
      `Se detectaron <strong>${bulkState.headers.length}</strong> columnas y ` +
        `<strong>${bulkState.dataRows.length}</strong> filas de datos. ` +
        (bulkState.colEnlace >= 0
          ? `Enlace detectado en columna <strong>#${bulkState.colEnlace + 1}</strong>. `
          : "") +
        (bulkState.colNombre >= 0
          ? `Nombre detectado en columna <strong>#${bulkState.colNombre + 1}</strong>.`
          : "Selecciona manualmente las columnas."),
    );
  }

  function bulkBuildPairs() {
    const ce = parseInt($("#bulkColEnlace").val(), 10);
    const cn = parseInt($("#bulkColNombre").val(), 10);
    if (isNaN(ce) || isNaN(cn)) return [];
    if (ce === cn) {
      showNotification(
        "Las columnas de enlace y nombre no pueden ser la misma.",
        "warning",
        8000,
      );
      return [];
    }
    return bulkState.dataRows
      .map((r) => ({
        enlace: String(r[ce] || "").trim(),
        nombre: String(r[cn] || "").trim(),
      }))
      .filter((p) => p.enlace !== "" || p.nombre !== "");
  }

  function bulkRenderPreview(rows) {
    const $body = $("#bulkPreviewBody").empty();
    if (!rows.length) {
      $body.html(
        '<tr><td colspan="5" class="text-center text-muted py-3">Sin filas.</td></tr>',
      );
      return;
    }

    let html = "";
    rows.forEach((r, i) => {
      const nombresActuales = (r.nombres_actuales || []).join(" / ");
      const nombresActualesTrunc =
        nombresActuales.length > 60
          ? nombresActuales.substring(0, 60) + "..."
          : nombresActuales;

      let badge;
      if (r.status === "cambiado")
        badge = '<span class="badge bg-success">Se actualizará</span>';
      else if (r.status === "sin_cambios")
        badge = '<span class="badge bg-info text-dark">Sin cambios</span>';
      else if (r.status === "no_encontrado")
        badge = '<span class="badge bg-danger">No encontrado</span>';
      else badge = '<span class="badge bg-warning text-dark">Inválido</span>';

      html += `
            <tr class="${r.status === "no_encontrado" ? "table-danger" : ""}">
                <td class="text-center small">${i + 1}</td>
                <td class="small text-break" title="${escapeHtml(r.enlace)}">
                    ${escapeHtml(
                      r.enlace.length > 50
                        ? r.enlace.substring(0, 50) + "..."
                        : r.enlace,
                    )}
                </td>
                <td class="small">${escapeHtml(r.nombre_nuevo)}</td>
                <td class="small text-muted" title="${escapeHtml(nombresActuales)}">
                    ${escapeHtml(nombresActualesTrunc || "—")}
                </td>
                <td>${badge}</td>
            </tr>`;
    });
    $body.html(html);
  }

  async function bulkRequestPreview() {
    const pairs = bulkBuildPairs();
    if (!pairs.length) {
      showNotification("No hay filas válidas para procesar.", "warning", 8000);
      return;
    }
    if (pairs.length > 10000) {
      showNotification(
        "El archivo tiene más de 10,000 filas.",
        "warning",
        8000,
      );
      return;
    }

    const $btn = $("#bulkPreviewBtn");
    const orig = $btn.html();
    $btn
      .prop("disabled", true)
      .html(
        '<span class="spinner-border spinner-border-sm me-1"></span> Analizando...',
      );

    try {
      const res = await fetch(API_BASE + "bulk-update-autores.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ pairs, dry_run: true }),
      });
      const data = await res.json();

      if (!data.success) {
        showNotification(
          data.message || "Error en la vista previa.",
          "danger",
          8000,
        );
        return;
      }

      bulkState.preview = (data.detalles && data.detalles.rows) || [];
      bulkState.summary = data;

      $("#bulkCountTotal").text(data.total || 0);
      $("#bulkCountChanged").text(data.cambiados || 0);
      $("#bulkCountSame").text(data.sin_cambios || 0);
      $("#bulkCountNotFound").text(data.no_encontrados || 0);

      bulkRenderPreview(bulkState.preview);
      bulkShowStep(3);

      $("#bulkApplyBtn").prop("disabled", (data.cambiados || 0) === 0);

      if ((data.invalidos || 0) > 0) {
        showNotification(
          `${data.invalidos} fila(s) inválida(s) fueron ignoradas. Revisa la vista previa.`,
          "warning",
          8000,
        );
      }
    } catch (e) {
      showNotification(
        "Error de conexión al analizar el archivo.",
        "danger",
        8000,
      );
    } finally {
      $btn.prop("disabled", false).html(orig);
    }
  }

  async function bulkApply() {
    if (
      !confirm(
        "¿Aplicar los cambios a la base de datos? Esta acción no se puede deshacer.",
      )
    )
      return;

    const pairs = bulkBuildPairs();
    if (!pairs.length) return;

    const $btn = $("#bulkApplyBtn");
    const orig = $btn.html();
    $btn
      .prop("disabled", true)
      .html(
        '<span class="spinner-border spinner-border-sm me-1"></span> Aplicando...',
      );

    try {
      const res = await fetch(API_BASE + "bulk-update-autores.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ pairs, dry_run: false }),
      });
      const data = await res.json();

      if (!data.success) {
        showNotification(
          data.message || "Error al aplicar los cambios.",
          "danger",
          8000,
        );
        return;
      }

      const changed = parseInt(data.cambiados, 10) || 0;
      const same = parseInt(data.sin_cambios, 10) || 0;
      const notFound = parseInt(data.no_encontrados, 10) || 0;
      const invalid = parseInt(data.invalidos, 10) || 0;

      if (changed > 0)
        showNotification(
          `Se actualizaron ${changed} autor(es) correctamente.`,
          "success",
          8000,
        );
      if (same > 0)
        showNotification(
          `${same} autor(es) no necesitaban cambios.`,
          "info",
          8000,
        );
      if (notFound > 0)
        showNotification(
          `${notFound} enlace(s) no se encontraron en la base de datos.`,
          "danger",
          8000,
        );
      if (invalid > 0)
        showNotification(
          `${invalid} fila(s) inválida(s) fueron ignoradas.`,
          "warning",
          8000,
        );

      bootstrap.Modal.getOrCreateInstance(
        document.getElementById("bulkAutoresModal"),
      ).hide();

      loadPage(st.paginaActual);
    } catch (e) {
      showNotification(
        "Error de conexión al aplicar los cambios.",
        "danger",
        8000,
      );
    } finally {
      $btn.prop("disabled", false).html(orig);
    }
  }

  function initBulkAutores() {
    // Abrir modal
    $(document).on("click", "#bulkAutoresBtn", function () {
      bulkReset();
      bootstrap.Modal.getOrCreateInstance(
        document.getElementById("bulkAutoresModal"),
      ).show();
    });

    // ── Ventana 1: selección de archivo ──────────────────────
    $("#bulkFileInput").on("change", async function () {
      const file = this.files && this.files[0];
      if (!file) return;
      if (bulkState.parsing) return;
      bulkState.parsing = true;

      try {
        const matrix = await bulkParseFile(file);
        if (matrix.length < 2) {
          showNotification(
            "El archivo debe tener al menos una fila de encabezados y una de datos.",
            "warning",
            8000,
          );
          return;
        }

        bulkState.headers = matrix[0].map((h) => String(h || ""));
        bulkState.dataRows = matrix.slice(1);

        if (bulkState.dataRows.length > 10000) {
          bulkState.dataRows = bulkState.dataRows.slice(0, 10000);
          showNotification(
            "El archivo tiene más de 10,000 filas. Se procesarán solo las primeras 10,000.",
            "warning",
            8000,
          );
        }

        const detected = bulkDetectColumns(
          bulkState.headers,
          bulkState.dataRows,
        );
        bulkState.colEnlace = detected.colEnlace;
        bulkState.colNombre = detected.colNombre;

        // Mostrar info del archivo y habilitar "Siguiente"
        $("#bulkFileInfoText").text(
          `${bulkState.dataRows.length.toLocaleString()} filas · ${bulkState.headers.length} columnas · "${file.name}"`,
        );

        // AQUI CAMBIAMOS LA VISIBILIDAD
        $("#bulkFileInfo").removeClass("d-none");
        $("#bulkDropZone").addClass("d-none");
        $("#bulkNextFromFile").prop("disabled", false);

        bulkPopulateColumnSelectors();
      } catch (err) {
        showNotification(
          err.message || "Error al procesar el archivo.",
          "danger",
          8000,
        );
      } finally {
        bulkState.parsing = false;
      }
    });

    // Quitar archivo cargado
    $(document).on("click", "#bulkClearFileBtn", function () {
      $("#bulkFileInput").val("");

      // AQUI CAMBIAMOS LA VISIBILIDAD
      $("#bulkDropZone").removeClass("d-none");
      $("#bulkFileInfo").addClass("d-none");

      $("#bulkFileInfoText").text("");
      $("#bulkNextFromFile").prop("disabled", true);
      bulkState.headers = [];
      bulkState.dataRows = [];
      bulkState.colEnlace = -1;
      bulkState.colNombre = -1;
    });

    // Drag & Drop (opcional — mejora UX)
    const $drop = $("#bulkDropZone");
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
      const file = dt.files[0];
      // Inyectar en el input para reutilizar el handler
      const dataTransfer = new DataTransfer();
      dataTransfer.items.add(file);
      $("#bulkFileInput")[0].files = dataTransfer.files;
      $("#bulkFileInput").trigger("change");
    });

    // ═══ NEW ═══ Clic en el cuadro para abrir explorador
    $(document).on("click", "#bulkDropZone", function (e) {
      // Evitar doble clic si presiona el botón interno
      if ($(e.target).is("label") || $(e.target).closest("label").length > 0) {
        return;
      }
      $("#bulkFileInput")[0].click();
    });

    // ── Navegación entre ventanas ─────────────────────────────
    $("#bulkNextFromFile").on("click", function () {
      if (bulkState.dataRows.length === 0) {
        showNotification("Selecciona un archivo primero.", "warning", 8000);
        return;
      }
      bulkShowStep(2);
    });

    $("#bulkBackToFile").on("click", function () {
      bulkShowStep(1);
    });

    $("#bulkBackToColumns").on("click", function () {
      bulkShowStep(2);
    });

    // ── Ventana 2 → Preview / Ventana 3 → Apply ──────────────
    $("#bulkPreviewBtn").on("click", bulkRequestPreview);
    $("#bulkRefreshPreviewBtn").on("click", bulkRequestPreview);
    $("#bulkApplyBtn").on("click", bulkApply);
  }

  // ═════════════════════════════════════════════════════════════
  // ═══ EXPORTAR A EXCEL (SheetJS)                             ═══
  // ═════════════════════════════════════════════════════════════

  function generateAndDownloadExcel(autores, filename) {
    if (typeof XLSX === "undefined") {
      showNotification("SheetJS no está cargado en la página.", "danger", 8000);
      return;
    }

    const data = autores.map((a) => {
      // Filtramos el nombre actual para no repetirlo en "Otros Nombres"
      const otrosNombres = (a.nombres || [])
        .filter((n) => n !== a.nombre)
        .join(" | ");

      const activos = parseInt(a.activos, 10) || 0;
      const inactivos = parseInt(a.inactivos, 10) || 0;
      let estadoTexto = "Inactivo";

      if (activos > 0 && inactivos > 0) {
        estadoTexto = "Activo/Inactivo";
      } else if (activos > 0) {
        estadoTexto = "Activo";
      } else if (inactivos > 0) {
        estadoTexto = "Inactivo";
      }

      return {
        Estado: estadoTexto,
        "Nombre Principal": a.nombre || "(sin nombre)",
        "Otros Nombres": otrosNombres,
        "Enlace del Autor": a.enlace || "Sin enlace",
        "Total general": activos + inactivos,
        Activo: activos,
        Inactivo: inactivos,
      };
    });

    const ws = XLSX.utils.json_to_sheet(data);

    ws["!cols"] = [
      { wch: 18 }, // Estado
      { wch: 30 }, // Nombre Principal
      { wch: 40 }, // Otros Nombres
      { wch: 55 }, // Enlace
      { wch: 15 }, // Total general
      { wch: 10 }, // Activo
      { wch: 10 }, // Inactivo
    ];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Autores");

    const fecha = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(wb, `${filename}_${fecha}.xlsx`);
  }

  async function exportAutoresFromServer() {
    const params = new URLSearchParams(window.location.search);
    const main_search = params.get("search") || "";
    const main_ugoira = params.get("ugoira") || "";
    const secondary = $("#clientSearch").val() || "";
    const autorSearch = $("#autorSearch").val() || "";
    const main_col = params.get("col") || "";
    const secondary_col = $("#secondaryColSelect").val() || "";

    let baseUrl = `${API_BASE}export-autores.php?estado=${MODE === "restauracion" ? 0 : 1}`;
    if (main_search)
      baseUrl += `&main_search=${encodeURIComponent(main_search)}`;
    if (main_ugoira)
      baseUrl += `&main_ugoira=${encodeURIComponent(main_ugoira)}`;
    if (secondary)
      baseUrl += `&secondary_search=${encodeURIComponent(secondary)}`;
    if (autorSearch)
      baseUrl += `&autor_search=${encodeURIComponent(autorSearch)}`;
    if (main_col) baseUrl += `&main_col=${encodeURIComponent(main_col)}`;
    if (secondary_col)
      baseUrl += `&secondary_col=${encodeURIComponent(secondary_col)}`;

    const limit = 5000;
    let currentPage = 1;
    let allAutores = [];
    let totalReal = -1;

    let startTime = 0;
    let abortController = new AbortController(); // Controlador para cancelar el fetch
    let cancelado = false;

    // Mostrar modal de progreso
    const $modal = bootstrap.Modal.getOrCreateInstance(
      document.getElementById("exportProgressModal"),
    );
    $modal.show();
    $("#exportProgressBar").css("width", "0%").text("0%");
    $("#exportProgressText").text("Iniciando descarga...");
    $("#exportEtaText").text("Calculando tiempo...");
    $("#cancelExportBtn").prop("disabled", false);

    // Configurar botón de cancelar
    $("#cancelExportBtn")
      .off("click")
      .on("click", function () {
        cancelado = true;
        abortController.abort(); // Corta la petición actual
        $modal.hide();
        showNotification(
          "Exportación cancelada por el usuario.",
          "warning",
          5000,
        );
      });

    try {
      startTime = Date.now();

      while (totalReal === -1 || allAutores.length < totalReal) {
        if (cancelado) throw new Error("AbortError");

        const url = `${baseUrl}&page=${currentPage}&limit=${limit}`;
        const res = await fetch(url, {
          credentials: "same-origin",
          signal: abortController.signal,
        });

        const data = await res.json();

        if (!data.success)
          throw new Error(data.message || "Error del servidor.");

        if (totalReal === -1) {
          totalReal = data.total_real;
          if (totalReal === 0) {
            $modal.hide();
            showNotification("No hay autores para exportar.", "warning", 8000);
            return;
          }
        }

        allAutores = allAutores.concat(data.autores);
        currentPage++;

        // ═══ NEW: Calcular Progreso y ETA ═══
        const percent = Math.round((allAutores.length / totalReal) * 100);
        $("#exportProgressBar").css("width", `${percent}%`).text(`${percent}%`);
        $("#exportProgressText").text(
          `${allAutores.length.toLocaleString()} de ${totalReal.toLocaleString()} autores`,
        );

        const elapsedSeconds = (Date.now() - startTime) / 1000;
        const itemsPerSecond = allAutores.length / elapsedSeconds;
        const itemsRestantes = totalReal - allAutores.length;

        if (itemsPerSecond > 0 && itemsRestantes > 0) {
          const etaSeconds = Math.ceil(itemsRestantes / itemsPerSecond);
          const min = Math.floor(etaSeconds / 60);
          const seg = etaSeconds % 60;
          $("#exportEtaText").text(
            `Tiempo restante: ${min > 0 ? min + "m " : ""}${seg}s`,
          );
        } else {
          $("#exportEtaText").text("Finalizando...");
        }

        await new Promise((r) => setTimeout(r, 50)); // Respiración
      }

      // Terminado con éxito: Ocultar modal
      $modal.hide();

      // Generar Excel
      generateAndDownloadExcel(allAutores, "autores_filtrados");

      // Notificación final
      showNotification(
        `<i class="fas fa-check-circle me-1"></i> ¡Exportación completa! Se descargaron <strong>${allAutores.length.toLocaleString()}</strong> autores.`,
        "success",
        8000,
      );
    } catch (e) {
      $modal.hide();
      if (e.name === "AbortError" || e.message === "AbortError" || cancelado) {
        // El mensaje ya se mostró en el evento del botón cancelar
        console.log("Exportación cancelada por el usuario.");
      } else {
        showNotification(e.message || "Error al exportar.", "danger", 8000);
      }
    }
  }

  function exportAutoresSelected() {
    if (st.selectedAutores.size === 0) {
      showNotification(
        "No hay autores seleccionados para exportar.",
        "warning",
        8000,
      );
      return;
    }

    // Filtrar exactamente las filas seleccionadas (usando el ID único)
    const seleccionadosData = st.autoresData.filter((a) => {
      const uniqueId = a.enlace + "|||" + a.nombre;
      return st.selectedAutores.has(uniqueId);
    });

    generateAndDownloadExcel(seleccionadosData, "autores_seleccionados");
    showNotification(
      "Excel de seleccionados generado correctamente.",
      "success",
      5000,
    );
  }

  // ============================================================
  // INIT + DELEGACIÓN
  // ============================================================
  function init() {
    // Abrir modal
    $(document).on("click", "#autoresBtn", function () {
      st.paginaActual = 1;
      bootstrap.Modal.getOrCreateInstance(
        document.getElementById("autoresModal"),
      ).show();
      loadPage(1);
    });

    // Búsqueda interna
    $("#autorSearch").on("input", function () {
      clearTimeout(st.searchTimeout);
      st.searchTimeout = setTimeout(() => {
        st.paginaActual = 1;
        loadPage(1);
      }, 300);
    });

    $("#clearAutorSearch").on("click", function () {
      $("#autorSearch").val("");
      st.paginaActual = 1;
      loadPage(1);
    });

    // Per page
    $("#autoresPerPageTop, #autoresPerPageBottom").on("change", function () {
      const val = parseInt($(this).val(), 10) || 50;
      st.perPage = val;
      $("#autoresPerPageTop, #autoresPerPageBottom").val(val);
      st.paginaActual = 1;
      loadPage(1);
    });

    // Selección
    $("#selectAllAutores").on("click", selectAll);
    $("#deselectAllAutores").on("click", deselectAll);
    $("#checkAllAutoresHeader").on("change", function () {
      if ($(this).is(":checked")) selectAll();
      else deselectAll();
    });

    $(document).on("change", ".autor-checkbox", function () {
      const enlace = $(this).data("enlace");
      const nombre = $(this).data("nombre");
      const isChecked = $(this).is(":checked");
      if (!enlace) return;

      const uniqueId = enlace + "|||" + nombre;

      if (isChecked) {
        st.selectedAutores.add(uniqueId);
        $(this).closest("tr").addClass("selected-row");
      } else {
        st.selectedAutores.delete(uniqueId);
        $(this).closest("tr").removeClass("selected-row");
      }
      updateSelectionUI();
    });

    // Paginación
    $(document).on(
      "click",
      "#autoresPaginacionTop .page-link[data-page], #autoresPaginacionBottom .page-link[data-page]",
      function (e) {
        e.preventDefault();
        const page = parseInt($(this).data("page"), 10);
        if (!page || page === st.paginaActual) return;
        st.paginaActual = page;
        loadPage(page);
      },
    );

    // Acciones masivas
    $("#openSelectedAutores").on("click", openSelectedLinks);
    $("#copyAutorLinks").on("click", copySelectedLinks);

    // Refrescar
    $("#refreshAutores").on("click", () => loadPage(st.paginaActual));

    // Acciones de fila (delegadas)
    $(document).on("click", ".editar-autor", function (e) {
      e.preventDefault();
      openEditModal(
        $(this).data("enlace"),
        $(this).data("nombre"),
        $(this).attr("data-nombres"),
      );
    });
    $(document).on("click", ".ver-detalle-autor", function () {
      openDetalleSimple(
        $(this).data("enlace"),
        $(this).data("nombre"),
        $(this).data("total"),
      );
    });
    $(document).on("click", ".btn-detalle-multiple", function () {
      openDetalleMultiple($(this).data("enlace"), $(this).attr("data-nombres"));
    });
    $(document).on("click", ".abrir-autor", function () {
      const enlace = $(this).data("enlace");
      if (enlace) window.open(enlace, "_blank", "noopener,noreferrer");
    });
    $(document).on("click", ".copiar-enlace-autor", function (e) {
      e.preventDefault();
      const enlace = $(this).data("enlace");
      if (enlace) {
        copyToClipboard(enlace);
        showNotification("Enlace copiado al portapapeles", "success");
      } else {
        showNotification("Este autor no tiene enlace", "warning");
      }
    });
    $(document).on("click", ".filtrar-autor", function () {
      const nombre = $(this).data("nombre");
      $("#clientSearch").val(nombre).trigger("input");
      bootstrap.Modal.getOrCreateInstance(
        document.getElementById("autoresModal"),
      ).hide();
    });

    // Editar autor: guardar
    $("#guardarEditarAutor").on("click", saveEditAutor);

    // Toggle bloque editar enlace
    $("#btnEditarEnlaceAutor").on("click", function () {
      $("#bloqueEditarEnlace").toggleClass("d-none");
      if (!$("#bloqueEditarEnlace").hasClass("d-none")) {
        $("#editAutorEnlaceNuevo").trigger("focus");
      }
    });
    $("#btnCancelarEditarEnlace").on("click", function () {
      $("#bloqueEditarEnlace").addClass("d-none");
      $("#editAutorEnlaceNuevo").val("");
    });

    // Copiar enlace en modales de detalle
    $("#btnCopiarEnlaceDetalle").on("click", function () {
      const enlace = $("#abrirEnlaceDetalle").data("enlace");
      if (enlace) {
        copyToClipboard(enlace);
        showNotification("Enlace copiado al portapapeles", "success");
      } else {
        showNotification("No hay enlace para copiar", "warning");
      }
    });
    $("#abrirEnlaceDetalle").on("click", function () {
      const enlace = $(this).data("enlace");
      if (enlace) window.open(enlace, "_blank", "noopener,noreferrer");
    });
    $("#btnCopiarEnlaceMultiple").on("click", function () {
      const enlace = $("#detalleMultipleAutorModal").data("enlace");
      if (enlace) {
        copyToClipboard(enlace);
        showNotification("Enlace copiado al portapapeles", "success");
      } else {
        showNotification("No hay enlace para copiar", "warning");
      }
    });

    // Tooltip flotante
    $(document).on("mouseenter", ".badge-multiple-autor", function () {
      showTooltip($(this));
    });
    $(document).on("mouseleave", ".badge-multiple-autor", hideTooltip);
    $(window).on("scroll resize", hideTooltip);
    $("#autoresModal .table-responsive").on("scroll", hideTooltip);

    // ═══════════════════════════════════════════════════════════
    // ═══ NEW ═══ Inicializar módulo de bulk autores          ═══
    // ═══════════════════════════════════════════════════════════
    initBulkAutores();

    // ═══ NEW ═══ Eventos de exportación
    $(document).on("click", "#exportAutoresAllBtn", function (e) {
      e.preventDefault();
      exportAutoresFromServer();
    });

    $(document).on("click", "#exportAutoresSelectedBtn", function (e) {
      e.preventDefault();
      exportAutoresSelected();
    });

    // ═══ NEW: Evento para todos los botones de Copiar Autor ═══
    $(document).on("click", ".btn-copy-author", function (e) {
      e.preventDefault();
      const nombre = $(this).data("nombre");
      if (nombre) {
        copyToClipboard(nombre);
        showNotification(
          `Autor copiado: <strong>${escapeHtml(nombre)}</strong>`,
          "success",
          3000,
        );
      }
    });

    // ═══ NEW: Evento para el botón Pegar en el buscador ═══
    $("#pasteAutorSearch").on("click", async function () {
      try {
        const text = await navigator.clipboard.readText();
        if (text && text.trim() !== "") {
          $("#autorSearch").val(text.trim()).trigger("input");
        }
      } catch (err) {
        showNotification(
          "No se pudo acceder al portapapeles. Asegúrate de usar HTTPS o permitir el acceso en tu navegador.",
          "warning",
          5000,
        );
      }
    });
  }

  $(document).ready(init);
})(jQuery);
