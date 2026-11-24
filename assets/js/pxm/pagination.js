/**
 * pxm/pagination.js — Paginación superior/inferior tras búsqueda AJAX.
 */
(function ($) {
  "use strict";

  function updateServerPagination(totalPages, currentPage, totalItems) {
    PXM.state.totalPages = totalPages;
    PXM.state.currentPage = currentPage;

    const infoTextBase = `Página ${currentPage} de ${totalPages}`;

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

    if (totalPages <= 1) {
      const single =
        makeLi(1, '<i class="fas fa-angle-double-left"></i>', { disabled: true }) +
        makeLi(1, '<i class="fas fa-angle-left"></i>', { disabled: true }) +
        makeLi(1, "1", { active: true }) +
        makeLi(1, '<i class="fas fa-angle-right"></i>', { disabled: true }) +
        makeLi(1, '<i class="fas fa-angle-double-right"></i>', { disabled: true });
      $("#paginationTop, #paginationBottom").html(single);

      const finalText = `${infoTextBase} • ${PXM.numberFormat(totalItems)} resultados filtrados`;
      $("#paginationInfoTop").html('<i class="fas fa-list-ol me-1"></i>' + finalText);
      $("#paginationInfoBottom").text(finalText);
      return;
    }

    const delta = 2;
    const set = new Set([1, totalPages, currentPage]);
    for (let i = currentPage - delta; i <= currentPage + delta; i++) {
      if (i >= 1 && i <= totalPages) set.add(i);
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
      currentPage > 1
        ? makeLi(1, '<i class="fas fa-angle-double-left"></i>')
        : makeLi(1, '<i class="fas fa-angle-double-left"></i>', { disabled: true });
    html +=
      currentPage > 1
        ? makeLi(currentPage - 1, '<i class="fas fa-angle-left"></i>')
        : makeLi(1, '<i class="fas fa-angle-left"></i>', { disabled: true });

    range.forEach((item) => {
      if (item.dots) html += makeLi(0, "…", { dots: true });
      else
        html += makeLi(item.page, String(item.page), {
          active: item.page === currentPage,
        });
    });

    html +=
      currentPage < totalPages
        ? makeLi(currentPage + 1, '<i class="fas fa-angle-right"></i>')
        : makeLi(totalPages, '<i class="fas fa-angle-right"></i>', { disabled: true });
    html +=
      currentPage < totalPages
        ? makeLi(totalPages, '<i class="fas fa-angle-double-right"></i>')
        : makeLi(totalPages, '<i class="fas fa-angle-double-right"></i>', { disabled: true });

    $("#paginationTop, #paginationBottom").html(html);

    const finalText = `${infoTextBase} • ${PXM.numberFormat(totalItems)} resultados filtrados`;
    $("#paginationInfoTop").html('<i class="fas fa-list-ol me-1"></i>' + finalText);
    $("#paginationInfoBottom").text(finalText);
  }

  window.PXM = window.PXM || {};
  window.PXM.updateServerPagination = updateServerPagination;
})(jQuery);