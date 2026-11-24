/**
 * main.js — Bootstrap de la pantalla principal.
 *
 * Toda la lógica vive en assets/js/pxm/*.js y se coordina a través
 * del namespace window.PXM. Este archivo solo dispara la inicialización
 * cuando el DOM está listo.
 *
 * Depende de:
 *   - notifications.js  (window.PxmNotify, window.showNotification)
 *   - pxm/core.js       (window.PXM.config, .state, helpers)
 *   - pxm/*.js          (módulos funcionales)
 */
(function () {
  "use strict";

  function bootstrap() {
    if (!window.PXM) {
      console.error(
        "[main.js] window.PXM no existe. Revisa el orden de los <script> en footer.php.",
      );
      return;
    }

    // 1) Inicializar módulos que se autoengancha por delegación
    PXM.initBulkRecords();
    PXM.initClearData();
    PXM.initExportModule();

    // 2) Delegación global de eventos (binding de todo lo demás)
    PXM.init();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootstrap);
  } else {
    bootstrap();
  }
})();
