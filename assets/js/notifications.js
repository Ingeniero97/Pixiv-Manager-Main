/**
 * notifications.js — Sistema centralizado de notificaciones.
 *
 * Convenciones:
 *   - Se apilan verticalmente en la esquina superior derecha.
 *   - Duración por defecto: 8000ms.
 *   - Colores: success (verde) / danger (rojo) / warning (naranja) / info (azul).
 *   - Para operaciones batch se emite un mensaje por resultado:
 *       verde = cambiados, rojo = ya estaban en destino.
 *
 * API pública: window.PxmNotify
 *   .success(msg, dur?)     .danger(msg, dur?)     .warning(msg, dur?)     .info(msg, dur?)
 *   .batchResult({ action, matched, changed, target })
 *   .invalidField(fieldName)
 *   .noMatch(fieldName)
 *   .connectionError(context?)
 *   .copySuccess(count)
 *   .recordEstadoChanged(estado, wasChanged)
 *   .recordUpdated()
 *   .confirmBatch(action, count)
 *   .confirmSingle(action, titulo, id)
 *
 * Compatibilidad: también expone window.showNotification(msg, type, dur)
 * y window.__pxmNotify para código legacy (main.js / autores.js).
 */
(function (global) {
    'use strict';

    const DEFAULT_DURATION = 8000;
    const STACK_ID         = 'pxmNotifications';
    const MAX_VISIBLE      = 6;

    let $container = null;

    function ensureContainer() {
        if ($container && $container.length && $container.closest('body').length) {
            return $container;
        }
        $container = $('#' + STACK_ID);
        if (!$container.length) {
            $container = $('<div id="' + STACK_ID + '"></div>').css({
                position: 'fixed',
                top: '20px',
                right: '20px',
                zIndex: 9999,
                display: 'flex',
                flexDirection: 'column',
                gap: '10px',
                maxWidth: '480px',
                pointerEvents: 'none',
            }).appendTo('body');
        }
        return $container;
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function push(type, html, duration) {
        const $c = ensureContainer();

        // Limitar acumulación visual
        const $alerts = $c.children('.alert');
        if ($alerts.length >= MAX_VISIBLE) {
            $alerts.first().alert('close');
        }

        const $alert = $(`
            <div class="alert alert-${type} alert-dismissible fade show" role="alert"
                 style="min-width: 300px; margin-bottom: 0; pointer-events: auto;">
                ${html}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        `);
        $c.append($alert);

        setTimeout(() => {
            if ($alert.closest('body').length) {
                $alert.alert('close');
            }
        }, duration || DEFAULT_DURATION);

        return $alert;
    }

    // ============================================================
    // API PÚBLICA
    // ============================================================
    const PxmNotify = {

        // ---------- Primitivas ----------
        success(msg, dur) { return push('success', msg, dur); },
        danger(msg, dur)  { return push('danger',  msg, dur); },
        warning(msg, dur) { return push('warning', msg, dur); },
        info(msg, dur)    { return push('info',    msg, dur); },

        // ---------- Batch (Plantillas 01/02/03) ----------
        /**
         * @param {Object} opts
         * @param {'desactivar'|'restaurar'} opts.action
         * @param {number} opts.matched   Total encontrados
         * @param {number} opts.changed   Total modificados
         * @param {string} opts.target    'Restauración' | 'Activo'
         */
        batchResult({ action, matched, changed, target }) {
            const already = Math.max(0, (matched || 0) - (changed || 0));
            if ((matched || 0) === 0) return;

            const verbo = action === 'restaurar' ? 'restauró' : 'desactivó';

            if (changed > 0 && already > 0) {
                // Plantilla 01: verde + rojo apilados
                PxmNotify.success(`Se ${verbo} ${changed} registro(s).`);
                PxmNotify.danger(`${already} registro(s) ya estaba(n) en ${target}.`);
            } else if (changed > 0) {
                // Plantilla 02: verde
                PxmNotify.success(`Se ${verbo} ${changed} registro(s).`);
            } else if (already > 0) {
                // Plantilla 03: rojo
                PxmNotify.danger(`${already} registro(s) ya estaba(n) en ${target}.`);
            }
        },

        // ---------- Validaciones ----------
        invalidField(fieldName) {
            return PxmNotify.warning(
                `Valores inválidos para el campo <strong>${escapeHtml(fieldName)}</strong>.`
            );
        },

        noMatch(fieldName) {
            return PxmNotify.warning(
                `Ningún registro coincide con los valores en el campo <strong>${escapeHtml(fieldName)}</strong>.`
            );
        },

        connectionError(context) {
            const ctx = context ? ` al ${escapeHtml(context)}` : '';
            return PxmNotify.danger(`Error de conexión${ctx}. Intenta nuevamente.`);
        },

        // ---------- Atajos semánticos ----------
        copySuccess(count) {
            const n = parseInt(count, 10) || 0;
            return PxmNotify.success(
                n > 1 ? `${n} enlace(s) copiado(s) al portapapeles.` : 'Enlace copiado al portapapeles.'
            );
        },

        recordEstadoChanged(estado, wasChanged) {
            if (!wasChanged) {
                return PxmNotify.info(
                    `El registro ya estaba ${estado === 1 ? 'activo' : 'inactivo'}. No se modificó nada.`
                );
            }
            return PxmNotify.success(
                estado === 1 ? 'Registro activado.' : 'Registro desactivado.'
            );
        },

        recordUpdated() {
            return PxmNotify.success('Registro actualizado correctamente.');
        },

        // ---------- Confirmaciones ----------
        confirmBatch(action, count) {
            const verbo = action === 'restaurar' ? 'restaurar' : 'desactivar';
            return window.confirm(
                `¿${verbo.charAt(0).toUpperCase() + verbo.slice(1)} ${count} elemento(s)?`
            );
        },

        confirmSingle(action, titulo, id) {
            if (action === 'restaurar') {
                return window.confirm(`¿Restaurar "${titulo}" (ORDEN #${id})?`);
            }
            return window.confirm(`¿Estás seguro de desactivar #${id}?`);
        },
    };

    // ============================================================
    // EXPOSICIÓN GLOBAL
    // ============================================================
    global.PxmNotify = PxmNotify;

    // Compat: showNotification(msg, type, dur) — usado por main.js y autores.js
    if (!global.showNotification) {
        global.showNotification = function (msg, type, dur) {
            type = type || 'info';
            const method = PxmNotify[type];
            return typeof method === 'function'
                ? method.call(PxmNotify, msg, dur)
                : PxmNotify.info(msg, dur);
        };
    }

    // Compat: __pxmNotify — usado por autores.js como fallback
    if (!global.__pxmNotify) {
        global.__pxmNotify = global.showNotification;
    }

})(window);