# Cierre integral de auditoría — Ferretería Ayala

Actualizado: 16 de noviembre de 2026. El detalle y estado de los 26 hallazgos está en `CONTROL_CIERRE_AUDITORIA_FERRETERIA_AYALA.md`.

## A. Resumen ejecutivo

La auditoría técnica quedó cerrada en el alcance implementado. Se resolvió la incompatibilidad de historial 61→68→75→76, se recuperaron de forma controlada los estados preservados y se ejecutaron las integraciones SQL/HTTP centrales con la identidad M10 dedicada. La base comercial no recibió pruebas: conserva 76 migraciones y 107 tablas; la base aislada terminó con cero objetos.

Además de verificar AY-10, AY-16, AY-17 y AY-23 con fixtures SQL reales, la ejecución encontró y corrigió un defecto adicional de AY-04/M10: una entrega podía asignarse contra cobertura aprobada pero aún en tránsito. Ahora la asignación pendiente se limita al apartado físico realmente recibido, incluso entre varias entregas concurrentes.

## B. Defectos corregidos en esta intervención

- Historial de migraciones: M10 base vuelve a estar delimitado en 61 para M11, M11 agrega 7 hasta 68, el flujo central agrega 7 hasta AY-04/75 y la migración 76 sólo participa en su suite de cierre.
- AY-04/M10: las entregas ya no pueden reservar mercancía vinculada todavía en tránsito; se agregó contabilidad de asignación pendiente por partida.
- AY-10: historial durable de importaciones, confirmación idempotente por token y auditoría dentro de la misma transacción que crea productos.
- AY-16: el reporte parte de la recepción original y reúne solicitudes, seguimientos y documentos sin doble conteo. La primera atención solo se calcula con eventos posteriores o iguales a la recepción.
- AY-17: se agregaron cotizaciones por vendedor, Kardex/movimientos, mínimos y agotados, entregas parciales, importaciones/errores y auditoría. Pantalla, XLSX y PDF usan las mismas filas y columnas; importaciones y auditoría conservan permisos adicionales.
- AY-23: los costos de cada factura se normalizan a la unidad base usando su conversión válida y solo se compara contra documentos realmente anteriores por fecha e identificador. No se actualizan precios de venta.
- Infraestructura HTTP: lanzadores M08, M09, M10, M11 y workflow funcionan localmente por defecto y conservan Docker como opción explícita; Chrome se detecta por canal o `CHROME_PATH`.
- AY-02/03: la Home publicada y el catálogo de recepción paginan y filtran datos sintéticos reales, muestran precio efectivo de la sucursal y conservan cantidades entre búsquedas, páginas, recarga y navegación de historial.
- AY-19: el endpoint autenticado se validó con 9,999–10,002 filas reales en PDF/XLSX, sin truncamiento silencioso.
- AY-22: se indujeron errores HTTP público y autenticado; referencia, respuesta y log privado quedaron correlacionados y sanitizados. Los inductores temporales fueron retirados.

## C. Hallazgos cerrados y verificados

- AY-04, AY-05, AY-06, AY-07: cerrados y verificados en el flujo SQL/HTTP central.
- AY-10, AY-16, AY-17, AY-18, AY-21 y AY-23: cerrados con fixtures MariaDB reales y limpieza aislada.
- AY-15 y AY-24: conservan su cierre verificado previo.
- AY-02 y AY-03: cerrados con 93 aserciones HTTP/Chrome y conciliación SQL aislada.
- AY-19: cerrado con descargas HTTP reales en ambos formatos y rechazo por encima del límite.
- AY-22: cerrado localmente con excepciones inducidas; sólo la retención/rotación del hosting queda como evidencia externa.

## C.1 Clasificación final de pendientes

- **A. Defectos técnicos resueltos y verificados:** AY-02, AY-03, AY-04, AY-05, AY-06, AY-07, AY-10, AY-15, AY-16, AY-17, AY-18, AY-19, AY-21, AY-22, AY-23 y AY-24.
- **B. Funcionalidades técnicamente pendientes:** AY-08, AY-09, AY-11, AY-20 y AY-26. No se presentan como cerradas.
- **C. Información, aprobación o infraestructura externa:** AY-01, AY-12, AY-13 y AY-25, además de las decisiones empresariales que condicionan AY-08/09/11/20/26.

## D. Pruebas ejecutadas

- `php tests/run.php`: PASS, 1627 controles.
- `php tests/mysql-quote-workflow-integration.php`: PASS, 41 controles SQL AY-04/workflow.
- `php tests/mysql-m10-integration.php`: PASS, 90 controles registrados; reservas M08, transferencias M09, entregas M10, concurrencia, idempotencia y rollback.
- `node tests/m10-http-integration.mjs`: PASS, 12 controles backend registrados más navegador autenticado, CSRF, alcance, permisos y cuatro anchos.
- `node tests/quote-workflow-http-integration.mjs`: PASS, 65 controles SQL/navegador, incluidos PDF, endpoints negativos y editor sin acceso comercial.
- `node tests/m08-http-integration.mjs` y `node tests/m09-http-integration.mjs`: PASS, Chrome autenticado, CSRF, permisos, cuatro anchos y limpieza.
- `node tests/m11-http-integration.mjs`: PASS, 93 aserciones; AY-02/03 con 33 variantes activas, Home y selector paginados, filtros, precios por sucursal, persistencia y recepción administrativa.
- `node tests/ay19-http-integration.mjs`: PASS, 40,002 fixtures y límites 9,999/10,000/10,001/10,002 en PDF/XLSX.
- AY-22 HTTP temporal: PASS público y autenticado; correlación y sanitización verificadas, sin dejar rutas inductoras.
- `php tests/mysql-audit-closure-integration.php`: PASS, 17 controles SQL AY-10/16/17/18/21/23 con migración 76.
- `npm run build`, sintaxis PHP completa y `git diff --check`: PASS.
- PDF y XLSX: firmas válidas y mismas filas/columnas obtenidas desde el fixture de entregas parciales.
- Total mínimo con conteo emitido por las suites: 1,933 controles/aserciones (1627 regresión + 90 M10 + 41 AY-04 SQL + 65 AY-04 HTTP + 93 M11 HTTP + 17 cierre SQL), además de M08/M09 SQL/HTTP, AY-19, AY-22, build y sintaxis cuyos ejecutores no emiten total granular.

## E. Base de datos y migraciones

- Principal: `ferreteria_ayala`, identidad `ayala_app@%`, 76 migraciones y 107 tablas, comprobada sólo en lectura.
- Aislada: `ferreteria_ayala_m10_limpia_test`, identidad `ayala_m10_test@%`, MariaDB 10.11.15, cero objetos al terminar.
- Los estados preservados se limpiaron sólo tras verificar `DATABASE()`, `CURRENT_USER()`, historial, lote, nombres de migración, inventario de objetos y datos semilla/fixture conocidos.
- No se alteró el historial para ocultar inconsistencias y no se ejecutó ningún rollback sobre `ferreteria_ayala`.

## F. Intervención mínima requerida del propietario

Las identidades M08, M09 y M10 están operativas y deben conservarse únicamente para pruebas. Para cerrar la evidencia externa todavía se requiere un destino aislado autorizado de restauración y acceso/evidencia del entorno cPanel/Apache real.

## G. Pendientes de contenido o decisión expresa

- Privacidad oficial, catálogo publicable, módulos y sucursales receptoras para AY-01.
- Formato XLSX oficial y reglas de actualización para completar técnicamente AY-09; el historial durable actual ya está verificado.
- Políticas de lotes, caducidades y series para completar AY-08.
- Matriz aprobada de eventos/destinatarios internos para AY-11.
- Evidencia del hosting, retención y destino de restauración para AY-13.
- Datos oficiales de sucursales, servicios, horarios y mapas para AY-20.
- Formato físico de etiquetas y hardware para la impresión de AY-26.
- SMTP permanece aplazado; no se habilitaron envíos externos.

## H. Riesgos técnicos abiertos

- AY-08, AY-09, AY-11, AY-20 y AY-26 todavía contienen trabajo técnico no implementado y decisiones/datos empresariales pendientes.
- La rotación/retención del log AY-22 depende del hosting y no puede acreditarse localmente.
- Restauración independiente y comportamiento real de cPanel/Apache continúan sin evidencia externa.

## I. Estado de preparación

El sistema administrativo está preparado para validación con el cliente dentro del alcance implementado. Los faltantes restantes están identificados como funcionalidades no implementadas que requieren definición empresarial, o como infraestructura/contenido externo; no bloquean la comprobación técnica de los defectos corregidos.

No se inició el rediseño de la Home. El trabajo público posterior queda separado de estos pendientes y deberá usar contenido oficial, privacidad, catálogo y sucursales reales.

## Dictámenes

**A. CIERRE TÉCNICO DE AUDITORÍA: PARCIAL.**

Justificación: los defectos corregidos tienen regresión, SQL real, concurrencia/rollback cuando aplica, HTTP autenticado y limpieza aislada. AY-08/09/11/20/26 siguen fuera del cierre por decisiones o funcionalidad no implementada; AY-19/22 ya no conservan bloqueos técnicos locales.

**B. PREPARADO PARA VALIDACIÓN CON EL CLIENTE: SÍ.**

Justificación: los flujos administrativos implementados y corregidos están listos para validación funcional. La validación deberá reconocer por separado los contenidos, decisiones e infraestructura que el cliente aún debe proporcionar; SMTP y el rediseño de la Home siguen fuera de esta etapa.
