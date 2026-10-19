# Verificación de la plataforma

Fecha de última ejecución: 18 de octubre de 2026. Entorno: Ubuntu, Node 22.22.1, npm 9.2.0 y PHP CLI 8.5.4. El código utiliza sintaxis compatible con PHP 8.2; no se ejecutó una matriz de versiones 8.2/8.3/8.4. PDO está habilitado, pero este entorno no tiene ningún controlador PDO ni `pdo_mysql`.

## Ejecutado

- Sintaxis de todos los PHP: sin errores.
- Pruebas PHP `tests/run.php`: **118 comprobaciones** correctas. Conservan las comprobaciones públicas y añaden carga/validación de configuración PDO, conexión diferida, transacciones, opciones seguras, ausencia del driver, descubrimiento, orden, lotes, doble aplicación, rollback, fallos parciales, bloqueos y CLI.
- `npm run dev`: observación iniciada correctamente y detenida después de comprobar el modo desarrollo.
- `npm run build`: Tailwind 4.3.3 y esbuild correctos; assets locales minificados y fuentes/licencias copiadas.
- Instalación/auditoría npm: **0 vulnerabilidades reportadas** después del override de `@parcel/watcher` a 2.6.x. Tailwind CLI fijaba 2.5.1 vulnerable; override limitado a herramienta de observación local.
- Navegador a 360, 390, 768, 1024 y 1440px: sin overflow horizontal; todas las imágenes cargadas. Capturas completas y de viewport en tests/artifacts (ignoradas por Git).
- Flechas/indicadores del hero con áreas táctiles mínimas de 44×44px, filtro por categoría, búsqueda, estado vacío y reset: correctos.
- Agregar producto, cambiar cantidad, contador y descarga TXT: correctos.
- Menú móvil, Escape, ciclo Tab/Shift+Tab dentro del diálogo, recuperación de foco y link de categoría: correctos.
- Cambio dinámico de reduced motion: velocidad del slider 0ms.
- HTTP GET / 200, HEAD / 200, POST / 405, ruta inexistente 404; archivos privados, router de desarrollo y asset inexistente no expuestos.
- Sin errores de JavaScript ni errores relevantes de consola durante los flujos probados.
- Sin JavaScript: hero principal y seis productos visibles; acciones dependientes de JS permanecen disabled.

## Pendiente / límite del alcance

La integración `npm run test:mysql` no se ejecutó: falta `pdo_mysql` y no se ha autorizado modificar una base MariaDB de pruebas. M00 no está completo hasta que esa prueba pase. No se ha probado Docker/minilab, Apache/cPanel real, FTP, TLS, instalación en subdominio, iOS Safari, Android real ni lectores de pantalla. No se confirma stock, precios ni ningún flujo administrativo porque no existen en esta fase.

## Revisión de aprobación

La Home fue aprobada como base el 17 de octubre de 2026. Permanecen pendientes el logo oficial, fotos reales y datos de contacto verificados para reemplazar los placeholders. La infraestructura de persistencia está implementada sin tablas comerciales; el administrador y M01–M14 permanecen fuera del alcance.
