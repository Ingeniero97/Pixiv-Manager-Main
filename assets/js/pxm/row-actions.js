/**
 * pxm/row-actions.js — Acciones individuales por fila:
 *   - loadDetails           → modal de detalles
 *   - loadEditForm + Tagify → modal de edición (incluye edición in-situ)
 *   - changeRecordEstado    → activar/desactivar una fila
 *
 * Depende de:
 *   - PXM.escapeHtml, PXM.copy  (core.js)
 *   - PxmNotify                 (notifications.js)
 *   - Tagify, bootstrap.Modal   (CDN)
 */
(function ($) {
  "use strict";

  function loadDetails(id) {
    $.get(PXM.config.API_BASE + "get-details.php", { id })
      .done((html) => {
        $("#viewModalBody").html(html);
        bootstrap.Modal.getOrCreateInstance(
          document.getElementById("viewModal"),
        ).show();
      })
      .fail(() => PxmNotify.connectionError("cargar detalles"));
  }

  function loadEditForm(id) {
    $.get(PXM.config.API_BASE + "get-edit-form.php", { id })
      .done((html) => {
        $("#editModalBody").html(html);
        bootstrap.Modal.getOrCreateInstance(
          document.getElementById("editModal"),
        ).show();

        // ═══ INICIALIZAR TAGIFY ═══
        setTimeout(() => {
          const input = document.querySelector("#input_tag03");
          if (!input) return;

          let rawValue = input.value;
          input.value = "";

          const tagify = new Tagify(input, {
            duplicates: false,
            dropdown: { enabled: 0 },
            editTags: false, // Desactivamos edición nativa para controlarla nosotros
            delimiters: /[\r\n]+|\\n|,/,
            templates: {
              tag(tagData) {
                const tag = tagData.value;
                const regex =
                  /^(.*?[\u4e00-\u9faf\u3040-\u309f\u30a0-\u30ff\)\]])(\s*)([a-zA-Z].*)$/;
                const match = tag.match(regex);

                if (match) {
                  const asiatico = match[1].trim();
                  const ingles = match[3].trim();
                  return `<tag title='${PXM.escapeHtml(tag)}' spellcheck="false" class="tagify__tag d-inline-flex align-items-center" style="background: transparent; border: 1px solid #0d6efd; padding: 0; border-radius: 20px;" __isValid="true" value="${PXM.escapeHtml(tag)}">
                                        <div class="tagify__tag-text d-flex align-items-stretch" style="font-size: 0.8rem; font-weight: 500; margin: 0;">
                                            <span style="background-color: #0d6efd !important; color: white !important; padding: 0.4em 0.8em; display: flex; align-items: center; border-radius: 20px 0 0 20px;">${PXM.escapeHtml(asiatico)}</span>
                                            <span style="background-color: rgba(13, 110, 253, 0.15) !important; color: #0d6efd !important; padding: 0.4em 0.8em; display: flex; align-items: center; border-radius: 0 20px 20px 0;"><span class="fw-bold me-2 text-secondary opacity-75">|</span>${PXM.escapeHtml(ingles)}</span>
                                        </div>
                                        <x title='' class='tagify__tag__removeBtn' role='button'></x>
                                    </tag>`;
                }
                const esAsiatico =
                  /[\u4e00-\u9faf\u3040-\u309f\u30a0-\u30ff]/.test(tag);
                const bg = esAsiatico ? "#0d6efd" : "rgba(13, 110, 253, 0.15)";
                const color = esAsiatico ? "white" : "#0d6efd";

                return `<tag title='${PXM.escapeHtml(tag)}' spellcheck="false" class="tagify__tag d-inline-flex align-items-center" style="background: transparent; border: 1px solid #0d6efd; padding: 0; border-radius: 20px;" __isValid="true" value="${PXM.escapeHtml(tag)}">
                                    <div class="tagify__tag-text" style="background: ${bg} !important; color: ${color} !important; padding: 0.4em 0.8em; border-radius: 20px; font-size: 0.8rem; font-weight: 500;">${PXM.escapeHtml(tag)}</div>
                                    <x title='' class='tagify__tag__removeBtn' role='button'></x>
                                </tag>`;
              },
            },
          });

          tagify.addTags(rawValue);
          // Guardar instancia
          $("#editRecordForm").data("tagify03", tagify);

          // ═══ BOTONES DE QUITAR, COPIAR Y PEGAR EN TAG_03 ═══

          // Botón Quitar (Vaciar todo)
          $("#btn-clear-tag03")
            .off("click")
            .on("click", function () {
              if (
                confirm(
                  "¿Estás seguro de que deseas quitar todas las etiquetas del Tag_03?",
                )
              ) {
                tagify.removeAllTags();
                PxmNotify.info("Todas las etiquetas han sido quitadas.", 3000);
              }
            });

          // Botón Copiar
          $("#btn-copy-tag03")
            .off("click")
            .on("click", function () {
              const textToCopy = tagify.value.map((t) => t.value).join("\n");
              if (!textToCopy) {
                PxmNotify.warning("No hay etiquetas para copiar.", 3000);
                return;
              }
              PXM.copy(textToCopy);
              PxmNotify.success("Etiquetas copiadas al portapapeles.", 3000);
            });

          // Botón Pegar
          $("#btn-paste-tag03")
            .off("click")
            .on("click", async function () {
              try {
                const text = await navigator.clipboard.readText();
                if (!text || text.trim() === "") return;

                const newTags = text
                  .split(/[\r\n]+|\\n|,/)
                  .map((t) => t.trim())
                  .filter((t) => t !== "");

                if (newTags.length > 0) {
                  tagify.addTags(newTags);
                  PxmNotify.success("Etiquetas pegadas correctamente.", 3000);
                }
              } catch (err) {
                PxmNotify.warning(
                  "El navegador bloqueó el acceso al portapapeles. Por seguridad, esto solo funciona en HTTPS o localhost.",
                  6000,
                );
              }
            });

          // ═══ EDICIÓN MANUAL IN-SITU CON BOTONES ═══
          $(tagify.DOM.scope).on("dblclick", ".tagify__tag", function (e) {
            e.stopPropagation();
            e.preventDefault();

            const $tagEl = $(this);
            const tagValue = $tagEl.attr("value");
            if (!tagValue) return;

            if ($tagEl.find(".tag-edit-input").length > 0) return;

            const $textContainer = $tagEl.find(".tagify__tag-text");
            const $removeBtn = $tagEl.find(".tagify__tag__removeBtn");
            const originalHtml = $textContainer.html();

            $removeBtn.hide();

            const $editWrapper = $(
              '<div class="d-flex align-items-center tag-edit-input" style="padding: 2px;"></div>',
            );
            const $input = $(
              '<input type="text" class="form-control form-control-sm me-1" style="min-width: 120px; height: 24px; padding: 2px 8px; font-size: 0.8rem; border-radius: 20px;">',
            ).val(tagValue);

            // ═══ FIX: tabindex="-1" evita que el teclado enfoque los botones ═══
            const $saveBtn = $(
              '<button type="button" tabindex="-1" class="btn btn-sm btn-success p-0 me-1 btn-save-tag" title="Guardar" style="width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;"><i class="fas fa-check" style="font-size: 10px;"></i></button>',
            );
            const $cancelBtn = $(
              '<button type="button" tabindex="-1" class="btn btn-sm btn-danger p-0 btn-cancel-tag" title="Cancelar" style="width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;"><i class="fas fa-times" style="font-size: 10px;"></i></button>',
            );

            $editWrapper.append($input, $saveBtn, $cancelBtn);
            $textContainer.html($editWrapper);
            $input.focus().select();

            const saveEdit = () => {
              let newValue = $input.val().trim();

              $textContainer.html(originalHtml);
              $removeBtn.show();

              if (newValue === "") return;

              if (newValue !== tagValue) {
                const currentValues = tagify.value.map((t) => t.value);
                const idx = currentValues.indexOf(tagValue);

                if (idx > -1) {
                  currentValues[idx] = newValue;
                  tagify.removeAllTags();
                  tagify.addTags(currentValues);
                }
              }
            };

            const cancelEdit = () => {
              $textContainer.html(originalHtml);
              $removeBtn.show();
            };

            $saveBtn.on("mousedown", function (ev) {
              ev.preventDefault();
            });
            $cancelBtn.on("mousedown", function (ev) {
              ev.preventDefault();
            });

            $saveBtn.on("click", function (ev) {
              ev.preventDefault();
              ev.stopPropagation();
              saveEdit();
            });

            $cancelBtn.on("click", function (ev) {
              ev.preventDefault();
              ev.stopPropagation();
              cancelEdit();
            });

            // ═══ FIX: stopPropagation evita que Tagify reaccione al Delete/Backspace ═══
            $input.on("keydown", function (ev) {
              ev.stopPropagation(); // No dejar que Tagify escuche las teclas

              if (ev.key === "Enter") {
                ev.preventDefault();
                saveEdit();
              } else if (ev.key === "Escape") {
                ev.preventDefault();
                cancelEdit();
              }
            });

            // Guardar si hace clic fuera
            $input.on("blur", function (ev) {
              const relatedTarget = ev.relatedTarget;
              // Si el clic fue en los botones, dejamos que ellos manejen el evento
              if (
                $(relatedTarget).hasClass("btn-save-tag") ||
                $(relatedTarget).closest(".btn-save-tag").length > 0
              )
                return;
              if (
                $(relatedTarget).hasClass("btn-cancel-tag") ||
                $(relatedTarget).closest(".btn-cancel-tag").length > 0
              )
                return;

              // Pequeño delay para evitar conflictos
              setTimeout(() => {
                if ($.contains(document, $input[0])) saveEdit();
              }, 150);
            });
          });
        }, 100);
      })
      .fail(() => PxmNotify.connectionError("cargar el formulario"));
  }

  async function changeRecordEstado(id, estado) {
    const cleanId = parseInt(String(id).replace(/,/g, ""), 10);
    if (isNaN(cleanId)) return;

    try {
      const res = await fetch(PXM.config.API_BASE + "change-estado.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ ids: [cleanId], estado: parseInt(estado, 10) }),
      });
      const data = await res.json();

      if (!data.success) {
        PxmNotify.danger(data.message || "No se pudo cambiar el estado.");
        return;
      }

      const matched = parseInt(data.matched, 10) || 0;
      const changed = parseInt(data.changed, 10) || 0;

      if (matched === 0) {
        PxmNotify.warning("Registro no encontrado.");
        return;
      }

      PxmNotify.recordEstadoChanged(estado, changed > 0);

      if (changed > 0) {
        setTimeout(() => PXM.refreshCurrentView(), 300);
      }
    } catch (e) {
      PxmNotify.connectionError();
    }
  }

  window.PXM = window.PXM || {};
  window.PXM.loadDetails = loadDetails;
  window.PXM.loadEditForm = loadEditForm;
  window.PXM.changeRecordEstado = changeRecordEstado;
})(jQuery);
