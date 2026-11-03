# Integración M08 · Cotizaciones comerciales

Implementación del 3 de noviembre de 2026. El propietario confirmó el entorno vigente: Windows, PHP 8.2.35 y MySQL 8.4.10 en Docker. El antiguo minilab MariaDB 192.168.1.12 no es el destino de esta entrega. No se declara una ejecución MariaDB 10.11 en esta máquina.

## Funcionalidades operativas

Listado paginado por sucursal, búsqueda por folio/cliente, filtros y estados vacíos. Alta con cliente activo M07 de la sucursal emisora, lista M04, vigencia obligatoria y condiciones capturadas por el vendedor. Búsqueda de variantes activas por producto, SKU o código de barras; hasta cien partidas por versión, sin duplicar una variante.

Guardar actualiza explícitamente precios e impuestos desde M04. Se conserva la captura ante una validación fallida. Emitir cierra la versión; no modifica inventario ni envía mensajes. El código interno sent tiene la etiqueta comercial «Emitida»: prepara el documento para compartir y no demuestra transmisión.

Se pueden generar hasta cien versiones conservando las emitidas, autorizar descuentos superiores a la política, registrar aceptación/rechazo/vencimiento/cancelación y consultar el historial. Rechazar y cancelar requieren motivo. El HTML es imprimible y cada versión emitida tiene una descarga PDF real generada en PHP, sin Node en producción.

## Modelo y migraciones

Las 48 migraciones M00–M07 se conservaron intactas. Cinco adiciones elevan el total a 53:

| Migración | Tablas / efecto |
| --- | --- |
| 20261103080000_create_quotes | quotes, quote_contacts |
| 20261103080100_create_quote_versions | quote_versions, quote_items |
| 20261103080200_create_quote_traceability | quote_status_history, quote_assignments, quote_approvals |
| 20261103080300_create_inventory_reservations | inventory_reservations, inventory_reservation_items |
| 20261103080400_register_quote_permissions | Nueve permisos y asignación exclusiva a GLOBAL_ADMIN |

Todas usan InnoDB/utf8mb4, FKs RESTRICT, índices, unicidad y DECIMAL. Los rollbacks se niegan a retirar tablas con historia o permisos asignados a roles configurables. No se modifica el CHECK ni el libro de movimientos M05.

El encabezado conserva sucursal, FK del cliente, responsable, folio, versión actual, clave idempotente, hash y lock_version. Cada versión guarda lista, vigencia, MXN, condiciones, entrega acordada, notas, totales e instantáneas de cliente/sucursal/vendedor. Las partidas conservan FK variante/unidad, orden, SKU, descripción, cantidad, precio, descuento, base, tasa, tratamiento fiscal y origen M04. El contacto inicial queda separado de posteriores cambios M07.

## Folios, concurrencia e idempotencia

El servidor genera COT- más 96 bits aleatorios; un índice único protege la identidad documental. No se promete una secuencia sin huecos. Encabezado y versión se bloquean dentro de una transacción; lock_version rechaza ediciones perdidas. Guardados y transiciones tienen claves únicas y hashes: un reenvío equivalente devuelve la operación; una clave con otros datos se rechaza.

Partidas, estado, historial y auditoría confirman o revierten juntos. Los timeouts/deadlocks no se reintentan automáticamente. Las pruebas usan procesos PHP y PDO independientes para emisión simultánea con exactamente un ganador, creación concurrente con folios distintos y timeout sin emisión parcial.

## Integración comercial

M03 aporta variantes, SKU, códigos y unidades. No hay partidas libres. Se valida precisión de unidad y cantidad máxima sin float; los ceros finales de una cantidad normalizada no impiden editarla.

M04 conserva el único motor. CommercialCalculator::calculateDiscounted reutiliza el cálculo fiscal original, cadenas decimales y half-up; los importes de partida se acumulan a dos decimales. Se respetan sucursal, lista, excepción/regla general, vigencia y tasa activa. No hay IVA precargado. Precio ausente permanece NULL y bloquea emisión; cero es válido. El importe final de entrega se captura explícitamente según lo acordado y se suma; no se administra logística ni se inventa un tratamiento fiscal nuevo para ese concepto.

Un descuento adicional exige política M04 activa. Superar su límite pone el borrador pendiente de autorización. quotes.approve conserva responsable, motivo y hash de contenido; cambiar importes invalida esa autorización. El navegador no impone precios ni totales.

M07 se consulta dentro de la sucursal autorizada. Listado y detalle muestran datos históricos. Antes de emitir se verifica que precio, impuesto, cliente, unidad y sucursal sigan vigentes; los cambios requieren guardar y actualizar explícitamente el borrador.

M05 aporta físico, apartado y disponible. Se advierte cuando no existe inicial o la cantidad supera disponibilidad. Guardar y emitir no alteran saldos ni precios. Aceptar comprueba disponibilidad bajo bloqueos y crea el apartado atómicamente con estado/historial/auditoría. Una aceptación multilínea insuficiente no deja reservas parciales. Cancelar una aceptada libera lo pendiente sin modificar físico, incluso si una variante fue desactivada. Las tablas de reservas conservan cantidades y saldos antes/después; no se disfrazan reservas como ajustes físicos.

## Estados, vigencia e historial

El alta administrativa inicia en preparing; puede requerir pending_approval y emite como sent. Desde emitida se admite accepted, rejected, expired o cancelled. Una aceptada solo puede cancelarse dentro de M08. No se permite aceptar vencidas ni marcar vencida una todavía vigente. Fechas comerciales: America/Hermosillo. Sellos técnicos de emisión/transición: UTC, convertidos al presentar.

El esquema conserva received/assigned y una toma atómica, pero esta entrega entra por alta administrativa: el creador queda asignado. Editar no reasigna silenciosamente al vendedor. M10 no está implementado.

## Seguridad y rutas

Nueve permisos por sucursal: quotes.view, create, edit, issue, assign, approve, accept, status e history. La migración solo los asigna a GLOBAL_ADMIN. Toda escritura exige sesión M02, permiso, alcance, estado, CSRF y validación del servidor. Lecturas, búsqueda y PDF también comprueban alcance. La auditoría recibe acciones/identificadores, sin secretos ni copia de contactos.

| Método | Ruta | Operación |
| --- | --- | --- |
| GET | /admin/quotes | Listado |
| GET | /admin/quotes/create | Alta |
| GET | /admin/quotes/edit | Editor |
| POST | /admin/quotes/save | Guardado y actualización de precios |
| GET | /admin/quotes/detail | Documento, versiones e historial |
| POST | /admin/quotes/action | Emisión, transiciones y autorización |
| GET | /admin/quotes/lookup | Variantes, precio y disponibilidad |
| GET | /admin/quotes/pdf | PDF de versión emitida |

El PDF usa fuentes estándar PDF y Windows-1252 para español; otros caracteres se transliteran. Las instantáneas UTF-8 originales se conservan en SQL y HTML. La identidad es tipográfica provisional y solo muestra datos configurados de sucursal.

## Credenciales y aislamiento local

La identidad dedicada ayala_m08_test en mysql-local está limitada a ferreteria_ayala_test. El aprovisionamiento administrativo usó Docker; root nunca es ejecutor. El secreto aleatorio solo existe en .env ignorado por Git. TEST_DB_*_M08 y M08_TEST_AUTHORIZATION no heredan otra cuenta.

Privilegios exactos: SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, TRIGGER, EVENT y EXECUTE. MySQL 8.4 exige además REFERENCES para crear FKs; el preflight lo exige solo en ese motor. No hay GRANT OPTION ni permisos administrativos globales.

En NTFS el modo Linux del bind mount no representa la ACL. Se retiró la herencia de .env y se limitó al usuario propietario, SYSTEM y Administradores. Docker conserva su lectura. No se afirma un chmod 600 efectivo en NTFS.

El preflight comprueba identidad, engine, autorización, privilegios, base distinta de desarrollo e inventario vacío. No usa DROP DATABASE, TRUNCATE ni desactiva FKs. Ante un fallo preserva el estado. Se recuperaron únicamente grafos sintéticos identificados, respaldados y comparados contra inventario/historial/conteos exactos antes del rollback oficial.

## Ejecutores y evidencias

En esta máquina:

    docker exec ferreteria-ayala-php-1 php tests/run.php
    docker exec ferreteria-ayala-php-1 php tests/mysql-m08-integration.php
    npm run test:mysql:m08:http
    docker compose --env-file .env.example run --rm assets npm run build

El ejecutor HTTP usa Chrome Windows y un servidor PHP Docker temporal contra la base aislada, secuencialmente. Las credenciales sintéticas viajan por un pipe privado; no se imprimen ni pasan como argumentos. Evidencias y capturas: tests/artifacts, ignorado por Git.

El activador privado bin/activate-m08-development.php --apply exige APP_ENV=development, 48 historiales previos exactos, cinco adiciones y evidencias SQL/HTTP recientes que coincidan con los hashes del código. Antes de migrar escribe una copia privada de DDL/filas/historial, decodifica y compara el respaldo y verifica su SHA-256. Después comprueba conservación de todas las tablas previas salvo los registros técnicos de permisos/historial, nueve permisos/asignaciones y cero cotizaciones. El DDL conserva sus commits implícitos: un fallo exige inspección.

## Resultados

- Regresión PHP: 1052 comprobaciones PASS, incluidas M00–M07.
- Sintaxis PHP, JavaScript y build Docker: PASS.
- SQL definitivo: PASS con 53 migraciones, límites M03, snapshots, autorizaciones, versiones, concurrencia, timeout y rollback multilínea; base aislada vacía.
- Chrome autenticado: PASS en alta, búsqueda, partidas, cálculo, validación con captura conservada, emisión, PDF, aceptación, cancelación, CSRF, permisos y sucursal ajena.
- 360/768/1024/1440 px sin overflow; limpieza HTTP final PASS.
- PDF rasterizado con Poppler e inspeccionado visualmente.
- Activación: PASS en MySQL 8.4.10. Lote 2, 53 migraciones, nueve tablas nuevas vacías, 24 FKs restrictivas y 19 índices únicos (incluidos PRIMARY). Nueve permisos asignados a GLOBAL_ADMIN. Las 50 tablas previas conservaron sus filas; cero objetos de pruebas al finalizar.

## Fronteras explícitas

La solicitud pública sigue siendo demo local: no se recoge información sin aviso de privacidad aprobado/versionado. La entrada operativa implementada es administrativa. SMTP, mensajería automática y notificaciones están excluidos por la restricción expresa del encargo. Tampoco hay M09, M10, pagos, ventas, CFDI, compras o portal de clientes. La expiración se opera manualmente con validación; no se agrega un proceso residente.

El catálogo oficial amplio M08 incluye solicitud pública y correo: esos puntos no se declaran implementados en este incremento. Quedan sujetos a privacidad aprobada y canal de envío autorizado. Continúan pendientes los datos reales del negocio. No se insertan productos, precios, tasas, existencias, clientes ni cotizaciones ficticias en desarrollo.

## Archivos y control de Git

Nuevos: dominio Quotes, QuoteRepository/PdoQuoteRepository, QuoteManagementService, QuoteReservationService, QuotesController, QuotePdf, tres vistas administrativas, cinco migraciones, módulo JS de búsqueda, activador privado y suites de dominio/SQL/HTTP/concurrencia.

Integraciones modificadas: AppKernel, PermissionCatalog, AdminController/sidebar, routes.php, IntegrationDatabaseEnvironment, CommercialCalculator M04, app.js, CSS y assets compilados, package.json, .env.example y tests/run.php. No se alteraron migraciones M00–M07. README y documentación de módulos/modelo/arquitectura/roadmap/QA incluyen el incremento.

Todo queda local y sin staging. No se ejecuta add, commit, push, reset, restore, clean, checkout, merge ni rebase. El siguiente módulo oficial es M09; no se implementa en esta entrega.

## Respaldo y cierre de activación

Respaldo privado: storage/backups/m08-pre-migration-20261103-203309.json. JSON con DDL, filas e historial, decodificado y comparado íntegramente antes de migrar. SHA-256: 721cac1ed70bbbc63d765def969294ce004a85b1588f1fddcc5356e1fffce3e5. El directorio también conserva ACL NTFS privada.

La verificación del servidor real devolvió Home 200, login 200, cotizaciones/alta/PDF sin sesión 303 y /.env 404. El migrador oficial enumera las cinco nuevas migraciones en lote 2. La base de desarrollo tiene un administrador activo y no contiene sucursales, clientes, variantes, precios ni existencias: deben cargarse datos reales; no se migraron datos del antiguo minilab.

Validación final: npm test PASS (1052), 291 archivos PHP válidos, 16 archivos JavaScript válidos, npm run build PASS y git diff --check PASS. PDF A4 1.4 sin JavaScript, rasterizado y revisado visualmente. Chrome y SQL definitivos PASS; ambas bases de operación y pruebas se mantuvieron separadas.

### Reporte A–P

| Punto | Resultado |
| --- | --- |
| A · Operación | Alta, editor, cálculo, emisión, aceptación/cancelación y consulta administrativa disponibles |
| B · Modelo | Nueve tablas, cinco migraciones aditivas; 53 acumuladas |
| C · Integración | Variantes/unidades M03, motor M04, saldos M05 y clientes M07 |
| D · Documento | Folio único, partidas ordenadas, borradores y estados controlados |
| E · Cálculos | Decimal por cadenas, half-up, MXN, impuesto incluido/excluido y descuentos autorizados |
| F · Historia | Versiones emitidas conservadas, snapshots y trazabilidad por actor |
| G · Concurrencia | Locks, lock_version, claves/hash idempotentes y rollback transaccional |
| H · Seguridad | Nueve permisos por sucursal, sesión, CSRF, alcance y auditoría |
| I · Panel | Listado, alta, edición, búsqueda, detalle y operaciones en /admin/quotes |
| J · Comercial | HTML imprimible y PDF real por versión emitida |
| K · SQL | MySQL Docker local confirmado, test vacío y activación verificada |
| L · QA | 1052 comprobaciones, PHP/JS/build/diff, SQL y Chrome en cuatro anchos |
| M · Archivos | 26 nuevos y 20 modificados; inventario exacto en tests/artifacts/m08-git-status.txt |
| N · Git | Sin staging, commit, push ni cambios de historial |
| O · Pendientes | Datos reales, privacidad para solicitud pública y canal SMTP autorizado; PDF fuera de Windows-1252 se translitera |
| P · Continuidad | Siguiente módulo oficial M09, sin comenzar |
