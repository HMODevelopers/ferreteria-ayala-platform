# Verificación de la primera entrega

Fecha de última ejecución: 17 de octubre de 2026. Entorno: Ubuntu, Node 22.22.1, PHP CLI 8.5.4 y Google Chrome mediante Playwright. El código utiliza sintaxis compatible con PHP 8.2; no se ejecutó una matriz de versiones 8.2/8.3/8.4.

## Ejecutado

- Sintaxis de todos los PHP: sin errores.
- Pruebas PHP `tests/run.php`: **48 comprobaciones** correctas de rutas, escape, entradas array, redirecciones, rutas de vistas, unicidad, contratos demo y assets.
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

No se ha probado Apache/cPanel real, FTP, TLS, instalación en subdominio, iOS Safari, Android real ni lectores de pantalla. El gesto táctil requiere revisión en dispositivo; Swiper lo provee y los controles alternativos se probaron. No hay auditoría WCAG formal ni métricas Lighthouse/Core Web Vitals. No se confirma stock, precios ni ningún flujo administrativo porque no existen en esta fase.

## Revisión de aprobación

La Home fue aprobada como base el 17 de octubre de 2026. Permanecen pendientes el logo oficial, fotos reales y datos de contacto verificados para reemplazar los placeholders. Persistencia y administrador se incorporarán por incrementos con pruebas específicas; no forman parte de la suite pública actual.
