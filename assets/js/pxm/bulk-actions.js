/**
 * pxm/bulk-actions.js — Acciones masivas sobre la selección actual:
 *   - copySelectedLinks    → obtiene enlaces y abre el modal
 *   - changeSelectedEstado → activa/desactiva en lote
 *   - batchByIds           → elimina/restaura por lista de IDs
 *
 * Depende de:
 *   - PXM.state, PXM.config, PXM.getSelectedIds  (core.js)
 *   - PXM.updateSelectionUI                       (selection.js)
 *   - PxmNotify                                    (notifications.js)
 */
(function ($) {
  "use strict";

  async function copySelectedLinks() {
    const ids = PXM.getSelectedIds();
    if (ids.length === 0) {
      PxmNotify.warning("No hay elementos seleccionados.");
      return;
    }

    try {
      const res = await fetch(PXM.config.API_BASE + "copy-links.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ ids, field: "Contenedor_Enlace_Album_href" }),
      });
      const data = await res.json();
      if (data.success) {
        $("#linksToCopy").val((data.links || []).join("\n"));
        bootstrap.Modal.getOrCreateInstance(
          document.getElementById("copyLinksModal"),
        ).show();
      } else {
        PxmNotify.danger(data.message || "Error al obtener enlaces.");
      }
    } catch (e) {
      PxmNotify.connectionError();
    }
  }

  async function changeSelectedEstado() {
    const ids = PXM.getSelectedIds();
    if (ids.length === 0) return;

    const accion = PXM.config.IS_REST ? "restaurar" : "desactivar";
    if (!PxmNotify.confirmBatch(accion, ids.length)) return;

    try {
      const res = await fetch(PXM.config.API_BASE + "change-estado.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ ids, estado: PXM.config.BULK_ESTADO }),
      });
      const data = await res.json();

      if (!data.success) {
        PxmNotify.danger(data.message || "Error al actualizar.");
        return;
      }

      const matched = parseInt(data.matched, 10) || 0;
      const changed = parseInt(data.changed, 10) || 0;

      if (matched === 0) {
        PxmNotify.warning("No se encontraron registros con esos IDs.");
        return;
      }

      // Plantillas 01/02/03: verde + rojo apilados verticalmente
      PxmNotify.batchResult({
        action: accion,
        matched,
        changed,
        target: PXM.config.TARGET_LABEL,
      });

      if (changed > 0) {
        setTimeout(() => PXM.refreshCurrentView(), 300);
      }
    } catch (e) {
      PxmNotify.connectionError();
    }
  }

  async function batchByIds() {
    const $btn = $("#confirmBatchDelete");
    if ($btn.prop("disabled")) return;

    const idsText = $("#batchDeleteIds").val().trim();
    if (!idsText) {
      PxmNotify.warning("Ingresa los IDs.");
      return;
    }

    const ids = idsText
      .split("\n")
      .map((s) => s.trim())
      .filter(Boolean);
    if (ids.length === 0) {
      PxmNotify.warning("No hay IDs válidos.");
      return;
    }

    const field = $("#batchDeleteField").val();

    // Validación de formato (Plantilla 04)
    let esInvalido;
    if (field === "Contenedor_ID_Enlace") {
      esInvalido = (id) => !/^\d+$/.test(id);
    } else if (field === "Contenedor_Enlace_Album_href") {
      esInvalido = (id) => !/^https?:\/+/i.test(id);
    } else {
      esInvalido = () => false;
    }

    if (ids.some(esInvalido)) {
      PxmNotify.invalidField(field);
      return;
    }

    const accion = PXM.config.IS_REST ? "restaurar" : "desactivar";
    if (!PxmNotify.confirmBatch(accion, ids.length)) return;

    $btn.prop("disabled", true);

    try {
      const res = await fetch(PXM.config.API_BASE + "batch-delete.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ ids, field, estado: PXM.config.BULK_ESTADO }),
      });
      const data = await res.json();

      if (!data.success) {
        PxmNotify.danger(data.message || "Error al actualizar.");
        return;
      }

      const matched = parseInt(data.matched, 10) || 0;
      const changed = parseInt(data.changed, 10) || 0;

      // Caso 0: nada coincidió → modal ABIERTO para corregir (Opción A)
      if (matched === 0) {
        PxmNotify.noMatch(field);
        return;
      }

      // Plantillas 01/02/03: verde + rojo apilados verticalmente
      PxmNotify.batchResult({
        action: accion,
        matched,
        changed,
        target: PXM.config.TARGET_LABEL,
      });

      bootstrap.Modal.getOrCreateInstance(
        document.getElementById("batchDeleteModal"),
      ).hide();

      if (changed > 0) {
        setTimeout(() => PXM.refreshCurrentView(), 300);
      }
    } catch (e) {
      PxmNotify.connectionError();
    } finally {
      $btn.prop("disabled", false);
    }
  }

  window.PXM = window.PXM || {};
  window.PXM.copySelectedLinks = copySelectedLinks;
  window.PXM.changeSelectedEstado = changeSelectedEstado;
  window.PXM.batchByIds = batchByIds;
})(jQuery);
