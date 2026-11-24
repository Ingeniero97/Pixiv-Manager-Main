/**
 * pxm/core.js — Config, state y helpers compartidos.
 * Debe cargarse PRIMERO (antes que cualquier otro archivo de pxm/).
 */
(function (window) {
  "use strict";

  const CONFIG = window.APP_CONFIG || {};
  const API_BASE = CONFIG.apiBase || "api/";
  const MODE = CONFIG.mode || "index";
  const IS_REST = MODE === "restauracion";
  const BULK_ESTADO = IS_REST ? 1 : 0;
  const TARGET_LABEL = IS_REST ? "Activo" : "Restauración";

  // Fix #5: inicializar currentPage desde la URL para que refreshCurrentView
  // respete la página en la que está el usuario antes de cualquier AJAX.
  const _urlParams = new URLSearchParams(window.location.search);
  const _initialPage = Math.max(
    1,
    parseInt(_urlParams.get("page") || "1", 10) || 1,
  );

  const state = {
    selectedIds: new Set(),
    currentPage: _initialPage,
    itemsPerPage: parseInt(CONFIG.itemsPerPage) || 50,
    totalPages: 1,
    secondarySearch: "",
    primaryCol: _urlParams.get("col") || "",
    secondaryCol: _urlParams.get("scol") || "",
    searchTimeout: null,
    loading: false,
  };

  function escapeHtml(text) {
    if (text === null || text === undefined) return "";
    return String(text)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function numberFormat(n) {
    return Number(n || 0).toLocaleString("en-US");
  }

  function fallbackCopy(text) {
    const ta = document.createElement("textarea");
    ta.value = text;
    ta.style.position = "fixed";
    ta.style.opacity = "0";
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand("copy");
    } catch (e) {
      /* noop */
    }
    document.body.removeChild(ta);
  }

  function copyToClipboard(text) {
    if (!text) return;
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).catch(() => fallbackCopy(text));
    } else {
      fallbackCopy(text);
    }
  }

  function getSelectedIdsFromDOM() {
    const ids = [];
    jQuery("#resultsTable tbody .row-checkbox:checked").each(function () {
      const id = parseInt(String(jQuery(this).val()).replace(/,/g, ""), 10);
      if (!isNaN(id) && id > 0) ids.push(id);
    });
    return ids;
  }

  function setLoading(flag) {
    state.loading = flag;
    jQuery("#paginationTop, #paginationBottom").toggleClass(
      "pe-none opacity-50",
      flag,
    );
  }

  function updateHeaderBadges(total, activos, inactivos) {
    jQuery("#statsTotal").html(
      '<i class="fas fa-database me-1"></i> ' + numberFormat(total),
    );
    jQuery("#statsActivos").html(
      '<i class="fas fa-check-circle me-1"></i> ' + numberFormat(activos),
    );
    jQuery("#statsInactivos").html(
      '<i class="fas fa-times-circle me-1"></i> ' + numberFormat(inactivos),
    );
  }

  // ---------- Namespace público ----------
  window.PXM = window.PXM || {};
  window.PXM.config = { API_BASE, MODE, IS_REST, BULK_ESTADO, TARGET_LABEL };
  window.PXM.state = state;
  window.PXM.escapeHtml = escapeHtml;
  window.PXM.numberFormat = numberFormat;
  window.PXM.copy = copyToClipboard;
  window.PXM.fallbackCopy = fallbackCopy;
  window.PXM.getSelectedIds = getSelectedIdsFromDOM;
  window.PXM.setLoading = setLoading;
  window.PXM.updateHeaderBadges = updateHeaderBadges;

  // ---------- Backward compat para autores.js ----------
  window.__pxmEscapeHtml = escapeHtml;
  window.__pxmCopy = copyToClipboard;

  // Guard defensivo
  if (!window.PxmNotify) {
    console.error(
      "[pxm/core.js] notifications.js debe cargarse ANTES que pxm/core.js. " +
        "Revisa el orden de los <script> en views/layouts/footer.php.",
    );
  }
})(window);
