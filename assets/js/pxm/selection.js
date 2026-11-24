/**
 * pxm/selection.js — Selección de filas de la tabla principal.
 */
(function ($) {
  "use strict";

  function updateSelectionUI() {
    const $visibleCheckboxes = $("#resultsTable tbody .row-checkbox");
    const $checked = $("#resultsTable tbody .row-checkbox:checked");
    const count = $checked.length;

    const $badge = $("#badgeSeleccionados");
    if (count > 0) {
      $badge.text(count).css("display", "inline-flex");
    } else {
      $badge.css("display", "none");
    }

    $("#copyLinksBtn, #deactivateSelectedBtn").prop("disabled", count === 0);
    $("#deselectAllButton").prop("disabled", count === 0);

    $("#checkAllHeader").prop(
      "checked",
      $visibleCheckboxes.length > 0 &&
        $checked.length === $visibleCheckboxes.length,
    );
  }

  function selectAllVisible() {
    $("#resultsTable tbody .row-checkbox:not(:disabled)").each(function () {
      if (!$(this).is(":checked")) {
        $(this).prop("checked", true).trigger("change");
      }
    });
  }

  function deselectAllVisible() {
    $("#resultsTable tbody .row-checkbox:checked").each(function () {
      $(this).prop("checked", false).trigger("change");
    });
  }

  window.PXM = window.PXM || {};
  window.PXM.updateSelectionUI = updateSelectionUI;
  window.PXM.selectAll = selectAllVisible;
  window.PXM.deselectAll = deselectAllVisible;
})(jQuery);