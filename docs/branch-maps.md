# Ubicación de sucursales

El formulario permite buscar una dirección o lugar, revisar el mapa, mover el marcador y pulsar **Usar esta ubicación**. Hasta aplicar el punto, las coordenadas anteriores no se reemplazan; una selección pendiente impide guardar por accidente. El nombre y la dirección oficiales no se sobrescriben con resultados del buscador.

Se guardan las columnas existentes latitude y longitude, con seis decimales y la validación de Branch. No se requiere migración. La consulta de sucursal permite abrir el punto registrado en modo de solo lectura. La captura manual permanece disponible y acepta también coordenadas 0,0.

## Proveedores y recursos

- Leaflet 1.9.4 se compila localmente en un módulo separado que se descarga al abrir el mapa; no se usa CDN ni Node en producción. Licencia BSD-2-Clause conservada en public/assets/licenses/leaflet.txt. Las imágenes auxiliares del paquete se copian a public/assets/css/images/.
- Cartografía: [OpenStreetMap Standard](https://operations.osmfoundation.org/policies/tiles/), con atribución visible, HTTPS y caché normal del navegador. Solo se solicitan teselas al abrir el mapa o seleccionar un resultado, sin precarga masiva.
- Búsqueda: [Photon](https://github.com/komoot/photon#demo-server), basada en OpenStreetMap. El servidor público admite uso moderado y no garantiza disponibilidad. El autocompletado consulta al escribir al menos tres caracteres, después de una pausa de 650 ms. Mantiene separación mínima de 1,1 segundos entre peticiones y caché en memoria de hasta 50 consultas por página. El botón Buscar y Enter permiten la búsqueda explícita; no hay búsquedas periódicas. Las sugerencias aparecen sobre el formulario, sin desplazarlo, y se recorren con flechas y Enter; Escape, Tab o un clic fuera cierran la lista.
- El navegador realiza las consultas HTTPS con sus certificados de confianza. No se modifica el almacén TLS de PHP, Docker ni del sistema. Solo se envía el texto escrito al buscar; abrir el mapa solicita la cartografía del área visible.

MAP_TILE_URL y MAP_GEOCODER_URL permiten sustituir proveedores sin cambiar código. El segundo debe ofrecer la respuesta GeoJSON de Photon. Se configuran como variables de entorno reales; .env no se carga automáticamente. Las URLs se validan como HTTPS y se amplía la CSP únicamente para sus orígenes, en las respuestas de Sucursales. El resto de páginas conserva la política original.

El buscador y los mapas necesitan internet. Si un proveedor falla, se conserva el formulario y puede utilizarse la captura manual. Este cambio incorpora selección y verificación en el administrador; no sustituye la representación relativa que ya existe en la página pública.

## Verificación

- PHP: sintaxis y php tests/run.php.
- Vistas aisladas: php tests/branch-maps-fixtures.php.
- Navegador: node tests/branch-maps-browser.mjs. Comprueba CSP, carga diferida, etiquetas como texto, caché, selección/aplicación, teclado, datos enviados al guardar, reintentos, coordenadas existentes y vista de solo lectura. Usa servidor y proveedores simulados, sin base de datos.
- Comprobación externa opcional: node tests/branch-maps-live-browser.mjs. Busca un lugar público y verifica teselas reales con HTTPS normal del navegador; no guarda sucursales.
