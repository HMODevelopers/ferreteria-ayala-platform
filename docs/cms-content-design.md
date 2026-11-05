# Contenido del sitio: diseño y flujo de edición

La pantalla utiliza todo el ancho de trabajo y presenta categorías, una explicación del contenido que administra cada una, estados en español, listas o estados vacíos y una guía para preparar y publicar. Se retiran los identificadores de módulos y los textos dirigidos a desarrolladores.

Crear contenido, editar y consultar versiones usan el dialog lateral compartido del layout administrativo, con el mismo cierre, carga y estilo que los formularios existentes. La biblioteca de imágenes conserva su acceso independiente. La ruta GET /admin/cms admite editor=1 para creación y el id existente para edición; sin JavaScript sigue disponible el formulario como página. No se introducen rutas ni dependencias.

El POST mantiene CSRF, permisos, clave de operación, versión de concurrencia y validaciones existentes. Dentro del modal, la respuesta 422 conserva valores y muestra el error sin abandonar la lista. El guardado correcto actualiza la lista de la categoría con el HTML de la redirección ya recibida y conserva la confirmación del servidor; evita una segunda petición que consumiría ese mensaje. Los formularios de contenido no se almacenan en la caché del modal, evitando reutilizar claves de operación o versiones antiguas al reabrir. El manejo adicional de POST se limita a data-cms-editor-form.

El formulario agrupa información, imagen y alcance, publicación y detalles del tipo. Explica identificador, sucursal, orden, estados y fechas en Hermosillo; las opciones para buscadores se presentan como opcionales. El historial muestra campos con nombres legibles, incluido el contenido anterior y sus metadatos, sin exponer JSON en la interfaz. Todo texto dinámico conserva e().

Verificación: 1307 comprobaciones locales, 385 archivos PHP con sintaxis válida, sintaxis JS y build aprobados. Chrome: 80 comprobaciones del nuevo diseño en las siete categorías y anchos 360/768/1440/2560, modal, permisos, valores preservados, POST con respuestas de prueba, edición e historial; 51 del menú y 63 de publicación. Las capturas de escritorio y móvil se revisaron. Las pruebas de presentación no escriben en MySQL. Las rutas reales de lista y editor responden 303 sin sesión.

Ejecutores:

    docker exec ferreteria-ayala-php-1 php tests/m11-lint.php
    docker exec ferreteria-ayala-php-1 php tests/run.php
    docker exec ferreteria-ayala-php-1 php tests/cms-design-fixtures.php
    docker compose --env-file .env.example run --rm assets npm run build
    node tests/cms-design-browser.mjs
    node tests/admin-navigation-browser.mjs
    node tests/publication-settings-browser.mjs
    node tests/m11-http-integration.mjs

Capturas y evidencias locales: tests/artifacts/cms-design/, ignoradas por Git. No se modifica .env, no se crea contenido de demostración en desarrollo y no se ejecuta commit ni push.

La primera regresión autenticada detectó la pérdida de la confirmación al seguir dos veces la redirección. Se corrigió y se añadió una comprobación de confirmación visible al servidor de presentación. El fixture fallido se respaldó y verificó antes de limpiar exclusivamente el esquema aislado: m11-failed-isolated-20261104-194233.json, SHA-256 b19c54dd6afa9cd652682b7b36fd218c48a1ac99e9a24d2e5a67a16dcdbfcaaa.

La confirmación de guardado requiere respuesta completa: el POST del editor usa Accept: text/html, porque adminView omite el flash en fragmentos XMLHttpRequest. La respuesta 422 sigue extrayéndose dentro del modal. La recuperación de la segunda prueba verificó el respaldo m11-failed-isolated-20261104-194717.json (SHA-256 3e2c42d5e3777bf1997052c2b88d0448a108fc810a3fcc599e5c2072430bb180) y dejó cero objetos en el esquema aislado antes del siguiente recorrido.

Cierre: regresión HTTP/Chrome M11 aprobada con 80 aserciones y limpieza completa de sus fixtures y esquema aislado. La confirmación visible, publicación, archivado y programación se comprobaron contra el controlador y la persistencia reales. Los datos de desarrollo permanecen fuera de este recorrido. Evidencia: tests/artifacts/m11-http-pass.json.

## Editores específicos por categoría

Cada categoría tiene una vista propia en app/Views/admin/cms/editors/: banners, promociones, eventos, testimonios, páginas, presentación y navegación. El modal conserva su infraestructura común, pero cambia título, composición, controles, ayudas y botón de guardado. Los datos propios preceden a la publicación. Eventos usa fecha nativa y lugar arriba; testimonios usa autor y selector de calificación; navegación muestra texto, destino obligatorio y posición, conservando en campos ocultos los valores anteriores de imagen y descripción. Las opciones de identificación y metadatos quedan después de los datos principales; en creación y errores se abren para que el identificador requerido pueda completarse. Buscadores se muestra únicamente en páginas; los metadatos anteriores de otros tipos se preservan al guardar.

Validación de este ajuste: 1377 comprobaciones locales, 393 archivos PHP con sintaxis válida, sintaxis de los ejecutores JS, build y 128 comprobaciones Chrome. Incluye los siete editores en 360 y 1440 píxeles, títulos específicos, campos propios, ausencia de campos de otras categorías, controles de fecha/calificación, orden respecto a publicación, creación, error y edición. Capturas revisadas. Persistencia, permisos y servicios no se modificaron; no se repitió la suite SQL/HTTP autenticada para este ajuste de vistas. El ejecutor HTTP existente se actualizó al texto Guardar página y pasó sintaxis.
