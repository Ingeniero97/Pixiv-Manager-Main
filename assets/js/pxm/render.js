/**
 * pxm/render.js — Construcción de filas y render de resultados AJAX.
 */
(function ($) {
  "use strict";

  function truncate(str, n) {
    if (!str) return "";
    const clean = String(str).replace(/\s+/g, " ");
    return clean.length > n ? clean.substring(0, n) + "..." : clean;
  }

  function createTableRow(item) {
    const orden = String(item.ORDEN || "").replace(/,/g, "");
    const titulo = item.Enlace_Titulo || "Sin título";
    const autor = item.Contenedor_Autor_Album || "";
    const album = item.Contenedor_Nombre_Album || "";
    const autorUrl = item.Contenedor_Enlace_Autor_Album || "#";
    const img = item.Enlace_Img_Original || "";
    const hasImg = img && img !== "NO IMG-ORIGINAL POR UGOIRA";
    const ugoira = item.Enlace_Ugoira || "";
    const fecha1 = item.Enlace_Fecha_Formato_01 || "";
    const fecha2 = item.Enlace_Fecha_Formato_02 || "";
    const linkHref = item.Contenedor_Enlace_Album_href || "";
    const estado = parseInt(item.Estado, 10);
    const e = PXM.escapeHtml;

    const estadoBadge =
      estado === 1
        ? '<span class="badge badge-estado badge-activo"><i class="fas fa-check-circle me-1"></i> Activo</span>'
        : '<span class="badge badge-estado badge-inactivo"><i class="fas fa-times-circle me-1"></i> Inactivo</span>';

    const imgCell = hasImg
      ? `<a href="${e(img)}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary btn-action" title="Ver imagen"><i class="fas fa-eye"></i></a>`
      : '<span class="badge bg-secondary">Sin img</span>';

    const autorCell = autor
      ? `<div class="d-flex flex-column">
                   <a href="${e(autorUrl)}" target="_blank" rel="noopener noreferrer"
                      class="text-decoration-none" title="${e(autor)}">
                       ${e(truncate(autor, 30))}
                   </a>
                   ${album ? `<small class="text-muted" title="${e(album)}">${e(truncate(album, 30))}</small>` : ""}
               </div>`
      : "";

    const accionEstado = PXM.config.IS_REST
      ? `<button class="btn btn-success btn-sm btn-action activate-item"
                       data-id="${e(orden)}" data-title="${e(titulo)}"
                       title="Activar (Restaurar)">
                   <i class="fas fa-check"></i>
               </button>`
      : `<button class="btn btn-danger btn-sm btn-action deactivate-item"
                       data-id="${e(orden)}" data-title="${e(titulo)}"
                       title="Desactivar (Eliminar)">
                   <i class="fas fa-trash"></i>
               </button>`;

    return `
        <tr data-id="${e(orden)}" data-estado="${estado}">
            <td><input type="checkbox" class="form-check-input row-checkbox" value="${e(orden)}"></td>
            <td><strong>${e(orden)}</strong></td>
            <td>${
              item.Contenedor_Tipo === "R-18"
                ? '<span class="badge bg-danger">R-18</span>'
                : `<span class="badge bg-secondary">${e(item.Contenedor_Tipo || "")}</span>`
            }</td>
            <td class="text-center">${e(item.Contenedor_Numero_Hoja || "")}</td>
            <td>${estadoBadge}</td>
            <td>${autorCell}</td>
            <td title="${e(titulo)}">${e(truncate(titulo, 50))}</td>
            <td class="text-center">${imgCell}</td>
            <td class="text-center">${ugoira ? `<span class="badge bg-info" title="${e(ugoira)}">${e(ugoira.substring(0, 20))}</span>` : ""}</td>
            <td><small class="text-muted" title="${e(fecha1)}">${e(fecha2)}</small></td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-info btn-sm btn-action view-details" data-id="${e(orden)}" title="Ver detalles">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-warning btn-sm btn-action edit-item" data-id="${e(orden)}" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    ${accionEstado}
                    <button class="btn btn-primary btn-sm btn-action copy-link" data-link="${e(linkHref)}" title="Copiar enlace">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </td>
        </tr>`;
  }

  function renderServerResults(items) {
    const $tbody = $("#resultsTable tbody");

    if (!items || items.length === 0) {
      $tbody.html(`
                <tr>
                    <td colspan="11" class="text-center py-4">
                        <i class="fas fa-search fa-2x text-muted mb-3"></i>
                        <h5>No se encontraron resultados</h5>
                    </td>
                </tr>
            `);
      PXM.state.selectedIds.clear();
      PXM.updateSelectionUI();
      return;
    }

    let html = "";
    items.forEach((item) => {
      html += createTableRow(item);
    });
    $tbody.html(html);

    PXM.state.selectedIds.clear();
    PXM.updateSelectionUI();
  }

  window.PXM = window.PXM || {};
  window.PXM.renderServerResults = renderServerResults;
})(jQuery);