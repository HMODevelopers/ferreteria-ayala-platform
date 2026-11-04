# Integración M09 · Transferencias entre sucursales

Implementación del 3 de noviembre de 2026. Destino comprobado: Windows, repositorio E:/PROYECTOS DEV/ferreteria-ayala-platform, rama main, PHP 8.2 Docker y MySQL 8.4.10 en mysql-local. Desarrollo: ferreteria_ayala; integración: ferreteria_ayala_test. No se utilizó el servidor histórico MariaDB.

## Modelo y flujo

Cuatro migraciones aditivas conservan las 53 anteriores y elevan el historial a 57. Crean transfers, transfer_items, transfer_events y transfer_event_items; registran diez permisos transfers.* exclusivamente para GLOBAL_ADMIN. InnoDB, utf8mb4, FKs RESTRICT, unicidad de folio/variante/operación/movimiento y cantidades DECIMAL(18,6). Los rollbacks rechazan historia y permisos delegados.

Estados oficiales: solicitada, aprobada, preparada, en tránsito, recibida y cancelada. La recepción parcial sigue en tránsito. La solicitud no altera saldos. Se pueden editar sus cantidades antes de aprobar. Aprobar puede reducir las cantidades solicitadas y aparta exactamente las aprobadas en origen. Preparar no cambia físico. Despachar consume el apartado propio y registra salida física; recibir registra exclusivamente lo realmente recibido. No se recibe sin salida previa ni por encima de lo despachado. El cierre exige recibir toda la cantidad aprobada.

Las cantidades se capturan por unidad autorizada M03 y se convierten exactamente a la unidad base, que conserva el documento. Se rechaza precisión excedida, conversión ausente o no representable, variantes duplicadas, sucursales iguales y referencias inexistentes. El folio TRA- usa 96 bits aleatorios y unicidad SQL.

## Inventario y apartados

TransferManagementService compone InventoryManagementService en una transacción exterior por evento. M05 conserva el único saldo y libro. Se usan adjustment_out/adjustment_in con source_type transfer_event, source_id evento:partida y FK única al movimiento; no se cambia el CHECK ni los movimientos históricos M05.

Disponible = físico - apartado. Al autorizar se bloquea el saldo y se verifica disponibilidad, incluidos los apartados M08. Al despachar sólo se libera el apartado propio antes de la salida atómica. El stock de destino no aumenta hasta recibir. En tránsito = despachado - recibido; por despachar = aprobado - despachado. No se suma existencia de unidades distintas.

La cancelación sólo es posible sin despachos. Libera el apartado propio; no borra registros. Con una cotización aceptada comprometida exige cancelar primero esa cotización. Una incidencia registra actor, motivo y fecha sin alterar cantidades ni inventar pérdidas. El pendiente permanece abierto hasta recibirlo. No existe una política aprobada de merma/devolución de traslado, por lo que no se fabrica una resolución física automática.

## Cotizaciones relacionadas

El requisito de completar cotizaciones aceptadas exige un cambio mínimo y explícito en M08: una aceptación puede cubrir faltantes mediante transferencias ya aprobadas. Se permite solicitar desde la versión emitida actual para obtener esa cobertura antes de aceptar; también se admiten referencias aceptadas con faltante real. No se modifica la versión comercial ni sus precios, cliente o cantidades.

La aprobación se limita a las variantes y cantidades sin cobertura de la versión. M08 aparta en destino la parte local y reconoce como cobertura pendiente únicamente cantidades aprobadas todavía no recibidas. Recibir antes de aceptar deja la mercancía disponible para la aceptación normal. Recibir después de aceptar incrementa físico y apartado en destino en la misma transacción. Cancelar M08 libera tanto su apartado local como la asignación recibida M09 mediante las filas trazables de transferencia. Una recepción posterior de una cotización cancelada queda disponible, sin reactivar apartados.

QuoteReservationService acepta un contrato TransferRepository opcional. AppKernel lo conecta sólo si la migración M09 está instalada, conservando compatibilidad con las suites históricas acotadas a M08.

## Seguridad y concurrencia

Solicitud/edición/cancelación pertenecen al destino; aprobación/preparación/despacho e incidencias al origen; recepción al destino. Lectura e historial admiten el alcance autorizado de cualquiera de ambas sucursales. Los permisos son configurables y no se delegan automáticamente a roles de sucursal. Todas las escrituras HTTP exigen sesión, permiso específico, sucursal y CSRF en servidor; la interfaz no decide reglas de inventario. La auditoría comparte conexión/transacción con cada evento.

Se bloquea primero la cotización relacionada cuando existe, luego el encabezado, partidas y sucursales en orden ascendente; M05 bloquea variante/saldo. Las partidas se procesan por variante. lock_version evita cambios perdidos. Clave única y hash de petición distinguen reenvío equivalente de clave reutilizada con otros datos. La creación conserva las cantidades originales por partida; las ediciones registran sus nuevas cantidades sin borrar esa instantánea inicial. Los eventos físicos enlazan sus movimientos, actor y hora UTC. No se mantienen transacciones durante el viaje. Deadlocks y timeouts revierten el evento; no hay reintentos automáticos.

## Panel

/admin/transfers ofrece búsqueda por folio/sucursal, filtro de estado/sucursal, paginación y acceso al detalle. /create permite seleccionar origen/destino, cotización emitida por folio, buscar SKU/producto, ver disponibilidad y capturar cantidades/unidades. /detail separa aprobación, preparación, despacho, recepción, cancelación e incidencias. Las operaciones físicas requieren confirmación explícita; JS evita doble envío, mantiene las cantidades al buscar y escribe resultados con textContent. El servidor valida nuevamente cada operación.

## Aislamiento, respaldo y activación

Cuenta M09 exclusiva limitada al esquema de pruebas; globalmente sólo USAGE, sin GRANT OPTION. MySQL 8.4 exige REFERENCES adicional para FKs. La suite comprueba identidad efectiva, motor, permisos exactos, autorización, inventario vacío y lock asesor. No usa root para ejecutar pruebas, no crea bases ni usa TRUNCATE. Si falla conserva el estado. Sólo limpia el esquema que creó después de completar las verificaciones.

El activador privado exige desarrollo local confirmado, historial previo exacto, cuatro adiciones y evidencias SQL/HTTP recientes con hashes del código. Escribe respaldo privado de DDL/filas/historial, comprueba decodificación y SHA-256 y lo restaura íntegramente en la base aislada vacía. Compara filas y retira exclusivamente esa restauración antes de migrar desarrollo. Revalida tablas previas, historial, permisos y ausencia de transferencias ficticias. Los commits implícitos de DDL requieren inspección si falla una migración.

## Resultados

- npm test: PASS, 1105 comprobaciones acumuladas M00–M09, con PHP Docker mediante adaptador temporal de PATH en Windows.
- Sintaxis PHP: PASS, 313 archivos. Fuentes JavaScript validadas y npm run build Docker: PASS.
- SQL definitivo: PASS con 57 migraciones. Incluye solicitud idempotente, unidades/conversión exacta y no representable, edición/versiones, protección de lotes, parciales 20→15→10→7+3→5, conservación de red, claves reutilizadas, sobre-recepción, cancelación con entidades desactivadas, permisos de origen/destino y rollback por fallo de auditoría.
- Conexiones independientes: un ganador al despachar/recibir la misma transferencia; competencia de dos transferencias por disponibilidad; timeout con rollback; aceptación M08 frente a aprobación M09 por el mismo saldo, con un ganador. El cierre definitivo añade rutas opuestas para verificar la adquisición estable de sucursales.
- M08 relacionado: aceptación insuficiente rechazada sin cobertura; aceptación con cobertura aprobada, apartado local exacto, recepción apartada, cancelación y recepción posterior sin reapartar. La base aislada terminó sin objetos.
- HTTP/Chrome definitivo: PASS con solicitud, aprobación, preparación, despacho, recepción parcial, rechazo de sobre-recepción, cierre, CSRF, permisos y alcance. Alta/listado/detalle revisados en 360/768/1024/1440 px, sin overflow. Capturas inspeccionadas visualmente; base aislada vacía al terminar.
- Regresión pública: PASS en cinco tamaños y login en tres, sin errores de consola.
- Regresión SQL/HTTP M08: PASS, con sus 53 migraciones históricas, emisión/PDF/aceptación/cancelación, concurrencia y cuatro tamaños. Ambas ejecuciones dejaron la base aislada vacía.
- Activación: PASS. MySQL 8.4.10, 57 migraciones, cuatro nuevas y diez permisos/asignaciones GLOBAL_ADMIN. Las 59 tablas previas conservaron sus filas; las cuatro tablas M09 están vacías.
- Respaldo: storage/backups/m09-pre-migration-20261103-224550.json. SHA-256: 85c99011c9299f3145023279ddd631d1ba72440eb9d27787a31559eb3d13b217. Decodificación, hash, restauración independiente y comparación íntegra de filas: PASS. La base aislada quedó sin objetos después de la restauración.
- Rutas del servidor real: Home/login 200; transferencias/alta/detalle sin sesión 303; /.env 404. Sin fixtures en desarrollo.
- Git: main, sin staging, commit, push ni cambios de historial.

Una ejecución inicial SQL detectó precisión normalizada indebidamente rechazada; los fixtures propios se inventariaron, respaldaron y limpiaron antes de repetir. Las primeras ejecuciones Chrome detectaron el mapa HTTP de partidas y una aserción sensible a mayúsculas; se corrigieron y se recuperó exclusivamente la base sintética antes de repetir. Nunca se limpió desarrollo ni se reutilizó el servidor antiguo. La primera restauración del respaldo detectó la columna generada M02 user_roles.branch_scope_key; se omitió de los INSERT y se dejó a MySQL calcularla. La copia parcial se validó por DDL equivalente y por pertenencia de cada fila al respaldo, se completó, se compararon todas las filas y se retiró únicamente esa copia aislada. Desarrollo permaneció en 53 migraciones durante esa recuperación.

## Límites reales

M05 tiene tablas opcionales de lotes/series, pero carece de un motor operativo para trasladar esas identidades. M09 rechaza variantes con identificaciones registradas; no las duplica ni descarta. Completar traslados por lote/serie exige ampliar ese contrato de M05. No se declaran ejecutados cPanel/Apache/FTP reales, MariaDB remoto, lectores de pantalla ni dispositivos físicos.

No hay M10, logística, compras, pagos, ventas, notificaciones ni SMTP. Desarrollo necesita los catálogos y existencias reales del negocio; no se insertan fixtures allí. Todo queda sin staging, commit ni push. La continuidad oficial es M10, sin implementarlo.

## Reporte A–O

| Punto | Cierre |
| --- | --- |
| A · Resumen | Transferencias administrativas operativas con eventos físicos separados |
| B · Entorno | Windows, PHP 8.2.35 Docker, MySQL 8.4.10 local; desarrollo y pruebas separados |
| C · Modelo SQL | Cuatro migraciones aditivas y cuatro tablas; 57 acumuladas |
| D · Flujo | Solicitada → aprobada/apartada → preparada → en tránsito → recibida; cancelación sólo sin salida |
| E · Inventario | M05 conserva saldo/libro; M08 conserva sus apartados y reconoce cobertura aprobada |
| F · Concurrencia | Locks por cotización/documento/sucursales ordenadas, versión, clave/hash; carreras reales y timeout PASS |
| G · Seguridad | Diez permisos configurables, origen/destino independientes, sesión, CSRF y auditoría atómica |
| H · Panel | /admin/transfers, create, detail, products y action |
| I · Integración | SQL y Chrome autenticado PASS, cuatro anchos y base aislada vacía |
| J · Desarrollo | 57 migraciones, respaldo restaurado, 59 tablas previas preservadas, cero transferencias ficticias |
| K · QA | 1105 comprobaciones PHP, 313 archivos PHP, 18 JS, build/diff y regresiones M08/públicas PASS |
| L · Archivos | 27 nuevos y 22 modificados; inventario debajo |
| M · Git | main, sin staging, commit, push ni alteraciones de historial |
| N · Límites | Faltan catálogos reales; variantes con lotes/series bloqueadas hasta un motor M05 trazable; sin política de pérdidas automáticas |
| O · Continuidad | M10 según documentación, sin implementarlo |

## Inventario de archivos

- .env.example
- README.md
- app/Application/Security/PermissionCatalog.php
- app/Application/Services/InventoryManagementService.php
- app/Application/Services/QuoteReservationService.php
- app/Controllers/Admin/AdminController.php
- app/Core/AppKernel.php
- app/Core/Persistence/IntegrationDatabaseEnvironment.php
- app/Core/Request.php
- app/Views/components/admin/sidebar.php
- config/routes.php
- docs/architecture.md
- docs/data-model.md
- docs/modules.md
- docs/qa.md
- docs/roadmap.md
- package.json
- public/assets/css/app.css
- public/assets/js/app.js
- resources/css/app.css
- resources/js/app.js
- tests/run.php
- app/Application/Repositories/TransferRepository.php
- app/Application/Services/TransferManagementService.php
- app/Controllers/Admin/TransfersController.php
- app/Domain/Transfers/TransferRules.php
- app/Infrastructure/Persistence/PdoTransferRepository.php
- app/Views/admin/transfers/create.php
- app/Views/admin/transfers/detail.php
- app/Views/admin/transfers/index.php
- bin/activate-m09-development.php
- bin/inspect-m09-environment.php
- bin/recover-m09-restore.php
- database/migrations/20261103090000_create_transfers.php
- database/migrations/20261103090100_create_transfer_items.php
- database/migrations/20261103090200_create_transfer_events.php
- database/migrations/20261103090300_register_transfer_permissions.php
- docs/integration-m09.md
- resources/js/modules/transfers.js
- tests/m09-admin-browser.mjs
- tests/m09-concurrency.php
- tests/m09-http-integration.mjs
- tests/m09-quote-integration.php
- tests/m09-restore-backup.php
- tests/m09-support.php
- tests/m09-transfers.php
- tests/m09-worker.php
- tests/mysql-m09-http-integration.php
- tests/mysql-m09-integration.php
