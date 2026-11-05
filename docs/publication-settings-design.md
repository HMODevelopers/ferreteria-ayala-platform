# Diseño de publicación del sitio

Rediseño visual del 4 de noviembre de 2026, sobre M11 ya implementado. Se conservan rutas, CSRF, permisos, formularios POST, estados esperados y reglas de habilitación existentes. No se cambian ajustes, módulos, usuarios ni datos de desarrollo.

## Presentación

- Resumen de los estados guardados: sitio público, recepción global y aviso de privacidad. Una activación global no se presenta como recepción efectiva de solicitudes.
- Contenido fluido al 100 % del área de trabajo, sin ancho máximo ni centrado adicional; mantiene únicamente los márgenes generales del panel.
- Tarjetas de publicación y recepción en columnas; apiladas en móvil, con selectores nativos y botones compactos.
- Lista de condiciones pendientes: sitio activado, aviso publicado y alguna sucursal configurada. El aviso se consulta mediante el repositorio legal existente, sin guardar información nueva.
- Sucursales en filas independientes con nombre escapado, estado guardado, selector y acción propia.
- Estado vacío que explica la ausencia de sucursales activas; registrar/ver sucursales y revisar contenido/legales aparecen únicamente con sus permisos.
- Textos de operación sin códigos internos de módulos. Tipografía, iconos SVG locales y tonos de estado mantienen el lenguaje naranja/negro/blanco.

Los indicadores no cambian al editar un selector: reflejan lo guardado hasta recibir la respuesta del servidor. Todos los cambios requieren el botón de guardar existente. La publicación y recepción global continúan separadas de la configuración por sucursal.

## Validación

PASS: 381 archivos PHP con sintaxis válida; 1265 comprobaciones locales (incluidas quince nuevas de estados, escape, permisos y contrato de formularios); build Tailwind/esbuild; sintaxis del ejecutor Chrome.

Chrome: 45 comprobaciones aprobadas, estados sin sucursales/con sucursales/con permisos limitados, responsive 360/768/1024/1440/1920 px, tarjetas en columnas y apiladas, botones compactos, foco de teclado, controles nativos sin JavaScript, estado guardado sin actualización optimista y campos originales de CSRF/expected/branch_id. Sin errores de consola, scripts o solicitudes fallidas. Capturas de escritorio y móvil inspeccionadas visualmente.

Las vistas de prueba usan el componente y layout reales, renderizados en archivos ignorados por Git y servidos localmente en modo de solo lectura. No crean cuentas, no usan MySQL y no simulan un guardado comercial. Las comprobaciones de formulario inspeccionan FormData, sin enviar ni alterar ajustes. Los servicios y la persistencia no cambiaron.

Ejecutores:

    docker exec ferreteria-ayala-php-1 php tests/m11-lint.php
    docker exec ferreteria-ayala-php-1 php tests/run.php
    docker exec ferreteria-ayala-php-1 php tests/publication-settings-fixtures.php
    docker compose --env-file .env.example run --rm assets npm run build
    node tests/publication-settings-browser.mjs

Evidencias: tests/artifacts/publication-settings/pass.json y capturas empty-*.png / branches-*.png. No se modifica .env ni se ejecutan migraciones. Los cambios del menú anteriores se conservan.


## Corrección de ancho fluido

Por indicación del propietario se retiró el máximo de 1280 px y el centrado automático. La pantalla ocupa el 100 % del área de contenido, alineada con los márgenes generales del panel y adaptable al ancho disponible junto al menú.

Build y git diff --check PASS. Chrome: 63 comprobaciones aprobadas a 360/768/1024/1440/1920/2560 px, con verificación geométrica de ambos bordes y del ancho completo del área de trabajo. Captura de 1920 px revisada visualmente. No cambiaron PHP, formularios ni configuración.
