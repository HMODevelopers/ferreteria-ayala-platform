# Integración M10 · Entregas de mercancía

Implementación local del 3 de noviembre de 2026. Incremento de entregas activado y verificado en desarrollo; las ejecuciones históricas se distinguen de las realizadas para este cierre.

## Modelo y ciclo operativo

Cuatro migraciones aditivas conservan las 57 anteriores y elevan el esquema a 61. Crean nueve tablas: vehicles, drivers, delivery_runs, deliveries, delivery_items, delivery_stops, delivery_events, delivery_event_items y delivery_reservation_consumptions. InnoDB, utf8mb4, cantidades DECIMAL(18,6), costo DECIMAL(14,2), FKs RESTRICT, claves únicas para folio, evento, partida, parada y movimiento. Los rollbacks rechazan registros históricos y permisos delegados.

Los estados oficiales se conservan: pending (pendiente), preparing (preparando), assigned (asignada), in_transit (en tránsito), delivered (entregada), failed (intento fallido), cancelled (cancelada). Crear registra asignación documental sin apartar otra vez. Preparar valida apartado recibido y fija cantidades preparadas sin salida física; asignar cierra la preparación y, a domicilio, exige ruta de misma sucursal y fecha. Despachar consume todas las partidas preparadas de esa entrega una vez. Confirmar puede registrar cantidades parciales; permanece en tránsito hasta recibir todas. Varias entregas permiten cumplir parcialmente una cotización.

Editar cantidades se permite únicamente pendiente, con versión optimista. Cada creación y edición conserva sus cantidades en eventos independientes. Crear entregas distintas nunca permite superar lo documentado: bajo el bloqueo de cotización se suma lo asignado a entregas no canceladas. El pendiente comercial es documentado menos recibido; pendiente físico del viaje es despachado menos recibido; sin asignar excluye entregas abiertas y cumplidas. No se suman cantidades de unidades diferentes para producir porcentajes ambiguos; el listado indica partidas completas.

## Integración M08, M05 y M09

Sólo la versión emitida actual de una cotización aceptada es elegible. Se conserva su FK, cliente y sucursal; precios, importes e instantáneas emitidas no cambian. La entrega registra dirección, referencia manual de ubicación, receptor acordado, fecha y costo explícito sin modificar el importe comercial ni registrar cobros.

QuoteReservationService es propietario de la atribución del consumo: primero apartado local de la partida M08 y después asignaciones realmente recibidas M09 para la misma versión/variante. delivery_reservation_consumptions conserva cada origen exacto, cantidad y evento físico. Nunca utiliza una reserva ajena ni unidades todavía en tránsito.

InventoryManagementService::recordDeliveryWithinTransaction reutiliza el saldo/libro M05. Después de atribuir el consumo, libera esa cantidad de reservado y registra adjustment_out con source_type delivery_event y source_id evento:partida, junto con FK única al movimiento. Físico y reservado disminuyen por la misma cantidad; disponible se conserva. Confirmar recepción no vuelve a mover inventario. Documento, cantidades, consumo, movimiento, saldo y auditoría confirman o revierten en la misma transacción.

La extensión mínima de cancelación M08 resta los consumos históricos antes de liberar el pendiente y conserva released_quantity separado del consumo. M09 hace lo mismo con destination_released. Los contratos agregan argumentos opcionales con valores predeterminados: las suites históricas funcionan sin esquema M10. AppKernel conecta el repositorio de consumo sólo cuando M10 está migrado.

## Incidencias y cancelación

Cancelar una entrega sólo es posible antes de salida: libera su asignación documental, conservando el apartado de la cotización aceptada. Cancelar la cotización libera exclusivamente lo aún no consumido. Una entrega pendiente de salida queda bloqueada si su origen se canceló; una entrega ya despachada puede conservar y confirmar su recepción.

El intento fallido identifica estructuradamente las cantidades despachadas no recibidas, motivo, actor y fecha. Reanudar vuelve a tránsito sin nuevo egreso. No se borra historia, no se reintegra físico y no se inventan pérdidas o devoluciones. La disposición de mercancía que retorna o se pierde exige política y evento físico trazable aprobados; no forma parte de este incremento de entregas.

## Logística y privacidad

Vehículos y repartidores tienen código estable, ficha básica, sucursal, estado y disponibilidad manual. Repartidores no son usuarios. Las rutas son configuraciones históricas de vehículo/repartidor/fecha; cada entrega a domicilio tiene una parada única y una posición única dentro de su ruta. No hay GPS, optimización de recorridos, mantenimiento o seguimiento de personas.

Receptor y observaciones se conservan en los eventos de confirmación, sin documentos de identidad ni datos sensibles. Las fotografías opcionales requieren el mecanismo privado/validado de medios M11 y quedan diferidas: no se creó una subida insegura, ruta pública de evidencia ni almacenamiento paralelo. La vista HTML conserva la identidad provisional; no se ofrece PDF ni factura fiscal inexistente.

## Seguridad

Once permisos deliveries.*: view, create, edit, prepare, assign, dispatch, confirm, incident, cancel, history y resources. La migración únicamente los asigna a GLOBAL_ADMIN. El permiso dispatch autoriza y registra conjuntamente la salida física; incident cubre el intento fallido y su reanudación. Sesión M02, permiso por sucursal y CSRF se validan en cada escritura HTTP; los servicios validan de nuevo alcance, estado y cantidades. Consultas y formularios también respetan sucursales. Auditoría comparte la transacción y no copia contactos, contraseñas ni tokens.

## Concurrencia e idempotencia

Se bloquea primero cotización, luego versión/entrega, sucursal, partidas y saldos en orden de variante. M08 y M09 comparten el bloqueo de cotización. Cada evento tiene operation_key única y hash del pedido/actor/versión; reenvío idéntico devuelve el mismo evento y una clave con contenido distinto se rechaza. lock_version impide ediciones perdidas. No se mantienen transacciones durante el viaje ni se reintentan automáticamente deadlocks/timeouts. JavaScript sólo evita dobles clics; el servidor conserva las garantías sin JS.

## Rutas y ejecución

- GET /admin/deliveries: búsqueda, estado, sucursal y paginación.
- GET/POST /admin/deliveries/create: selección de documento, partidas y alta.
- GET /admin/deliveries/detail: documento, progreso, historial y acciones.
- POST /admin/deliveries/action: editar, preparar, asignar, despachar, confirmar, incidencia, reanudar y cancelar.
- GET/POST /admin/deliveries/resources: vehículos, repartidores y rutas manuales.

Ejecutores: docker exec ferreteria-ayala-php-1 php tests/run.php; docker exec ferreteria-ayala-php-1 php tests/mysql-m10-integration.php; node tests/m10-http-integration.mjs. En una máquina con PHP instalado también npm test y npm run test:mysql:m10. Build: docker compose --env-file .env.example run --rm assets npm run build. Node sólo participa en desarrollo.

## Aislamiento y activación

El .env privado contiene identidad M10 independiente, autorizada explícitamente para la red Docker local 172.18.0.0/16 y exclusivamente ferreteria_ayala_test. Privilegios exactos: SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, TRIGGER, EVENT, EXECUTE y REFERENCES; sin GRANT OPTION, acceso a desarrollo ni privilegios globales administrativos. REFERENCES permite FKs en MySQL 8.4; TRIGGER/EVENT/EXECUTE permiten demostrar inventario de objetos ocultos. Root se usa sólo para aprovisionamiento autorizado, nunca como ejecutor normal.

Preflight exige endpoint, autorización exacta, identidad, motor, privilegios, inventario vacío y exclusión asesora. No crea/elimina bases, no usa TRUNCATE ni desactiva FKs. Las pruebas generan fixtures exclusivamente aislados y sólo limpian después de aprobar. Ante fallo se preserva el estado.

bin/activate-m10-development.php --apply exige desarrollo local, 57 historiales anteriores exactos, cuatro adiciones y evidencias SQL/HTTP recientes con hashes del código y aserciones ejecutadas. Genera respaldo privado, compara JSON/SHA-256, restaura y compara todas las filas en la base aislada vacía y retira esa restauración antes de migrar desarrollo. Luego verifica conservación de tablas previas, historial, permisos y ausencia de entregas ficticias. DDL conserva commits implícitos: un error exige inspección.

## Resultados y límites

Cierre definitivo: npm test PASS con 1168 comprobaciones M00–M10; 336 archivos PHP válidos; 21 archivos JS/MJS válidos; build Tailwind local y git diff --check PASS. SQL M10 definitivo y HTTP/Chrome PASS, incluida consola, saldos finales y limpieza completa. Las regresiones reales SQL y HTTP de M08 y M09 también aprobaron y dejaron cero objetos. La Home pasó cinco tamaños y login tres. Las ejecuciones fallidas previas se documentan separadamente y no se usaron para activar. No se declara cPanel/Apache/FTP real, MariaDB remoto, dispositivos físicos ni lectores de pantalla probados.

M05 aún no tiene un motor operativo completo para consumir identidades de lotes/series: M10 rechaza variantes con esas identidades registradas. No inicia M11–M14, notificaciones, pagos, CFDI ni procedimientos completos de devolución. Desarrollo requiere los catálogos reales del negocio; no se introducen fixtures allí. Todo queda local y sin staging, commit o push. Siguiente módulo oficial: M11.

## Incidencias de QA recuperadas

Antes de cada repetición se inspeccionó el esquema sintético completo (72 tablas), historial y grafo conocido; se guardó una copia privada de DDL/filas y se comparó íntegramente contra el estado antes de retirar únicamente los fixtures de esa ejecución mediante el rollback oficial. Desarrollo permaneció en 57 migraciones durante estas recuperaciones.

1. Aserción de navegador sensible a mayúsculas en «INTENTO FALLIDO». Respaldo m10-failed-browser.json, SHA-256 121de46e3b22ee0cd7cb19ad1df8c42a9cb2924de9ab347adaa37df1bf596497.
2. Importación TransferRules incorrecta en la nueva vista del listado. Se cambió a DeliveryRules y se añadió una regresión de renderizado. Respaldo m10-failed-browser-list.json, SHA-256 870583a0a8428c88fca089d766b2b5022a56571f4c0203b473483b6644ffa29d.
3. Tras aprobar Chrome, el ejecutor PHP falló al comprobar el saldo por un nombre de clase sin namespace. Se corrigió la referencia y se repitió el cierre completo. Respaldo m10-failed-http-completion.json, SHA-256 44481c28b0ed61198de52ab29a87e6a8e3e1d4bca991e52146f567355a9d0b4e.

Estas copias preservan estados fallidos de integración; no se confunden con el respaldo previo a migrar desarrollo. Las ejecuciones fallidas no generan evidencia de aprobación para el activador.

4. Durante la revisión final se movió el cálculo de pendientes al repositorio. Una referencia residual en la vista se detectó por HTTP y se corrigió; se añadieron cuatro pruebas de renderizado completo para los estados operativos. Respaldo m10-failed-detail-projection.json, SHA-256 2cc7611554e9172a86cf196338c9c1469084b51e6d029957be892be9d972d844.

5. Una ejecución completó las operaciones funcionales y falló al comprobar consola por ERR_NO_BUFFER_SPACE de Chrome. Se preservó el estado; la inspección no encontró procesos headless huérfanos, presión de memoria ni agotamiento evidente de conexiones. Se agregó diagnóstico de solicitudes fallidas y se repitió sin filtrar el error. Respaldo m10-failed-browser-network.json, SHA-256 22df0fd614c2b4185a9f7e1a402c1a0956aadee703062adc7ffc8a9772dfdb9e. No se atribuye una causa definitiva sin evidencia.

## Activación y comprobación final

Desarrollo: APP_ENV=development, mysql-local, ferreteria_ayala, MySQL 8.4.10 y PHP 8.2.35; PDO con prepares nativos y desfase SQL UTC 00:00:00. Se verificaron 61 historiales acumulados. Las 57 migraciones anteriores permanecieron sin modificaciones. Las nueve tablas M10 son InnoDB/utf8mb4_unicode_ci, con 23 FKs restrictivas y 20 índices únicos incluyendo PRIMARY. Existen once permisos/asignaciones nuevas para GLOBAL_ADMIN.

Respaldo privado previo: storage/backups/m10-pre-migration-20261104-021113.json. SHA-256: c89958b2c4648b04b39c234af2a773230a059edfe4670fbfab0b6c81d091e328. JSON decodificado, hash comprobado, restauración independiente y comparación completa de filas: PASS. Las 63 tablas anteriores conservaron sus datos. Los archivos heredan ACL NTFS limitadas al propietario, SYSTEM y Administradores; no se confunde chmod del bind mount con la protección NTFS real. El nombre del respaldo usa UTC, aunque la fecha local del cierre es 3 de noviembre.

Las nueve tablas nuevas quedaron vacías. Sucursales, variantes, clientes, cotizaciones, saldos y movimientos de desarrollo permanecen en cero: no se cargaron fixtures allí. La base aislada terminó con cero tablas, vistas, triggers, eventos y rutinas después de las pruebas y de la restauración.

Rutas reales tras activar: Home y login 200; entregas, alta, detalle, recursos, cotizaciones y transferencias sin sesión 303; /.env 404. El recorrido autenticado usa exclusivamente identidades sintéticas en la base aislada. Capturas de alta, listado, detalle, cierre y recursos/rutas: tests/artifacts/m10-*.png, revisadas en 360/768/1024/1440 px sin overflow. No se volvió a reproducir ERR_NO_BUFFER_SPACE en la ejecución definitiva, que conservó la comprobación de consola.

Evidencias privadas ignoradas por Git: tests/artifacts/m10-sql-pass.json, m10-http-pass.json, m10-activation.json, m10-final-state.json y m10-git-status.txt. Los PASS contienen hashes del código; SQL registra las aserciones realmente ejecutadas. La prueba de deadlock exige el error MySQL 1213, no sólo una excepción genérica.

## Reporte A–P

| Punto | Resultado verificable |
| --- | --- |
| A · Resumen | Panel de entregas operativo, recolección/domicilio, parciales, pendientes y logística manual |
| B · Entorno | Windows, PHP 8.2.35 Docker, MySQL 8.4.10 local y PDO nativo; desarrollo/pruebas separados |
| C · Modelo | Cuatro migraciones, nueve tablas, 61 historiales, 23 FKs RESTRICT y 20 índices únicos |
| D · Entregas | Crear, editar pendiente, preparar, asignar, despachar y confirmar cantidades parciales |
| E · Inventario | Consumo del apartado y egreso M05 atómicos; recepción sin segundo decremento |
| F · Integraciones | Versión/partida M08 estable; consumos locales y recibidos M09; tránsito no entregable |
| G · Concurrencia | Procesos/PDO independientes, un ganador, reenvíos idempotentes, timeout, deadlock 1213 y rollback de auditoría/multilínea |
| H · Seguridad | Once permisos por sucursal, sesión, CSRF, validación del servidor y auditoría transaccional |
| I · Panel | /admin/deliveries, create, detail, action y resources; Chrome autenticado y cuatro anchos PASS |
| J · MySQL | SQL/limpieza/restauración/activación PASS; 63 tablas previas preservadas y cero objetos aislados |
| K · QA | 1168 comprobaciones; PHP/JS/build/diff PASS; regresiones SQL/HTTP M08–M09 y navegador público PASS |
| L · Transversal | Fixtures M03/M04/M07, inicial M05 real, cotización/aceptación M08, cobertura M09 y cumplimiento M10 consistentes |
| M · Archivos | 27 nuevos y 23 modificados; inventario exacto debajo |
| N · Git | main; sin staging, commit, push, ramas nuevas ni alteración de historial |
| O · Límites | Datos reales pendientes; lotes/series bloqueados; fotos/firmas diferidas a medios M11; sin procedimiento inventado de retornos/pérdidas; cPanel/dispositivos reales no ejecutados |
| P · Continuidad | Siguiente módulo oficial M11, sin implementarlo |

## Inventario final de archivos

Nuevos:

- app/Application/Repositories/DeliveryRepository.php
- app/Application/Services/DeliveryManagementService.php
- app/Controllers/Admin/DeliveriesController.php
- app/Domain/Deliveries/DeliveryRules.php
- app/Infrastructure/Persistence/PdoDeliveryRepository.php
- app/Views/admin/deliveries/create.php
- app/Views/admin/deliveries/detail.php
- app/Views/admin/deliveries/index.php
- app/Views/admin/deliveries/resources.php
- bin/activate-m10-development.php
- bin/recover-m10-isolated.php
- database/migrations/20261103100000_create_delivery_resources.php
- database/migrations/20261103100100_create_deliveries.php
- database/migrations/20261103100200_create_delivery_events.php
- database/migrations/20261103100300_register_delivery_permissions.php
- docs/integration-m10.md
- resources/js/modules/deliveries.js
- tests/m10-admin-browser.mjs
- tests/m10-concurrency.php
- tests/m10-deliveries.php
- tests/m10-http-integration.mjs
- tests/m10-restore-backup.php
- tests/m10-support.php
- tests/m10-transfer-integration.php
- tests/m10-worker.php
- tests/mysql-m10-http-integration.php
- tests/mysql-m10-integration.php

Modificados:

- .env.example
- README.md
- app/Application/Repositories/QuoteRepository.php
- app/Application/Repositories/TransferRepository.php
- app/Application/Security/PermissionCatalog.php
- app/Application/Services/InventoryManagementService.php
- app/Application/Services/QuoteReservationService.php
- app/Controllers/Admin/AdminController.php
- app/Core/AppKernel.php
- app/Core/Persistence/IntegrationDatabaseEnvironment.php
- app/Infrastructure/Persistence/PdoQuoteRepository.php
- app/Infrastructure/Persistence/PdoTransferRepository.php
- app/Views/components/admin/sidebar.php
- config/routes.php
- docs/architecture.md
- docs/data-model.md
- docs/modules.md
- docs/qa.md
- docs/roadmap.md
- package.json
- public/assets/js/app.js
- resources/js/app.js
- tests/run.php

El .env privado también recibió exclusivamente la configuración de la identidad M10 autorizada; no se incluye en el inventario de Git ni se publican sus secretos. Los respaldos y artefactos de prueba permanecen privados e ignorados.


## Continuidad M11 · 4 de noviembre de 2026

Los pendientes de medios y contenido relacionados con este módulo se atienden en [integración M11](integration-m11.md). Ese informe conserva las decisiones autorizadas, los límites de formatos, la recepción pública con consentimiento y los resultados reales; los estados anteriores de este documento permanecen como historia del incremento original.

## Integración posterior con devoluciones

M10 continúa siendo Entregas. La funcionalidad independiente de devoluciones
usa exclusivamente `delivery_items.received_quantity` como evidencia física y
no una cotización aceptada ni mercancía solamente despachada. Véase
[integración de devoluciones](integration-returns.md).
