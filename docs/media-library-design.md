# Biblioteca de imágenes y carga guiada

La biblioteca pública usa todo el ancho de trabajo, una guía de tres pasos, un estado vacío con una acción para comenzar y tarjetas de imágenes con descripción, dimensiones, peso optimizado y acceso al archivo. Se explica que subir una imagen no la publica automáticamente y cómo seleccionarla desde un formulario de contenido. Se retiran los identificadores técnicos de módulos de esta pantalla.

La carga pública usa el dialog lateral compartido, con acceso GET /admin/media?upload=1 y respaldo como página nativa sin JavaScript. El formulario conserva POST /admin/media/upload, multipart/form-data, CSRF, kind=public, parent_id=0 y clave de operación. Permite arrastrar una imagen o usar el selector nativo, muestra una vista previa local mediante data URL compatible con la política CSP existente y orienta sobre descripción, autorización de uso, formatos y dimensiones. Los controles del navegador son ayudas; la validación y autorización definitivas permanecen en el servidor.

Un error controlado de carga pública devuelve 422 con descripción y clave preservadas. Dentro del modal se conserva también el archivo seleccionado para reintentar. El guardado correcto actualiza la galería y conserva el mensaje de confirmación del servidor. Se mantiene la auditoría de fallos. Las galerías de productos, comprobantes y evidencias conservan sus vistas, acciones y permisos existentes.

Verificación: 1391 comprobaciones locales, 398 archivos PHP con sintaxis válida, JS y build. Chrome: 38 comprobaciones de biblioteca en 360/768/1440/2560 px, estados vacío/con imágenes, vista previa con CSP, arrastre, rechazo por formato/peso, multipart, valores preservados, confirmación y acceso sin JavaScript; 128 comprobaciones de regresión de los editores por tipo. Capturas de escritorio y móvil revisadas. El servidor de presentación utiliza archivos sintéticos y no escribe en MySQL.

Ejecutores:

    docker exec ferreteria-ayala-php-1 php tests/m11-lint.php
    docker exec ferreteria-ayala-php-1 php tests/run.php
    docker exec ferreteria-ayala-php-1 php tests/media-library-fixtures.php
    docker compose --env-file .env.example run --rm assets npm run build
    node tests/media-library-browser.mjs
    node tests/cms-design-browser.mjs
    node tests/m11-http-integration.mjs

Evidencias locales ignoradas por Git: tests/artifacts/media-library/. No se modifican .env, esquema, servicios de almacenamiento ni reglas de negocio.

Cierre autenticado: PASS, 81 aserciones HTTP/Chrome M11, incluyendo nueva carga pública desde el modal y rechazo del archivo falso; se mantienen los recorridos de productos, comprobantes, evidencias y editores por tipo. Esquema aislado y archivos de prueba limpios al finalizar. Las rutas reales de biblioteca y formulario sin sesión responden 303. No se crearon imágenes ni cuentas en desarrollo.
