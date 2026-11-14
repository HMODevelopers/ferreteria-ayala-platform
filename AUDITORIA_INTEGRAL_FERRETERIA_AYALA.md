# AUDITORÍA INTEGRAL DEL SISTEMA — FERRETERÍA AYALA

Fecha de revisión: **13 de noviembre de 2026**, referencia local America/Phoenix. Repositorio: ferreteria-ayala-platform, rama indicada por .git/HEAD: main; commit de esa referencia: `fc709ed606f9ab0019dfef0b8932d0af7af97fd1`. No se pudo ejecutar git status para certificar limpieza del working tree. Actividad exclusivamente de auditoría. Único entregable creado: este Markdown.

## A. Resumen ejecutivo

**Dictamen: NO está listo para operar en producción con el alcance completo aprobado.** Hay una implementación empresarial sustancial en PHP, con controles de acceso, documentos versionados, inventario, entradas, clientes, transferencias, entregas, CMS, devoluciones, reportes y un núcleo durable de trabajos. No es solamente una maqueta. Sin embargo, hay brechas funcionales comprobables, inconsistencias de integración y límites de verificación que impiden declararlo terminado o estable en operación real.

La causa inmediata observada en el entorno local es que **la recepción pública no está disponible**: la página de solicitud responde sin formulario, indica que falta publicar privacidad y habilitar una sucursal, el aviso devuelve 404 y la Home muestra catálogo y sucursales en preparación. La aplicación se protege al no aceptar solicitudes sin estas precondiciones; esto es una falta de preparación del entorno, no evidencia de que el servicio de recepción pierda solicitudes.

El código permite reconstruir una cadena de recepción, asignación, preparación de precios, verificación, emisión PDF, registro manual de entrega, aceptación con apartado y entrega física. No se confirmó su ejecución integral actual. Hay una incompatibilidad entre la exigencia de stock local para emitir en seguimiento y el requisito de versión ya emitida para crear un traslado vinculado; los reportes no representan todas las solicitudes tempranas y pueden medir mal la primera atención. La importación de altas CSV no completa la importación Excel con actualización por SKU aprobada. M12 solo emite incidencias de entrega. Conteos documentales y trazabilidad por lotes/series no tienen operación completa.

**No se demostró ningún P0 de pérdida de datos, exposición de secretos o corrupción efectiva.** Eso no certifica su ausencia. Los P1 encontrados y la falta de pruebas actuales son suficientes para impedir el cierre de producción. No se atribuyen porcentajes arbitrarios de avance.

### Método, evidencia y límites

- **O — observado:** respuestas HTTP actuales o ejecución aislada de JavaScript en memoria.
- **C — demostrado por código:** condiciones, consultas, permisos o ausencia de consumidores localizados en el repositorio. No equivale a probar su ejecución SQL.
- **R — riesgo/inferencia:** escenario derivado del código cuya manifestación real necesita datos, concurrencia o sesión autenticada.
- **H — antecedente histórico:** resultado descrito en documentos o artefactos anteriores; no se cuenta como prueba aprobada en esta auditoría.
- **NV — no verificable en esta sesión:** esquema y datos vivos, integración autenticada, rendering responsive y hosting real.

Se inventarió la estructura transversal y se cargaron fuentes para búsqueda, cruce de rutas y lectura detallada de servicios y repositorios críticos. Se contaron **490 archivos PHP** dentro del conjunto de fuentes inspeccionado, excluyendo node_modules, .git, storage y artefactos históricos; **74 archivos de migración** y **178 rutas**. La correspondencia estática de las 178 rutas con clases y métodos existe, sin duplicados método/URL. Esto no constituye análisis semántico exhaustivo de cada línea de los 490 PHP.

Se ejecutaron consultas HTTP al servidor ya disponible en 127.0.0.1:8080 y pruebas de JS con dobles en memoria. **No se ejecutaron migraciones, rollback, fixtures, pruebas SQL con escritura, altas comerciales, login, cambios de permisos ni compilación que reescribiera assets.** Los GET de la aplicación pueden crear sesiones técnicas temporales por su propio funcionamiento; no se manipuló información operativa. El .env se consultó únicamente para confirmar presencia de claves, sin imprimir valores. No se guardan secretos, datos personales ni dumps en este informe.

El shell falló con CreateProcessAsUserW / acceso denegado (error 5), el lanzamiento de procesos desde Node con EPERM y el acceso al motor Docker con EPERM. La herramienta de navegador informó host de acceso y pipe nativo no disponibles. Estas restricciones impidieron PHP CLI, lectura SQL actual y capturas. **No son una prueba de fallo del sistema ni un rechazo de aprobación automática.** No se intentó eludirlas mediante endpoints de diagnóstico, scripts públicos o terminal por UI.

## B. Inventario de módulos

Los estados se refieren al alcance completo y a esta revisión. Ningún módulo empresarial recibe «Terminado y verificado» solo por los PASS históricos. Las capacidades observadas de manera acotada se detallan en G.

| Módulo real | Funcionalidades localizadas | Estado | Evidencias principales | Pendientes o límites |
|---|---|---|---|---|
| Núcleo MVC y rutas | Autoload App, router, request/response, componentes y escape | Implementado sin verificación completa | bootstrap/app.php; app/Core; config/routes.php; 178 correspondencias y HTTP anónimo | Lint/ejecución completa, Apache real |
| M00 Persistencia | PDO diferido, transacción, lotes, lock asesor, rollback protegido | Implementado sin verificación completa | Connection, Migrator, MySqlMigrationStore; 74 migraciones | Estado SQL actual y restauración no inspeccionados |
| M01 Sucursales | Altas, edición, estado, contactos/coordenadas; horario regular; servicios/horarios en dominio | Parcialmente implementado | BranchesController, BranchScheduleManagementService, BranchServiceManagementService | Excepciones y servicios sin mantenimiento completo en UI; AY-20/21 |
| M01 Activación modular | Tabla global/por sucursal, regla de disponibilidad, controles de publicación M08/M11 | Parcialmente implementado | ModuleAvailabilityService, CmsManagementService | Regla sin consumidores generales en escrituras; AY-07 |
| M02 Acceso | Usuarios, roles, alcance, sesiones revocables, hashing, CSRF y rate limiting | Implementado sin verificación completa | AuthenticationService, AccessManagementService, PdoAccessRepository | Matriz autenticada actual; recuperación SMTP pendiente; AY-05/06 |
| Auditoría | Eventos privados, consulta paginada, callbacks transaccionales en múltiples módulos | Parcialmente implementado | AuditController/PdoAuditRepository y servicios | Fronteras no atómicas y errores no diagnosticables: AY-10/21/22 |
| M03 Catálogo interno | Categorías, marcas, unidades, variantes, códigos, conversiones, atributos, galerías raster | Implementado sin verificación completa | CatalogManagementService, ProductsController y PdoCatalogRepository | Integración actual, impresión de códigos, importación completa |
| M04 Precios/impuestos | Listas, vigencias, políticas, cálculo Decimal, historial y snapshots | Implementado sin verificación completa | PricingManagementService, CommercialCalculator, PdoPricingRepository | Configuración fiscal real, concurrencia actual y precio público |
| M05 Inventario básico | Saldos físicos/apartados/disponibles, ajustes, movimientos, mínimos | Defectuoso | InventoryManagementService, PdoInventoryRepository, InventoryController | AY-15 afecta mínimos fuera de primeras 500; validación SQL actual |
| M05 Conteos/lotes/series | Tablas, flags de catálogo, permiso counts y rechazos protectores | Parcialmente implementado | Migraciones 20261101050000 y 20261101050400; assertUntracked | Sin operación documental de conteo ni trazabilidad integral; AY-08 |
| M06 Proveedores/entradas | Directorio/contacto, factura, partidas, confirmación y recepción física parcial/idempotente | Implementado sin verificación completa | PurchasingManagementService, PdoPurchasingRepository | Pruebas actuales, alerta comparable de costo AY-23 |
| M07 Clientes | Persona/empresa, contacto/dirección principal, lista, estado, duplicados posibles, lock_version | Implementado sin verificación completa | CustomerManagementService, PdoCustomerRepository | Carga real y verificación de concurrencia/permisos actuales |
| M08 Documentos de cotización | Borradores, precios, descuentos/autorización, versiones, PDF, aceptar/cancelar | Implementado sin verificación completa | QuoteManagementService, QuoteRules, QuotePdf | Integración faltantes AY-04; SMTP AY-12; pruebas completas |
| Seguimiento unificado | Origen web/correo/WhatsApp/teléfono/mostrador, etapas, responsable, consultas, conformidad/envío manual | Defectuoso | QuoteWorkflowService/Repository, QuoteWorkflowsController | AY-04/05/06/16; validar casos legacy y revisiones |
| M08 Recepción pública | Consentimiento, productos activos, throttle, idempotencia, folio y bandeja | Parcialmente implementado | PublicQuoteRequestService y CmsController | Inhabilitada actualmente; lista entre búsquedas incompleta AY-01/03 |
| Apartados | Reserva al aceptar, cobertura M09, consumo M10 y liberación de remanente | Implementado sin verificación completa | QuoteReservationService, inventory_reservation_items | Conciliar saldos y consumos con datos/SQL actuales |
| M09 Transferencias | Solicitar/aprobar/preparar/salir/recibir parcial, incidentes e historial | Implementado sin verificación completa | TransferManagementService/PdoTransferRepository | AY-04; no traslada identificaciones de lotes/series |
| M10 Entregas/logística | Cotización aceptada, parciales, preparación, ruta, vehículo/repartidor, salida, confirmación/incidencia | Implementado sin verificación completa | DeliveryManagementService, DeliveryRules, MediaManagementService | SQL/HTTP actuales, variantes trazadas no soportadas |
| Devoluciones operativas | Cliente desde recepción de entrega; proveedor desde recepción de factura; autorización y movimiento físico | Implementado sin verificación completa | ReturnManagementService/PdoReturnRepository, migraciones 20261110140000/140100 | SQL aislado pendiente en cierre anterior; no compensación financiera ni cuarentena (límites explícitos) |
| M11 CMS y medios | Formularios, estados/programación, raster reencodificado, galería, evidencia, legales y condiciones | Implementado sin verificación completa | CmsManagementService, MediaManagementService, ImageStorage, PdoCmsRepository | Contenido/privacidad real y pruebas actuales |
| Sitio público comercial | Home real desde CMS, búsqueda por nombre/SKU, solicitud y links de ubicación | Parcialmente implementado | CmsHomeData, public/home, componentes y HTTP | Catálogo limitado, filtros sin manejador y mapa público pendiente; AY-02/20/24 |
| M12 Avisos internos | Campana, recientes, contador, lectura, historial y único evento delivery.incident | Parcialmente implementado | PdoNotificationRepository y DeliveryManagementService | Orígenes faltantes AY-11; estado migrado actual no verificado |
| Núcleo de trabajos | Cola durable, leases/tokens, heartbeat, backoff, recuperación e historial, CLI | Implementado sin verificación completa | JobRunner, PdoJobQueueRepository, bin/jobs.php | Solo diagnóstico DB; instalación de cron y MySQL actual NV |
| M13 Reportes/exportación | Cinco reportes paginados, XLSX/PDF y permisos específicos | Defectuoso | PdoReportRepository, ReportsController y escritores | AY-16/17/18/19; SQL con datos reales y permisos actuales |
| M13 Importación | CSV hasta 1000 filas/2 MiB, preview, alta inactiva y transacción | Parcialmente implementado | CatalogImportService y ProductImportsController | AY-09/10; actualización por SKU e historial |
| M13 Códigos de barras | Registro de códigos y búsqueda de variantes en cotización | Parcialmente implementado | CatalogManagementService; PdoQuoteRepository::variants | No se localiza generación/impresión y recorrido de lectura en entradas/inventario/entregas acordado |
| M14 Operación | Guía cPanel, ejemplo cron, scripts de recuperación específicos y núcleo jobs | Parcialmente implementado | docs/deployment-cpanel.md; deploy/cron; bin | AY-13/14/22/25; retención/restore/hosting sin acreditar |
| Pedidos/almacenes independientes | No se localiza módulo independiente de pedido financiero ni pasillo/anaquel | No verificable | requirements.md excluye POS, ventas financieras y ubicaciones internas; entregas y stock usan sucursal | «Pedido» puede significar solicitud/cotización aceptada. No se declara un módulo pendiente sin decisión L |

### Tecnologías y estructura

PHP puro con arquitectura propia MVC, capas Application/Domain/Infrastructure y PDO MySQL; sin Composer ni framework PHP localizado. El requisito es PHP 8.2+ y el servidor HTTP local anuncia PHP/8.2.35, lo que no prueba versión de CLI/hosting. Tailwind y esbuild compilan recursos locales. Swiper y Leaflet se empaquetan en public/assets; fuentes locales Inter y Barlow Condensed con licencias presentes. No se localiza React, Vue, jQuery o DataTables como arquitectura del proyecto.

Document root previsto public/. app, config, bootstrap, database, bin y storage son privados. compose.yaml configura PHP y assets; MySQL y su red son externos. No se requiere Node en producción. XLSX y PDF usan escritores propios. Medios requieren GD con WebP y fileinfo; despliegue documenta mbstring, iconv y límites de subida/memoria. Configuración por getenv con parser local de .env que conserva precedencia de variables exportadas.

## C. Hallazgos técnicos

Clasificación: P0 bloqueante crítico de datos/seguridad/proceso; P1 alta; P2 media; P3 baja. Un riesgo no reproducido conserva explícitamente esa condición. Los hallazgos de preparación no implican automáticamente un defecto de programación.

### AY-01 — El entorno local todavía no recibe solicitudes

- **Prioridad:** P1. **Módulo:** Recepción pública / CMS.
- **Tipo de evidencia:** Observado por HTTP.
- **Evidencia verificable:** GET /solicitar-cotizacion: 200 sin formulario; mensaje de recepción no disponible. GET /legal: 404. GET /: sin sucursales ni productos activos. `app/Views/public/cms/request.php:4`
- **Comportamiento actual:** La página informa que faltan aviso de privacidad y habilitación de sucursal. Esto demuestra falta de preparación funcional local; no permite afirmar que las tablas estén vacías ni identificar por SQL qué banderas faltan.
- **Esperado y fundamento:** requirements.md y aclaración M11: recibir solicitudes reales con consentimiento, sucursal y catálogo autorizado.
- **Impacto operativo:** El objetivo principal no puede iniciarse desde el sitio en este entorno. Es una condición de puesta en operación, no una prueba de fallo del mecanismo de persistencia.
- **Dependencias:** Datos oficiales, aviso aprobado, M08/M11 y sucursal habilitados, catálogo activo.
- **Corrección recomendada (no ejecutada):** Completar configuración y datos reales en una fase posterior autorizada; comprobar el flujo antes de publicar.
- **Validación de cierre:** En staging con datos autorizados: solicitud visible, folio persistido, consentimiento versionado, bandeja correcta y ningún apartado al recibir.

### AY-02 — Catálogo público reducido y filtros publicados sin manejador

- **Prioridad:** P1. **Módulo:** Sitio público / catálogo.
- **Tipo de evidencia:** Demostrado por lectura de código.
- **Evidencia verificable:** `app/Data/CmsHomeData.php:14`; `app/Views/components/public/product-card.php:1`; `resources/js/components/interactions.js:4`; `app/Views/public/home.php:5`
- **Comportamiento actual:** Home consulta seis categorías y seis variantes, no pagina y fija price=null. Las tarjetas publicadas omiten data-product; interactions() retorna sin registrar los eventos de los botones de categoría que la vista sí muestra. La consulta no implementa filtros por atributos ni consulta stock/precio por sucursal.
- **Esperado y fundamento:** requirements.md: catálogo responsive buscable con categorías/atributos, selector de sucursal, disponible exacto y precio público opcional. Una selección destacada de seis productos puede existir, pero no sustituye ese catálogo.
- **Impacto operativo:** Botones que aparentan filtrar no actúan en el modo publicado; parte del catálogo queda inaccesible por navegación y faltan capacidades acordadas.
- **Dependencias:** M03/M04/M05, selección de sucursal y política de visibilidad.
- **Corrección recomendada (no ejecutada):** Conectar el catálogo público completo y sus filtros con consultas paginadas y contexto de sucursal; separar selección destacada de consulta general.
- **Validación de cierre:** Con 40 variantes y varias categorías: localizar cualquiera, filtrar categoría/atributo y cambiar sucursal; valores y permisos coherentes, con y sin JS según alcance.

### AY-03 — La lista solicitada se pierde al cambiar la búsqueda

- **Prioridad:** P1. **Módulo:** Solicitud pública.
- **Tipo de evidencia:** Demostrado por código y texto de la vista.
- **Evidencia verificable:** `app/Controllers/Public/CmsController.php:46`; `app/Views/public/cms/request.php:6`
- **Comportamiento actual:** El GET muestra hasta 25 variantes. No hay paginación ni conservación de cantidades entre consultas; la propia vista indica enviar juntos los materiales de esa consulta. El servicio admite hasta cien, pero la interfaz no permite construir una lista cruzando búsquedas.
- **Esperado y fundamento:** requirements.md: preparar una lista de productos activos y enviarla en una solicitud.
- **Impacto operativo:** Un pedido con materiales de distintos resultados requiere recaptura o solicitudes fragmentadas; riesgo operativo de omisiones.
- **Dependencias:** Catálogo público y recepción M11/M08.
- **Corrección recomendada (no ejecutada):** Conservar selección y cantidades mientras se navega o busca; validar la lista completa al enviar.
- **Validación de cierre:** Elegir dos SKU en búsquedas distintas, volver a cada búsqueda y enviar una sola solicitud con ambas partidas exactas.

### AY-04 — El seguimiento exige stock local antes de permitir el documento que necesita el traslado vinculado

- **Prioridad:** P1. **Módulo:** Cotizaciones / transferencias.
- **Tipo de evidencia:** Inconsistencia deducida de condiciones explícitas; reproducción SQL pendiente.
- **Evidencia verificable:** `app/Application/Services/QuoteWorkflowService.php:50`; `app/Application/Services/QuoteWorkflowService.php:88`; `app/Application/Services/TransferManagementService.php:38`; `app/Application/Services/QuoteReservationService.php:20`
- **Comportamiento actual:** financial(...,true) rechaza cantidad superior al disponible local al verificar y emitir. M09 solo vincula versiones ya emitidas de cotizaciones sent/accepted. El cálculo de cobertura existe al aceptar, pero el flujo unificado no permite llegar a emitir con faltante local. Un traslado genérico no vinculado podría abastecer primero; no se probó.
- **Esperado y fundamento:** requirements.md contempla faltantes completables por transferencias antes de emitir/aceptar y M09 contempla vínculo documental de cobertura.
- **Impacto operativo:** La integración de faltantes queda circular para solicitudes nuevas con seguimiento y requiere un desvío operativo sin vínculo comercial.
- **Dependencias:** Reglas M08/M09, cobertura, verificación e historial.
- **Corrección recomendada (no ejecutada):** Acordar y hacer consistente el punto de autorización del traslado y el criterio de disponibilidad/cobertura; conservar restricciones de apartados.
- **Validación de cierre:** Staging: destino 0, origen 5, solicitud 3; completar flujo documentado con traslado relacionado, sin eludir verificación y sin apartar/descontar dos veces.

### AY-05 — Historial de seguimiento no aplica el permiso dedicado de historial

- **Prioridad:** P1. **Módulo:** Seguridad / seguimiento de cotizaciones.
- **Tipo de evidencia:** Demostrado por comparación de rutas y permisos; explotación autenticada no ejecutada.
- **Evidencia verificable:** `app/Controllers/Admin/QuotesController.php:49`; `app/Controllers/Admin/QuoteWorkflowsController.php:34`; `app/Infrastructure/Persistence/PdoQuoteWorkflowRepository.php:46`
- **Comportamiento actual:** El detalle financiero consulta history solo con quotes.history. context() del seguimiento obtiene events para cualquier lector autorizado no restricted_intake; events() no verifica el permiso. Cargar el seguimiento usa quotes.view o quote_requests.view.
- **Esperado y fundamento:** Catálogo de permisos existente: quotes.history; las restricciones deben ser consistentes entre rutas que muestran historia comercial. Para el historial de recepción sin documento se debe definir el permiso exacto.
- **Impacto operativo:** Un rol que puede leer la cotización pero no su historial podría consultar contactos, notas, canales y responsables desde la ruta de seguimiento.
- **Dependencias:** Roles M02 y definición de historial comercial frente a recepción web.
- **Corrección recomendada (no ejecutada):** Aplicar permiso de historial al backend y a los datos entregados a la vista; acordar explícitamente el caso quote_requests.
- **Validación de cierre:** Dos usuarios de igual sucursal con quotes.view, uno sin quotes.history: comparar detalle y seguimiento; el segundo no recibe eventos restringidos por ninguna ruta.

### AY-06 — La acción take confía parcialmente en las restricciones de la interfaz

- **Prioridad:** P2. **Módulo:** Seguridad / asignación de cotizaciones.
- **Tipo de evidencia:** Riesgo condicionado deducido del código; recurso afectado no localizado en datos reales.
- **Evidencia verificable:** `app/Controllers/Admin/QuoteWorkflowsController.php:33`; `app/Application/Services/QuoteWorkflowService.php:67`
- **Comportamiento actual:** La UI exige quotes.assign para canTake. El servicio exige ese permiso si el seguimiento está asignado a otra persona, pero no lo exige para tomar uno sin responsable o ya propio. En el caso sin solicitud web, canWrite puede concederse con quotes.edit o quotes.create y el POST alcanza take.
- **Esperado y fundamento:** El sistema distingue quotes.assign como permiso de asignación y la interfaz ya lo usa para esa acción.
- **Impacto operativo:** Diferencia entre autorización de UI y backend. El caso con efecto de asignación nueva requiere un documento interno/legacy sin responsable; no se confirmó que exista ni que pueda generarse por el flujo normal, que suele asignar al creador. No se declara escalamiento de privilegios reproducido.
- **Dependencias:** M02, flujos internos y legacy sin asignación.
- **Corrección recomendada (no ejecutada):** Definir toma inicial frente a reasignación y hacer cumplir el contrato aprobado en el servicio, con comprobación por sucursal.
- **Validación de cierre:** Rol con view/edit sin assign; POST take con CSRF válido sobre seguimiento sin responsable debe seguir exactamente la política aprobada.

### AY-07 — La habilitación modular no gobierna uniformemente las operaciones privadas

- **Prioridad:** P1. **Módulo:** Activación de módulos.
- **Tipo de evidencia:** Demostrado por trazado de código; impacto por módulo requiere prueba.
- **Evidencia verificable:** `app/Application/Services/ModuleAvailabilityService.php:70`; `app/Application/Services/QuoteWorkflowService.php:14`; `app/Infrastructure/Persistence/PdoQuoteRepository.php:44`; `app/Controllers/Admin/InventoryController.php:19`
- **Comportamiento actual:** Existe ModuleAvailabilityService, pero no se localizan consumidores en la aplicación. Recepción pública sí consulta M08/M11. Captura privada y ajustes validan permisos/sucursal sin consultar modules/branch_modules. No hay interfaz general de administración de módulos en routes.php; CMS sí configura M08/M11.
- **Esperado y fundamento:** requirements.md: módulos habilitables globalmente y por sucursal sin borrar datos.
- **Impacto operativo:** Desactivar un módulo no garantiza impedir nuevas operaciones privadas; discrepancia entre estado visible/configurado y efectos autorizados.
- **Dependencias:** M01, permisos y definición de lectura histórica durante desactivación.
- **Corrección recomendada (no ejecutada):** Integrar la regla de disponibilidad en cada escritura aplicable y ofrecer la configuración prevista; mantener consulta histórica si se acuerda.
- **Validación de cierre:** Módulo deshabilitado globalmente y luego solo en una sucursal: probar POST directo con usuario autorizado; cero efectos comerciales nuevos donde esté deshabilitado.

### AY-08 — Parte de M05 tiene esquema y permisos, pero no operación completa

- **Prioridad:** P1. **Módulo:** Inventario / lotes / series / conteos.
- **Tipo de evidencia:** Demostrado por esquema y búsqueda de consumidores.
- **Evidencia verificable:** `database/migrations/20261101050000_create_inventory_tracking.php:6`; `database/migrations/20261101050400_create_stock_counts.php:6`; `app/Infrastructure/Persistence/PdoTransferRepository.php:41`; `app/Application/Security/PermissionCatalog.php:91`
- **Comportamiento actual:** No hay consumidores app/ de stock_counts/stock_count_items. inventory_lots/inventory_serials aparecen para detectar y rechazar trazabilidad en repositorios, no para altas/asignaciones/movimientos completos. Hay ajustes count_gain/count_loss, pero no ciclo documental de conteo. M09/M10 rechazan variantes con identificaciones existentes.
- **Esperado y fundamento:** requirements.md: conteos y control opcional por lote/caducidad/serie cuando se use. El rechazo protector es correcto como límite transitorio, no equivale a implementar esa funcionalidad.
- **Impacto operativo:** No puede darse por terminado inventario avanzado ni operar mercancía trazada de principio a fin. Un ajuste genérico no sustituye el conteo auditable.
- **Dependencias:** M03, M05 y logística M06/M08/M09/M10/devoluciones.
- **Corrección recomendada (no ejecutada):** Completar el flujo ya acordado o excluirlo expresamente de un lanzamiento limitado; evitar activar flags de trazabilidad como si fueran operativos.
- **Validación de cierre:** Conteo con diferencias e historial; lote con caducidad y serie única desde entrada hasta salida/devolución, o exclusión firmada y bloqueo consistente de operaciones no soportadas.

### AY-09 — La importación CSV de altas no completa la importación Excel acordada

- **Prioridad:** P1. **Módulo:** Importación de catálogo.
- **Tipo de evidencia:** Demostrado por implementación frente a requisitos.
- **Evidencia verificable:** `app/Application/Services/CatalogImportService.php:9`; `app/Application/Services/CatalogImportService.php:26`; docs/requirements.md, Catálogo; docs/integration-m13.md, Importación CSV.
- **Comportamiento actual:** Solo acepta CSV UTF-8, crea producto y primera variante inactivos y rechaza códigos/SKU existentes. No lee XLSX, no actualiza por SKU ni incorpora varias variantes del mismo producto en el archivo; no hay historial persistente de lotes consultable. Los JSON temporales pueden eliminarse tras un día.
- **Esperado y fundamento:** Requisito confirmado: Excel con preview, errores, transacción, historial y crear/actualizar por SKU, sin borrar por ausencia.
- **Impacto operativo:** No cubre la carga y mantenimiento masivo originalmente previstos. integration-m13.md describe correctamente el incremento CSV pero lo llama completo en código.
- **Dependencias:** M03, M13 y plantilla real del negocio.
- **Corrección recomendada (no ejecutada):** Acordar el formato definitivo y completar altas/actualizaciones e historial; no confundir CSV abrible en Excel con importador XLSX.
- **Validación de cierre:** Archivo acordado con alta, actualización, varias variantes, fila inválida y reenvío; preview exacto, rollback total y lote consultable sin cambios por ausencia.

### AY-10 — La importación puede confirmar datos y después informar que no confirmó cambios

- **Prioridad:** P1. **Módulo:** Importaciones / auditoría.
- **Tipo de evidencia:** Demostrado por frontera de commit; fallo inducido pendiente.
- **Evidencia verificable:** `app/Application/Services/CatalogImportService.php:20`; `app/Controllers/Admin/ProductImportsController.php:14`
- **Comportamiento actual:** CatalogImportService confirma la transacción comercial antes de actualizar el JSON. El controlador registra catalog.import.confirm después de retornar. Si falla esa auditoría o la escritura posterior al commit, el catch genérico muestra «no se confirmaron cambios» aunque los INSERT ya pudieron confirmarse. La auditoría comercial no comparte transacción.
- **Esperado y fundamento:** requirements.md: importación transaccional, historial y auditoría fiable; mensaje acorde con resultado real.
- **Impacto operativo:** Productos creados sin registro de confirmación o con resultado ambiguo para el operador; reintentos e investigaciones difíciles.
- **Dependencias:** M00, M02, M03, almacenamiento privado de M13.
- **Corrección recomendada (no ejecutada):** Confirmar datos y auditoría durable juntos; hacer recuperable el registro del lote y diferenciar fallo previo/posterior al commit.
- **Validación de cierre:** Inyectar fallo de auditoría y de escritura del JSON en base aislada; nunca debe notificarse rollback inexistente y el resultado debe ser recuperable e idempotente.

### AY-11 — Solo existe el origen delivery.incident

- **Prioridad:** P1. **Módulo:** Notificaciones internas M12.
- **Tipo de evidencia:** Demostrado por emisores del código.
- **Evidencia verificable:** `app/Application/Services/DeliveryManagementService.php:67`; docs/integration-m12.md, Límites y continuación.
- **Comportamiento actual:** El único productor de notificaciones localizado es la incidencia formal de entrega. Hay bandeja, contador, lectura y comprobación vigente del permiso; no hay productores de cotizaciones nuevas, asignaciones, autorizaciones, mínimos, transferencias ni cambios de costo.
- **Esperado y fundamento:** requirements.md, Notificaciones: avisos internos para esos eventos. La documentación M12 reconoce que se entregó solo un incremento vertical.
- **Impacto operativo:** No se puede depender de la campana como bandeja completa de pendientes operativos.
- **Dependencias:** Reglas de destinatarios y permisos de cada módulo emisor.
- **Corrección recomendada (no ejecutada):** Completar únicamente los eventos ya acordados y aprobar sus destinatarios; no añadir correos a empleados como requisito nuevo.
- **Validación de cierre:** Cada evento genera una entrega autorizada, deduplicada y transaccional; revocación de permisos retira acceso; rollback no deja avisos huérfanos.

### AY-12 — SMTP y recuperación de contraseña siguen pendientes

- **Prioridad:** P1. **Módulo:** Correo comercial / recuperación de acceso.
- **Tipo de evidencia:** Ausencia localizada y pendiente documentado.
- **Evidencia verificable:** Búsqueda en app/, config/routes.php y bin/: sin implementación SMTP ni rutas de recuperación; docs/requirements.md, Cotizaciones y Acceso; docs/quote-workflow.md, Entrega y actualización.
- **Comportamiento actual:** Se genera PDF y se registra un envío manual. Registrar send no envía correo. Existe cambio de contraseña administrativo, no recuperación autónoma por correo. WhatsApp no tiene integración automática, conforme al límite explícito.
- **Esperado y fundamento:** El alcance original incluye envío SMTP y recuperación. Las aclaraciones posteriores los mantienen pendientes y permiten entrega manual; no se debe presentar SMTP como requisito inventado ni exigir mensajería automática nueva.
- **Impacto operativo:** El alcance original no está completo; la aceptación de una salida inicial con envío manual requiere decisión explícita del negocio.
- **Dependencias:** Proveedor, credenciales y dominio; cola durable si se elige para efectos externos.
- **Corrección recomendada (no ejecutada):** Resolver el pendiente aprobado o formalizar el aplazamiento de lanzamiento; mantener claro al operador qué acciones solo registran una entrega.
- **Validación de cierre:** Si se implementa: entrega en buzón controlado, fallo recuperable y reenvío sin duplicados; recuperación con token de un uso/caducidad y sin enumeración. Si se aplaza: procedimiento manual aprobado y verificable.

### AY-13 — No está acreditado el sistema de respaldo y restauración exigido

- **Prioridad:** P1. **Módulo:** Operación M14.
- **Tipo de evidencia:** Parcial en repositorio; operación externa no verificada.
- **Evidencia verificable:** docs/requirements.md, Operación y calidad; bin/backup-m11-development.php, bin/restore-m11-backup.php; `app/Application/Jobs/DatabaseHealthJob.php:12`; docs/operation-jobs.md.
- **Comportamiento actual:** Hay utilidades específicas de respaldo/restauración de desarrollo e infraestructura durable de jobs. El único handler productivo registrado es database_health; no equivale a respaldo integral ni acredita cron instalado, retención, copia externa o restauración del despliegue actual.
- **Esperado y fundamento:** Respaldos diarios 30 días, mensuales 12 meses, antes de despliegues y restauración probada, según requisitos existentes.
- **Impacto operativo:** No hay evidencia suficiente de recuperación de datos y medios ante una pérdida real. No se afirma que el hosting carezca de respaldos externos: no se inspeccionó.
- **Dependencias:** Hosting/cPanel, responsables, DB, storage/media, secretos y procedimiento de despliegue.
- **Corrección recomendada (no ejecutada):** Cerrar M14 o aportar evidencia del mecanismo externo que cumpla la política; probar restauración en destino aislado.
- **Validación de cierre:** Recuperar base, medios y configuración en staging desde copia verificada; documentar retención, tiempos medidos, integridad y responsables.

### AY-14 — No hay validación actual de integraciones SQL y flujos autenticados

- **Prioridad:** P1. **Módulo:** QA / base real / producción.
- **Tipo de evidencia:** Limitación de verificación actual.
- **Evidencia verificable:** Exec: CreateProcessAsUserW error 5; child_process: EPERM; acceso al motor Docker: EPERM; navegador: host y pipe no disponibles. docs/integration-m13.md e integration-returns.md reconocen SQL aislado pendiente en sus cierres.
- **Comportamiento actual:** Se verificó HTTP público y anónimo, no se pudo consultar schema_migrations ni el esquema/datos actuales, ejecutar PHP CLI, autenticar flujos, concurrencia o restaurar copias. La confirmación del usuario de que migró no sustituye una inspección del esquema.
- **Esperado y fundamento:** Requisitos y AGENTS: pruebas relevantes, rutas, seguridad, concurrencia y despliegue verificados.
- **Impacto operativo:** Impide certificar integridad y funcionamiento completo. Es una brecha de evidencia, no la demostración de que esas operaciones fallen.
- **Dependencias:** PHP/Docker accesible, entorno de pruebas exclusivo, identidades seguras y navegador.
- **Corrección recomendada (no ejecutada):** Ejecutar las verificaciones de G en un entorno seguro con herramientas operativas, sin cambiar de destino a la base comercial.
- **Validación de cierre:** Resultados fechados del mismo commit y 74 migraciones; permisos negativos, SQL real y HTTP integrado; bases de pruebas controladas y recuperación comprobada.

### AY-15 — Configurar mínimos falla fuera de las primeras 500 variantes

- **Prioridad:** P2. **Módulo:** Inventario / mínimos.
- **Tipo de evidencia:** Demostrado por código.
- **Evidencia verificable:** `app/Application/Services/InventoryManagementService.php:142`; `app/Infrastructure/Persistence/PdoInventoryRepository.php:40`
- **Comportamiento actual:** setThreshold llama baseUnitId, que recorre variants() sin búsqueda. El repositorio ordena por nombre/SKU y devuelve como máximo 500. Una variante válida posterior no se encuentra y se informa «La variante no existe».
- **Esperado y fundamento:** requirements.md: miles de productos y mínimos por producto/sucursal.
- **Impacto operativo:** Catálogos grandes no pueden configurar todos sus mínimos aunque el saldo y la variante existan.
- **Dependencias:** M03/M05.
- **Corrección recomendada (no ejecutada):** Consultar la variante por identificador para obtener su unidad, sin depender de listados acotados.
- **Validación de cierre:** Con 501 o más variantes, configurar mínimo de una posterior al corte y comprobarlo por ID y sucursal.

### AY-16 — El reporte excluye solicitudes sin documento y mide atención desde una fecha incorrecta

- **Prioridad:** P2. **Módulo:** Reportes / cotizaciones.
- **Tipo de evidencia:** Demostrado por consulta SQL; datos reales no medidos.
- **Evidencia verificable:** `app/Infrastructure/Persistence/PdoReportRepository.php:16`; `app/Views/admin/reports/index.php:19`
- **Comportamiento actual:** El FROM arranca en quotes y no incluye public_quote_requests/quote_workflows sin quote_id. Primera atención resta q.created_at del primer take/contact/prepare del workflow, que puede ser anterior a crear el documento. Puede dar minutos negativos y deja fuera solicitudes aún no preparadas; UI lo rotula desde la recepción.
- **Esperado y fundamento:** requirements.md y flujo unificado: seguimiento de cada solicitud y tiempos de atención. No se debe confundir fecha del documento con recepción.
- **Impacto operativo:** Los pendientes tempranos y demoras reales se subrepresentan; indicadores pueden resultar engañosos.
- **Dependencias:** M08/M11/seguimiento/M13.
- **Corrección recomendada (no ejecutada):** Definir universo de solicitudes y documentos y calcular atención desde la recepción original; conservar separación de métricas documentales.
- **Validación de cierre:** Solicitud día 1, toma día 2, documento día 3: tiempo positivo desde día 1; incluir también solicitud nunca preparada y evitar doble conteo al convertir.

### AY-17 — Los cinco reportes no cubren todos los reportes acordados

- **Prioridad:** P2. **Módulo:** Reportes M13.
- **Tipo de evidencia:** Demostrado frente a alcance.
- **Evidencia verificable:** `app/Application/Reporting/ReportFilters.php:6`; docs/requirements.md, Notificaciones y reportes.
- **Comportamiento actual:** Solo hay quotes, inventory, purchases, transfers y deliveries. Kardex existe como pantalla M05, pero no como exportación M13; faltan filtros por vendedor en reportes y reportes de importaciones/errores. Auditoría tiene pantalla propia, sin demostrar exportación requerida. No existe detalle de cantidades parciales en el reporte de entregas.
- **Esperado y fundamento:** Reportes en pantalla y Excel/PDF de movimientos, mínimos/agotados, cotizaciones por vendedor/tiempos, entregas parciales, importaciones, errores y auditoría según permiso.
- **Impacto operativo:** El panel M13 no completa el control administrativo del alcance, aunque sus cinco consultas estén implementadas.
- **Dependencias:** M05/M08/M10/M13 y persistencia de importaciones.
- **Corrección recomendada (no ejecutada):** Completar consultas y exportaciones ya contempladas, reutilizando datos operativos; definir significado de parciales y agotados.
- **Validación de cierre:** Para cada reporte del requisito, pantalla/XLSX/PDF con filtros equivalentes, totales conciliados, paginación y alcance por sucursal/vendedor.

### AY-18 — Los KPI suman cantidades de unidades incompatibles

- **Prioridad:** P2. **Módulo:** Reportes / inventario.
- **Tipo de evidencia:** Demostrado por SQL y etiquetas.
- **Evidencia verificable:** `app/Infrastructure/Persistence/PdoReportRepository.php:33`; `app/Views/admin/reports/index.php:22`
- **Comportamiento actual:** Se suman physical/reserved/available de todas las variantes sin agrupar por unit_id. La vista muestra esos totales como existencia física/apartada/disponible y «unidades registradas». Una suma de metros, kilogramos y piezas no es una magnitud operativa válida.
- **Esperado y fundamento:** El inventario existente define unidad y precisión por variante; la lectura del reporte debe respetarlas.
- **Impacto operativo:** Puede inducir decisiones erróneas aun cuando cada renglón sea correcto.
- **Dependencias:** M03/M05/M13.
- **Corrección recomendada (no ejecutada):** Agrupar indicadores por unidad compatible o usar indicadores no aditivos; conservar unidades en exportaciones.
- **Validación de cierre:** Dos variantes: 10 m y 5 kg; no mostrar 15 unidades como existencia física global.

### AY-19 — El PDF puede superar el límite anunciado de 10,000 filas

- **Prioridad:** P2. **Módulo:** Reportes / exportación.
- **Tipo de evidencia:** Demostrado por límites del código.
- **Evidencia verificable:** `app/Controllers/Admin/ReportsController.php:18`; `app/Infrastructure/Persistence/PdoReportRepository.php:15`; `app/Infrastructure/Documents/XlsxWriter.php:9`
- **Comportamiento actual:** export solicita límite 10001. page solo marca hasMore al obtener más que ese límite. Con exactamente 10001 filas hasMore es falso: PDF las acepta; XLSX las rechaza por su comprobación propia. Con 10002 se rechaza antes.
- **Esperado y fundamento:** integration-m13.md: exportaciones de hasta 10,000 filas y equivalencia de filtros/límites.
- **Impacto operativo:** Resultados distintos por formato y límite inconsistente, sin demostrar pérdida de datos.
- **Dependencias:** Repositorio de reportes y escritores.
- **Corrección recomendada (no ejecutada):** Aplicar un único límite de 10000 y usar la fila adicional solo para detectar exceso.
- **Validación de cierre:** 9999, 10000, 10001 y 10002 coincidencias: aceptación/rechazo idéntico en PDF y XLSX, sin truncamiento silencioso.

### AY-20 — Excepciones, servicios y mapa público no tienen recorrido administrativo/público completo

- **Prioridad:** P2. **Módulo:** Sucursales / servicios / mapas.
- **Tipo de evidencia:** Demostrado por rutas y vistas.
- **Evidencia verificable:** `app/Application/Services/BranchScheduleManagementService.php:96`; `app/Controllers/Admin/BranchesController.php:42`; `app/Views/components/public/branch-card.php:1`
- **Comportamiento actual:** Hay servicios internos para horarios excepcionales y servicios comerciales, pero routes.php solo expone altas/bajas de horario regular y consulta de servicios. El mapa Leaflet está conectado al editor/detalle administrativo; las sucursales públicas muestran un enlace externo a ubicación, sin mapa dinámico con búsqueda ni horarios.
- **Esperado y fundamento:** requirements.md, Sucursales y mapas: formularios de horarios/excepciones/servicios y mapa público dinámico de carga diferida.
- **Impacto operativo:** Administrar excepciones y servicios depende de acceso técnico fuera del panel; la información pública no cumple toda la función prevista.
- **Dependencias:** M01/M11, proveedor de mapas y datos reales.
- **Corrección recomendada (no ejecutada):** Conectar las funciones internas existentes al panel con permisos y auditoría; completar el mapa público aprobado sin imponer un proveedor nuevo.
- **Validación de cierre:** Operador autorizado cambia cierre por fecha y servicio desde panel; público consulta horarios y mapa con teclado y carga diferida; rol sin permiso no escribe.

### AY-21 — Los cambios de horario y su auditoría no son atómicos

- **Prioridad:** P2. **Módulo:** Auditoría de horarios / errores operativos.
- **Tipo de evidencia:** Demostrado por frontera transaccional.
- **Evidencia verificable:** `app/Controllers/Admin/BranchesController.php:108`; `app/Controllers/Admin/BranchesController.php:122`; `app/Application/Services/BranchScheduleManagementService.php:134`
- **Comportamiento actual:** addRegular/removeRegular confirman su propia transacción. El controlador escribe audit()->record después. Si esa escritura falla, el horario puede haber cambiado y el run informa fallo; no hay auditoría de éxito garantizada junto al cambio.
- **Esperado y fundamento:** requirements.md: trazabilidad de acciones sensibles y consistencia de operaciones; el patrón de otros servicios incluye auditoría dentro de la transacción.
- **Impacto operativo:** Cambio real sin registro de éxito y resultado ambiguo para el administrador.
- **Dependencias:** M00/M01/M02.
- **Corrección recomendada (no ejecutada):** Integrar auditoría dentro de la transacción del servicio o una garantía durable equivalente; preservar mensajes de resultado exactos.
- **Validación de cierre:** Fallo de inserción de auditoría en prueba aislada: cambio y registro confirman juntos o ambos revierten.

### AY-22 — Excepciones inesperadas se ocultan sin referencia diagnóstica suficiente

- **Prioridad:** P2. **Módulo:** Observabilidad / soporte.
- **Tipo de evidencia:** Demostrado por manejadores de error.
- **Evidencia verificable:** `app/Controllers/Admin/AdminController.php:37`; `app/Controllers/Admin/QuotesController.php:42`; public/index.php, catch global.
- **Comportamiento actual:** El catch global registra excepciones no capturadas, pero varios controladores capturan Throwable y devuelven mensajes genéricos sin log técnico. run() registra a veces un failure de razón genérica e incluso ignora fallo de ese registro; no conserva una referencia correlacionable.
- **Esperado y fundamento:** M14 contempla observabilidad y diagnóstico; conservar detalle técnico seguro sin exponerlo al usuario.
- **Impacto operativo:** Errores de persistencia o integraciones pueden ser difíciles de reconstruir; app.log vacío o antiguo no demuestra ausencia de fallos.
- **Dependencias:** Registro privado, saneamiento y política de retención.
- **Corrección recomendada (no ejecutada):** Registrar errores inesperados con identificador y contexto mínimo seguro; mostrar esa referencia al operador, sin secretos ni datos sensibles.
- **Validación de cierre:** Inducir fallo controlado de infraestructura en staging; respuesta segura y referencia correlacionada en log privado, sin contraseña, token ni datos de contacto.

### AY-23 — El costo anterior no se normaliza por unidad ni respeta necesariamente el pasado documental

- **Prioridad:** P2. **Módulo:** Compras / costos.
- **Tipo de evidencia:** Demostrado por consulta; escenario SQL pendiente.
- **Evidencia verificable:** `app/Infrastructure/Persistence/PdoPurchasingRepository.php:32`; `app/Views/admin/purchasing/invoice-detail.php:4`
- **Comportamiento actual:** El subquery compara mismo proveedor/variante y otra factura, pero no filtra unidad ni exige fecha anterior a la factura actual. Selecciona el documento más reciente entre todos los demás. La vista compara unit_cost directamente.
- **Esperado y fundamento:** requirements.md: un cambio de costo genera alerta para decisión manual; las conversiones por unidad ya forman parte del modelo.
- **Impacto operativo:** Caja frente a pieza o consulta de factura histórica puede producir una alerta de cambio de costo engañosa.
- **Dependencias:** M03 conversiones, M06 y definición de costo comparable.
- **Corrección recomendada (no ejecutada):** Comparar costo en unidad equivalente y definir referencia temporal estable; conservar importes originales del documento.
- **Validación de cierre:** Mismo costo base con caja de 10 y pieza: sin falsa variación; factura histórica no llama «anterior» al costo de un documento futuro.

### AY-24 — El contador del slider conserva tres campañas fijas

- **Prioridad:** P3. **Módulo:** Home / campañas.
- **Tipo de evidencia:** Reproducido con JS en memoria; sin navegador real.
- **Evidencia verificable:** `resources/js/components/sliders.js:7`; `app/Views/components/public/hero-slider.php:1`
- **Comportamiento actual:** PHP inicia el contador con count($slides), pero sync() de Swiper lo reemplaza siempre por / 03. Ejecución aislada con stub de una campaña produjo 01 / 03.
- **Esperado y fundamento:** CMS admite cantidad variable de banners; el contador debe representar las campañas presentes.
- **Impacto operativo:** Información y anuncio accesible inconsistentes al publicar una o más de tres campañas.
- **Dependencias:** M11 y slider local.
- **Corrección recomendada (no ejecutada):** Derivar el denominador del conjunto real de slides.
- **Validación de cierre:** Una, dos y cuatro campañas: contador y navegación anuncian la cantidad correcta, incluido reduced motion.

### AY-25 — Las instrucciones de entorno y estado de módulos están desactualizadas

- **Prioridad:** P2. **Módulo:** Documentación operativa / seguridad de despliegue.
- **Tipo de evidencia:** Contradicción demostrada.
- **Evidencia verificable:** `bootstrap/app.php:4`; docs/deployment-cpanel.md, Variables y Requisitos adicionales M11; AGENTS.md; README.md; docs/modules.md.
- **Comportamiento actual:** AGENTS y guía de cPanel dicen que el servidor no carga .env; bootstrap/app.php lo carga en HTTP y CLI. La Home intenta PDO para decidir M11; la guía dice que no usa conexión. modules.md aún declara M13 pendiente, aunque existen controlador, repositorio, migración e interfaz; hay cifras históricas distintas de migraciones.
- **Esperado y fundamento:** Una guía de despliegue debe describir el código que se publica y distinguir hitos anteriores del estado vigente.
- **Impacto operativo:** Configuración de precedencia equivocada, omisión de dependencias y falsa impresión de cierre o de ausencia de módulos.
- **Dependencias:** Cierre técnico del commit, política definitiva de entorno y M14.
- **Corrección recomendada (no ejecutada):** Actualizar documentación/reglas en trabajo posterior autorizado; registrar una matriz de estado vigente con fechas y resultados reproducibles.
- **Validación de cierre:** Un operador instala staging siguiendo la guía sin instrucciones externas y obtiene configuración, esquema y capacidades esperadas; valores exportados prevalecen conforme al parser.

### AY-26 — El registro de códigos no completa lectura e impresión operativa

- **Prioridad:** P2. **Módulo:** Códigos de barras / intercambio M13.
- **Tipo de evidencia:** Implementación parcial localizada frente a requisitos.
- **Evidencia verificable:** `app/Infrastructure/Persistence/PdoQuoteRepository.php:50`; `app/Controllers/Admin/ProductsController.php:60`; docs/requirements.md, Inventario y Catálogo.
- **Comportamiento actual:** Se almacenan códigos y se usan para buscar variantes en cotizaciones. No se localiza generación/impresión de etiquetas ni un recorrido de captura por código conectado a entradas, inventario y entrega; el registro textual de un código no demuestra esas operaciones.
- **Esperado y fundamento:** El alcance existente contempla lectura e impresión de códigos en entradas, inventario y entrega; M13 los incluye en intercambio.
- **Impacto operativo:** Los operadores no pueden asumir que lectores/etiquetas sustituyan selección manual de variantes en esos procesos.
- **Dependencias:** M03/M05/M06/M10/M13 y formatos de etiqueta/lector por confirmar.
- **Corrección recomendada (no ejecutada):** Completar el recorrido previsto o registrar su aplazamiento explícito; no añadir hardware o proveedor sin decisión del negocio.
- **Validación de cierre:** Código de una variante escaneado en cada operación autorizada: selección inequívoca y validación normal; etiqueta impresa legible, código desconocido/duplicado tratado sin movimiento.

## D. Hallazgos funcionales

### Flujo prioritario reconstruido de principio a fin

| Paso | Implementación real y validaciones | Resultado de auditoría |
|---|---|---|
| Selección pública | CmsController::requestForm filtra sucursales habilitadas; consulta variantes activas y disponible de sucursal | HTTP actual sin formulario. No se prueba captura real (AY-01); sin lista persistente entre búsquedas (AY-03) |
| Recepción | PublicQuoteRequestService::receive: throttle independiente, consentimiento/honeypot, contacto, 1–100 variantes, operation_key, hash y privacidad vigente; locks de sucursal/aviso/variantes; snapshot y auditoría | Diseño inspeccionado, sin POST comercial. Preserva aviso aceptado y no mueve stock |
| Captura externa | QuoteWorkflowRules valida origen/contacto/materiales; receive crea folio y asigna creador con key/hash, transacción y evento | Código implementado; no se inventa catálogo ni precio durante la recepción |
| Bandeja y toma | PdoQuoteWorkflowRepository usa UNION de documentos, solicitudes sin documento y capturas; permisos separados para recepción/documento; seguimiento materializado, versión optimista y claves | Backend existe; asignación y permisos tienen AY-05/06. No se probó SQL ni concurrencia actual |
| Preparación | Relaciona cada renglón con variantes, exige cliente y sucursal, lista comercial, vigencia, condiciones; excluidos/sustituidos requieren conformidad; guarda documento y vínculo en callback de la misma transacción | Cliente interno obligatorio al preparar: decisión M11 documentada, no se considera error crear/vincularlo manualmente |
| Cálculo comercial | Precio M04 efectivo, cantidad decimal según unidad, impuesto/snapshot, política de descuento y aprobación por hash de versión | Código revisado; no hay tasas o precios reales certificados |
| Verificación y emisión | Fingerprint del documento, stock disponible local, revisión invalidada al editar; emisión formal invoca documentIssued dentro de transacción | Conflicto de faltantes/traslado AY-04. Emisión cambia estado documental sent, etiquetado «Emitida»; no equivale al envío de seguimiento |
| Entrega del PDF | PDF privado de versión issued; workflow send exige confirmación, canal, destinatario y registra versión exacta | Manual por diseño de incremento; no envía correo (AY-12). No se exige automatización nueva |
| Aceptación | Revalida vigencia/transición, bloqueo de cotización y reserva por variantes; considera cobertura M09 | Código coherente con apartado. Conciliación y competencia por última unidad pendientes |
| Transferencia | Aprueba/aparta origen; despacho libera apartado origen y genera salida; recepción entra destino y aparta si cotización aceptada | Implementado por transacciones, keys y hash; no demostración actual. Se permite salida/recepción parcial y no cancelar lo ya salido |
| Entrega física | Cotización aceptada actual; asigna cantidades sin exceder pendientes; prepara solo apartado recibido; salida consume apartado y físico; confirma receptor y cantidades | Evita conceptualmente doble descuento y entrega de mercancía solo en tránsito; falta SQL/HTTP actual |
| Cancelación | Libera solo lo pendiente, descontando consumos M10; no revierte salidas físicas como si no hubieran existido | Código inspeccionado; probar cancelación concurrente con dispatch/receive y estados inactivos |
| Devolución | Fuente físicamente recibida; committed descuenta devoluciones registradas/autorizadas/finalizadas; movimientos solo al confirmar; dañada/inspección no suma disponible | No confundir con reembolso o nota de crédito. Su SQL aislado sigue sin resultado actual (AY-14) |
| Reporte y cierre | Cinco consultas, snapshots documentales y exportaciones | No cubre la recepción temprana ni todas las métricas/formatos comprometidos (AY-16–19) |

### Pendientes que no son errores demostrados

- No se constató pérdida o duplicación efectiva de una solicitud ni saldo negativo real. Existen defensas de idempotencia, claves únicas, transacción y locks; falta ejercerlas en el estado vigente.
- No hay pagos, ventas financieras, facturación fiscal, órdenes de compra ni portal de clientes. Su ausencia corresponde al alcance confirmado y no se añade como deuda.
- Un almacén independiente de una sucursal, pedido comercial independiente, firma o inventario de cuarentena no se exige sin decisión de negocio. Devoluciones dañadas/inspección sin disponible es el comportamiento documentado.
- Las consultas al cliente, su conformidad, aceptación y registro del envío son acciones humanas previstas. No son automatizaciones faltantes por el hecho de ser manuales. La falta de SMTP sí está documentada como pendiente original aplazado.
- La advertencia de cambio de costo existe en la pantalla de factura; falta la notificación interna general (AY-11) y su comparación requiere AY-23. No se propone actualización automática de precios.

## E. Integración entre módulos

| Origen → destino | Vínculo implementado | Garantía localizada | Pendiente o problema |
|---|---|---|---|
| M01/M02 → módulos | Sucursal, rol, permiso por operación y user_branches | Autorización SQL en backend y guardas en controladores | No aplicación uniforme de habilitación modular AY-07; matriz autenticada NV |
| M03 → M04 | Variante/unidad/lista/impuesto | FKs, vigencias, Decimal, historial | Comprobar cambios concurrentes y tasas reales |
| M03 → M05 | Unidad base y conversiones exactas | Rechazo de conversión no representable; no disponible negativo | Mínimos truncados AY-15; trazabilidad avanzada AY-08 |
| M06 → M05 | goods_receipts/items → inventory_movements | Documento no mueve stock; recepción física dentro de transacción | Prueba actual parciales/exceso/reenvío; AY-23 |
| M07/M04 → M08 | Cliente de sucursal/lista y precio efectivo → snapshots | Versiones emitidas no se sobrescriben; hash para aprobación | Revalidación actual y casos límite NV |
| M11 → recepción M08 | Privacy version, consentimiento, items activos, folio | Unicidad operation_key/hash y locks | Preparación local AY-01; lista pública AY-03 |
| Recepción → seguimiento → documento | request_id/quote_id/workflow y callbacks | Conservación de solicitud original y conformidad | AY-04/05/06; comprobar ida/vuelta y documentos legacy |
| M08 → M05 | Aceptación → inventory_reservations/items | Reserva sin salida; cancelación libera remanente | Reconciliación histórica actual NV |
| M08 ↔ M09 | Versión emitida → transferencia/cobertura | Reserva origen, cobertura pendiente y reserva destino | Flujo unificado con faltante AY-04 |
| M08/M09 → M10 | Reserva local/destino → consumptions → movimiento de salida | Consumo vinculado e idempotente; no descontar dos veces | Pruebas concurrencia/abortos y lote/serie |
| M06/M10 → devoluciones → M05 | goods_receipt_item o delivery_item → return_items/event/movement | Límite devolvible menos comprometido y bloqueo de fuente | Integración SQL vigente NV; trazados rechazados en casos existentes |
| M10 → M11 medios | Evidencia de evento confirm/incident | Medio privado autorizado, append-only | Sin prueba visual/SQL actual |
| M10 → M12 | Evento delivery.incident en transacción | Destinatarios con permisos y sucursal; key única | Único origen (AY-11) |
| M08/M05/M06/M09/M10 → M13 | Reportes de documentos/saldos | Alcance servidor y parámetros preparados | Universo y métricas AY-16/18, reportes faltantes AY-17 |
| M13 importación → M03/M02 | Alta y primera variante + auditoría posterior | INSERT en transacción y archivo privado bloqueado | Auditoría/estado postcommit AY-10; actualización/historial AY-09 |
| Jobs → operación | Handler database_health; schedule/work/recover | Lease token, historial de intentos y reintentos | No respaldos ni SMTP implementados por el mero hecho de existir la cola |

Las transacciones se componen mediante servicios que comparten Connection; los adaptadores WithinTransaction no inician una segunda transacción. Connection no ofrece transacciones anidadas ni reintento general de deadlock: eso es una característica a tener en cuenta al ampliar integraciones, no un fallo probado por sí solo. Los locks de sucursal serializan muchas operaciones de una misma sucursal; protegen invariantes pero su costo bajo carga debe medirse.

## F. Seguridad y base de datos

### Seguridad observada y revisada

| Control | Evidencia real | Conclusión limitada |
|---|---|---|
| Acceso anónimo | 168 rutas administrativas de negocio probadas por GET/POST según su registro: 168 respuestas 303 a /admin/login | Pasa este control de acceso anónimo; no certifica autorización entre usuarios |
| Autenticación | AuthenticationService: hash seguro/dummy hash, usuario activo, rehash, token aleatorio, hash de token persistido, rate limiting por sujeto/IP | Implementada; pruebas de login/bruteforce no ejecutadas contra base operativa |
| Sesiones | SessionManager: strict_mode, cookie HttpOnly/SameSite, Secure configurable/HTTPS, regeneración al autenticar; validateSession y revocación por versión/estado | Implementado por código; expiración y cookies con HTTPS real NV |
| Autorización | Roles/permisos por sucursal, user_branches, rechazo a administrar usuarios globales/ajenos y permisos superiores | Diseño real en backend; AY-05 y AY-06 necesitan resolución/prueba |
| CSRF | requireCsrf en operaciones mutadoras inspeccionadas, token con hash_equals, recepción pública protegida | No es solo UI. Sin prueba autenticada negativa masiva ni envío público |
| SQL | PDO::ATTR_EMULATE_PREPARES=false y consultas preparadas; tablas dinámicas en repositorios acotadas/seleccionadas internamente | No se demostró SQL injection. Falta fuzzing seguro por rol/ruta y consultas reales |
| XSS | e() en componentes inspeccionados; DOM dinámico usa textContent; CMS estructurado; no libre render de entrada como HTML | No se demostró XSS; no equivale a auditoría dinámica de todos los campos persistidos |
| Medios | fileinfo/getimagesize/decodificación GD; límites de peso/píxeles/memoria; WebP nuevo, nombre aleatorio, hash y tamaño verificados al leer | Protección sustancial. Subidas, corrupción y límites no ejercidos ahora |
| Exposición | /.env, /.git/config, /config/database.php, /bin/migrations.php y /router.php devolvieron 404 local | No se divulgó contenido privado en esas sondas. Apache/FTP real pendiente |
| Cabeceras | CSP self sin unsafe-inline, nosniff, referrer-policy y no-store en respuestas consultadas | Controles observados; HTTPS y cabeceras del hosting NV |
| Auditoría y error | Eventos transaccionales en muchos servicios; mensajes públicos genéricos | Excepciones AY-10/21/22; no confundir no exposición con observabilidad suficiente |
| Recuperación | Cambio de clave administrativo sí; reset SMTP no localizado | Pendiente original AY-12 |

No se hizo escaneo de historial Git para afirmar ausencia de secretos históricos. .env está fuera de public y la configuración real se mantuvo privada. No se afirma que el usuario SQL actual tenga privilegios mínimos: no fue posible consultar grants. Tampoco se ha confirmado cifrado de conexión, políticas del servidor o aislamiento de cuentas a partir de que DB_* estén presentes.

### Esquema declarado frente a esquema vivo

Se contaron 74 migraciones PHP. Extracción textual del SQL literal: **102 sentencias CREATE TABLE**, **236 apariciones de FOREIGN KEY** y **246 CHECK(** en las migraciones. La tabla técnica schema_migrations es creada por MySqlMigrationStore fuera de ese conteo. Son métricas de fuente, no inventario del servidor ni evidencia de que cada restricción esté aplicada. El esquema esperado completo incluye por tanto la tabla técnica además de las declaraciones comerciales/técnicas de las migraciones.

Familias inspeccionadas:

- Organización: branches, modules, branch_modules, branch_hours, branch_special_hours, services, branch_services.
- Acceso: users, roles, permissions, role_permissions, user_roles, user_branches, user_sessions, audit_logs.
- Catálogo: categories, brands, units, products, product_variants, product_barcodes, attribute_definitions/options, category_attributes, variant_attribute_values, unit_conversions.
- Precios: tax_categories/rates, price_lists/rules, branch_variant_prices, price_history, discount_policies.
- Inventario: balances/movements, stock_thresholds, lots/serials, stock_counts/items, reservations/items.
- Entradas y clientes: suppliers/contacts, supplier_invoices/items, goods_receipts/items, customers/contacts/addresses.
- Comercial: quotes, quote_contacts, quote_versions/items, status_history, assignments, approvals, quote_workflows/events, public_quote_requests/items, legal_acceptances y public_request_limits.
- Logística: transfers/items/events/event_items, vehicles, drivers, delivery_runs, deliveries/items/stops/events/event_items/reservation_consumptions.
- CMS: media/variants/links, banners, promotions, events, reviews, pages, site_settings, navigation_items, page_revisions, cms_operations, legal_documents/versions, quote_term_templates, product_images, supplier_invoice_files, delivery_evidence.
- Operación: operation_jobs/runs, notification_events/deliveries, operational_returns/items/events/event_items.

Las migraciones usan InnoDB/utf8mb4, IDs/FKs restrictivas, índices de acceso y unicidad de claves/documentos/operaciones. DECIMAL evita aritmética flotante comercial; estados se restringen mediante CHECK y las reglas de transición viven además en dominio/servicios. Las FKs polimórficas source_type/source_id no pueden acreditar por sí solas la validez de todos los orígenes: la integridad depende del servicio y debe conciliarse. No se demuestra un huérfano existente.

Migrator usa lock asesor, orden, registro de lotes y preflight de todo el último lote antes de bajar; down repite protecciones de datos. **DDL MySQL/MariaDB no es atómico**: una migración puede crear algunas tablas y fallar antes del registro. No se ejecutó migrate/rollback. Una instalación reciente exitosa comunicada por el usuario no verifica SHOW CREATE TABLE, índices o datos actuales.

### Consultas de solo lectura pendientes en entorno autorizado

No se ejecutaron estas verificaciones ni se incluyen resultados inventados:

1. SELECT VERSION(), DATABASE(), CURRENT_USER(); inventario de schema_migrations contra los 74 IDs del checkout; no faltar/aparecer migraciones extra o pendientes fuera de orden.
2. information_schema.TABLES/COLUMNS/STATISTICS/TABLE_CONSTRAINTS/REFERENTIAL_CONSTRAINTS/CHECK_CONSTRAINTS y SHOW CREATE TABLE: motores, collations, precisión, índices, FKs y CHECK reales.
3. SHOW GRANTS de la cuenta efectiva; confirmar permisos mínimos y separación de dev/test/prod sin publicar identidades ni secretos.
4. Contar saldos con physical_quantity < reserved_quantity, disponible negativo o available_quantity distinto de physical_quantity - reserved_quantity. Cero esperado.
5. Conciliar movimientos por sucursal/variante con saldos, apartados activos menos liberados/consumidos y destino reservado de transferencias; no basta que available sea no negativo.
6. Buscar huérfanos por LEFT JOIN en vínculos de cotización/versión/solicitud/workflow, partidas y consumos/devoluciones, y validar coherencia de sucursal, variante y unidad entre vínculos.
7. Verificar cantidades recibidas/despachadas/aprobadas/solicitadas y elegibilidad de devoluciones; sin sobreconsumo ni doble movimiento por operación.
8. Detectar periodos de precio/tasa/regla activos solapados y estado documental incoherente con issued/accepted y reservas.
9. EXPLAIN/EXPLAIN ANALYZE en copia representativa para bandeja UNION, reportes agregados, búsquedas LIKE con comodín inicial y coste anterior; no ejecutar carga intensiva en operación.
10. Confirmar backup restaurable, schema y checksums sin copiar datos sensibles a este informe.

**Riesgos no demostrados:** contención por locks amplios de sucursal, deadlocks entre órdenes de bloqueo distintos, carrera en protección del último administrador si dos operaciones afectan usuarios diferentes, saturación de reportes agregados y acumulación de registros técnicos. Deben probarse con conexiones independientes y volumen representativo. No se presentan como corrupción o vulnerabilidad confirmadas.

### Dependencias

Versiones del lockfile inspeccionado: Swiper 12.2.0; Leaflet 1.9.4; esbuild 0.25.12; @playwright/test 1.64.0; Tailwind y CLI 4.3.3. No se ejecutaron npm audit, instalación ni actualizaciones; tampoco se enviaron archivos privados a servicios de análisis. La consulta pública de avisos fue acotada y **no certifica el árbol transitivo completo**.

La declaración oficial de Leaflet explica que bindPopup recibe HTML deliberadamente y recomienda textContent/elementos seguros; el mapa propio inspeccionado usa textContent para resultados y no se localizó bindPopup con entrada no confiable. No se declara XSS solo por utilizar Leaflet 1.9.4. Fuente: [posición de los mantenedores](https://github.com/Leaflet/Leaflet/issues/10214).

El aviso oficial esbuild GHSA-g7r4-m6w7-qqqr identifica versión afectada 0.27.3 y servidor de desarrollo Windows. El lock actual indica 0.25.12 y scripts/build.mjs usa build/context.watch, no serve. Ese aviso no prueba exposición de este proyecto. Fuente: [aviso oficial](https://github.com/evanw/esbuild/security/advisories/GHSA-g7r4-m6w7-qqqr). No se utiliza una lista genérica de CVE para atribuir fallos al producto sin validar versión, uso y superficie.

Antes de cierre, ejecutar análisis de dependencias reproducible con lockfile completo, distinguir dependencias de compilación/pruebas de código distribuido y contrastar avisos con las fuentes oficiales. Una ausencia de resultados en búsqueda no equivale a ausencia de vulnerabilidades.

## G. Cobertura de pruebas

### Ejecutado realmente en esta sesión

| Verificación | Resultado | Alcance práctico |
|---|---|---|
| Inventario de fuentes/migraciones/rutas | 490 PHP en conjunto de fuentes; 74 migraciones; 178 rutas | Conteo de archivos y rutas, no porcentaje de funcionalidad |
| Destinos estáticos de rutas | 178/178 clases y métodos localizados; 0 duplicados método/URL | No reemplaza lint ni dispatch con parámetros válidos |
| Acceso anónimo a rutas privadas | 168/168 GET/POST registrados de negocio: 303 a /admin/login | Se excluyeron login/logout, no se autenticó ni escribió comercio |
| Home y login | GET / y /admin/login: 200 | Responden; Home actual sin catálogo/sucursales activos |
| Solicitud pública | GET /solicitar-cotizacion: 200 sin form; aviso explícito de no disponible | Resultado AY-01, no recepción real |
| Legal e indexación | /legal y /sitemap.xml: 404 | Privacidad no disponible; sitemap 404 puede ser correcto con indexación deshabilitada |
| Aislamiento local | /.env, /.git/config, /config/database.php, /bin/migrations.php, /router.php: 404 | No demuestra configuración Apache del destino |
| Contrato HTTP | HEAD /: 200 cuerpo vacío; POST /: 405 Allow GET, HEAD; desconocida: 404 | Métodos y errores básicos correctos |
| Assets entregados | CSS app: 200/203042 bytes; JS app: 200/131406; Leaflet: 200/149483 | Existencia/entrega HTTP, sin ejecutar build ni probar sincronización completa de bundles |
| Contador de campañas JS | Con stub de Swiper y un slide: sync fija 01 / 03 | Reproducción en memoria de AY-24; no es prueba de layout |
| Activación de filtros JS | Con DOM sin data-product solo se consultó ese selector y se retornó | Corrobora rama usada por tarjetas publicadas; interacción real con productos aún NV |
| Configuración | Claves APP_*, DB_* y SESSION_* presentes en .env, sin valores expuestos | Presencia no significa validez, privilegio correcto ni configuración efectiva del proceso |
| Logs | Se leyeron metadatos y se contaron categorías sin volcar mensajes: app.log existente de 1857 bytes, última modificación 2026-11-04 UTC; también log histórico de lint | No hay señal suficiente para certificar ausencia de errores; catches pueden no registrar AY-22 |

### Suites localizadas y límites de sus resultados

- tests/run.php incluye reglas/contratos desde persistencia y M01–M13, devoluciones, navegación, CMS, biblioteca, publicación y seguimiento. Los 26 includes directos localizados existen. El número de asserts que figura en documentos anteriores no se reporta como resultado actual.
- Se localizaron 26 programas PHP de integración cuyo nombre coincide con mysql*integration.php. Hay SQL y HTTP de M02–M11, multiproceso de inventario/cotizaciones/transferencias/entregas/CMS/jobs/notificaciones/devoluciones y flujo unificado. La existencia de un worker o un assert no demuestra su ejecución.
- M13 tiene tests/m13-reporting.php: normalización, estructuras OOXML/PDF, plantilla y redirecciones anónimas; esto no cubre importación real, consultas con resultados, límites de exportación, permisos cruzados ni los tiempos del workflow. No se localizó una suite SQL integral M13 dedicada equivalente a las de M08–M12.
- Los browsers de diseño generan HTML/fixtures con vistas reales y revisan diálogos y tamaños; eso no prueba persistencia ni autorización HTTP autenticada cuando el test usa un servidor de fixtures. Se debe distinguir de m08/m09/m10/m11-http-integration y quote-workflow-http-integration.
- Los cierres anteriores declaran PASS para múltiples módulos, mientras M13 y devoluciones documentan que SQL quedó bloqueado por autenticación. Son antecedentes H, con fecha, host y versión de entonces. Esta auditoría no confirmó que esos mismos resultados correspondan al commit y esquema hoy disponibles.

### No ejecutado y motivo

| Validación | Estado y motivo | Cómo verificarla después con seguridad |
|---|---|---|
| Sintaxis PHP / npm test | No ejecutados: procesos bloqueados | PHP 8.2+ funcional; inspeccionar efectos del runner y ejecutarlo con configuración exclusiva de pruebas |
| Build Tailwind/esbuild | No ejecutado: reescribe assets y la auditoría prohíbe cambios; además procesos bloqueados | Compilar en copia aislada del mismo commit, comparar artefactos y conservar licencias |
| npm audit / árbol transitivo | No ejecutado | Análisis de lockfile con herramienta disponible y fuentes de avisos; no instalar/actualizar como parte de auditoría |
| status/SHOW CREATE/FKs/datos reales | No ejecutado: sin CLI/cliente SQL permitido disponible | Sesión SQL de solo lectura autorizada, valores privados, consultas F |
| SQL integración/concurrencia | No ejecutadas: no tocar DB_* operativa ni asumir que una base es descartable | Identidad TEST_DB_* propia, sufijo _test, privilegios mínimos, inventario y autorización exacta del ejecutor antes de escribir |
| HTTP autenticado / roles / CSRF | No ejecutado para no alterar sesiones/auditoría comercial mediante cuentas operativas y sin navegador disponible | Usuarios de prueba en staging; pruebas negativas por ruta, operación, ID y sucursal |
| Responsive/modales/foco/teclado | No verificable: host de navegador y pipe nativo no disponibles | Navegador en 360/768/1440/2560, casos llenos/vacíos/error, reduced motion y teclado; no basarse solo en capturas viejas |
| XLSX/PDF renderizados con datos actuales | No ejecutado; no hubo exportación autenticada ni herramientas de render | Base aislada y exportaciones 1/muchas filas, acentos, textos largos y límites; abrir en lector real y validar paginación |
| Backups/restore/cron | No ejecutado | Restauración en destino distinto, inventario antes/después, logs de job y retención; nunca restaurar sobre operación |
| Carga y rendimiento | No ejecutado para no afectar servicio | Dataset representativo en copia aislada; medir p95/p99, locks, EXPLAIN y memoria de exportaciones |
| cPanel/Apache/TLS/FTP | No verificable: no se inspeccionó hosting | Publicar staging con public/ como raíz, HTTPS/cookies/cabeceras y rutas privadas verificadas, extensiones y permisos reales |

Los scripts de fixtures y test:browser:returns / test:browser:m12:notifications / test:browser:m13:reports invocan preparación de datos o archivos; no deben ejecutarse contra desarrollo/producción suponiendo que son de solo lectura. Los comandos mysql-*-integration pueden aplicar y revertir migraciones en su base exclusiva. La seguridad del destino se valida antes, no a partir del nombre amigable del script.

### Escenarios mínimos de regresión para cerrar

1. Recepción: variante inactiva, unidad decimal inválida, aviso cambiado, sin consentimiento, honeypot, clave repetida con mismo/diferente contenido, rate limit y caída antes/después del commit.
2. Seguimiento: dos vendedores toman al mismo tiempo, permisos view/assign/edit/history distintos, cambio concurrente con lock_version, sustitución sin conformidad, recepción web restringida y documento legacy.
3. Cotización: sin precio/tasa/política, aprobación invalidada al cambiar partidas, nueva versión sin sobrescribir emitida, vigencia vencida, datos de cliente cambiados y envío de versión exacta.
4. Inventario: última unidad entre dos aceptaciones, apartados previos, conversión no representable, catálogo >500, corrección/merma limitada, desactivación de producto/sucursal y activación modular.
5. Logística: traslado parcial con cotización, recepción simultánea, cancelación concurrente con salida y aceptación, entrega de apartado local/trasladado, confirmación parcial e incidencia/reintento.
6. Devoluciones: sucesivas/parciales, cantidad ya comprometida, daño/inspección, origen ajeno, devolución concurrente sobre última unidad, reenvío, rechazo/cancelación y fallo de auditoría.
7. M13: alta/actualización/varias variantes, cambio de catálogo después de preview, fallos poscommit, historial; reportes con solicitudes sin documento, antigüedad/atención y mezcla de unidades; 10000/10001 filas.
8. M12/jobs: revocación de destinatario, duplicado concurrente, rollback del evento, worker muerto, lease vencido, fencing, agotamiento y recuperación sin doble efecto.

## H. Pendientes reales del proyecto

Se derivan del requisito confirmado, implementación inspeccionada o necesidad de verificar el despliegue existente. No autorizan desarrollo durante esta auditoría.

| Grupo | Pendiente real | Hallazgos |
|---|---|---|
| Seguridad coherente | Política de historial/asignación y enforcement por módulo/sucursal | AY-05/06/07 |
| Recepción comercial | Aviso y datos reales, sucursal/catálogo habilitados, lista entre búsquedas y catálogo completo | AY-01/02/03 |
| Cotización integrada | Resolver cobertura por transferencia en flujo unificado y verificar toda la cadena | AY-04/14 |
| Inventario | Mínimos por ID; conteos y trazados conforme a operación que el negocio mantenga en alcance | AY-08/15 |
| Sucursales | Excepciones/servicios administrables, mapas/horarios públicos y auditoría atómica | AY-20/21 |
| Intercambio | Importación acordada con actualización/historial y confirmación auditada; códigos de barras operativos | AY-09/10/26 |
| Notificaciones/contacto | Orígenes internos faltantes; resolver aplazamiento o implementación SMTP/recuperación | AY-11/12 |
| Reportes | Universo/tiempos, vendedor, movimientos/importaciones/auditoría y límites/unidades correctos | AY-16/17/18/19 |
| Operación | Respaldo/restore/retención, diagnóstico, hosting/cron y documentación exacta | AY-13/14/22/25 |
| Ajustes funcionales menores | Comparación correcta de costos y contador de campañas | AY-23/24 |
| Información del negocio | Datos oficiales, Excel real, impuestos/listas/descuentos, legales, identidad, dominio y proveedor mapas/SMTP | requirements.md, Pendientes de información; L |

No se exige cargar promociones o testimonios para que una solicitud funcione; sí evitar publicar ejemplos como hechos reales. No se crean teléfonos, precios, saldos, clientes o tasas ficticios para «completar» el sistema.

## I. Mejoras opcionales

**Opcional — Fuera de alcance, requieren autorización independiente:** portal de clientes; pagos/POS/facturación fiscal/contabilidad; órdenes de compra; cuentas por cobrar/pagar; WhatsApp Business API; GPS continuo; firma digital; mantenimiento de flotilla; pasillo/anaquel o almacén independiente; cuarentena con saldo propio; notificaciones push/WebSockets o aplicaciones móviles nativas.

No se incluyen como condiciones obligatorias del cierre. SMTP de cotizaciones, recuperación por correo, avisos internos y respaldos ya aparecen en requisitos y se tratan en H, no como propuestas opcionales. Un cambio explícito de alcance puede aplazarlos para un lanzamiento limitado, pero no los convierte retroactivamente en funcionalidades entregadas.

## J. Plan de cierre

Orden propuesto por dependencias. No es un calendario ni una autorización para ejecutar cambios. En cada paso se conserva historial, no se hacen rollbacks destructivos de comercio existente y se separan entorno operativo y prueba.

| Orden | Trabajo | Depende de | Criterio verificable de aceptación |
|---|---|---|---|
| 1 | Obtener snapshot del commit, schema y configuración efectiva; recuperar herramientas PHP/SQL/navegador en entorno aislado (AY-14) | Infraestructura de pruebas y acceso de solo lectura al estado actual | 74 IDs conciliados o diferencias explicadas; cuenta de pruebas comprobada; lint/base de suites ejecutables sin tocar DB operativa |
| 2 | Cerrar matriz de permisos e integración modular (AY-05/06/07) | 1; decisión sobre historial/toma inicial | POST e IDs directos denegados por rol/sucursal/módulo con cero efectos; historial no excede permiso |
| 3 | Acordar datos/políticas reales y límites del lanzamiento | 1; responsable del negocio | Privacidad/condiciones, catálogo, sucursales, impuestos/listas y tratamiento de lotes/series/SMTP firmados; sin datos inventados |
| 4 | Corregir mínimos y completar o acotar explícitamente inventario avanzado (AY-08/15) | 2–3 | >500 variantes; conteo y trazabilidad elegidos operativos o exclusión/bloqueo coherente; saldos conciliados |
| 5 | Cerrar flujo cotización–cobertura–apartado–entrega–devolución (AY-04/14) | 2–4; precios/clientes | Caso sin stock local y con origen disponible completado con vínculo; parciales/reenvíos/cancelaciones/concurrencia sin doble efecto |
| 6 | Completar catálogo/lista pública y sucursales; cargar precondiciones reales (AY-01/02/03/20) | 3–5 | Solicitud multibúsqueda completa con folio/consentimiento; horarios/mapa/filtros y disponible de sucursal coherentes |
| 7 | Cerrar importación, auditoría y códigos/costos (AY-09/10/21/23/26) | 2–4; formato real de catálogo | Altas/actualizaciones y lotes trazables, fallos atómicos o resultado recuperable; costo comparable y recorrido de código comprobados |
| 8 | Completar avisos acordados y decidir/ejecutar SMTP y recuperación (AY-11/12) | 2, 5–7; destinatarios/proveedor | Avisos deduplicados por sucursal; entrega/fallo de correo y recuperación probados, o aplazamiento de lanzamiento explícito |
| 9 | Corregir y completar reportes (AY-16/17/18/19) | 5–8 y datos controlados | Cada reporte comprometido concilia con las operaciones; fechas/tiempos/unidades y límites correctos en pantalla/XLSX/PDF |
| 10 | Ajustes UI y verificación responsive (AY-24 más todos los flujos) | 6–9 | 360/768/1440/2560 sin acciones inaccesibles, tablas legibles, modales con foco/teclado, campos y anchos uniformes; pruebas autenticadas reales |
| 11 | Cerrar operación y guía vigente (AY-13/22/25) | 1–10; hosting y responsables | Restauración DB+medios verificada, retención acreditada, cron/diagnóstico, errores correlacionables y guía reproducible |
| 12 | Ensayo de despliegue y aceptación operativa | Todos los anteriores o aplazamientos firmados | Staging cPanel/Apache con HTTPS/public/, pruebas SQL+HTTP mismo commit, ruta de restauración y aceptación del negocio. Solo entonces autorizar producción |

Un lanzamiento limitado puede excluir expresamente una capacidad no necesaria para el inicio. Debe tener procedimiento, límites y pruebas propios; no sirve para declarar terminado el alcance original completo ni para omitir seguridad, integridad, recepción útil o capacidad de recuperación.

## K. Preparación para producción

| Condición | Estado actual acreditado | Condición de cierre |
|---|---|---|
| PHP puro, MVC y assets locales | Cumplida por código inspeccionado | Mantener arquitectura al corregir |
| public/ aislado | Cumplida en sondas locales; hosting NV | Confirmar document root Apache y denegación de privados en destino |
| Servicio HTTP y rutas básicas | Cumplida localmente | Repetir en staging Apache, HTTPS y dominio |
| Login/roles/CSRF backend | Implementados, verificación parcial | Matriz autenticada y AY-05/06/07 resueltos |
| Recepción principal utilizable | No cumplida localmente | AY-01–03 y prueba comercial controlada |
| Flujo comercial con faltantes y logística | No acreditado; inconsistencia AY-04 | Prueba integral de G y decisión de cobertura coherente |
| Integridad y esquema vivos | No verificable | Inspección SQL F y suites con datos/concurrencia |
| Catálogo/stock/precio/configuración reales | No acreditados; Home sin activos | Datos autorizados, inventario inicial conciliado y política fiscal |
| Alcance inventario/reportes/importación/avisos | Parcial o defectuoso | H completo o aplazamientos explícitos con límites operativos |
| Privacidad/condiciones e identidad | Privacidad actual 404; resto no certificado | Documentos aprobados versionados, datos/contactos oficiales |
| SMTP/recuperación | Pendientes documentados | Implementación verificada o decisión formal de lanzamiento limitado |
| Dependencias | Lockfile conocido; auditoría transitiva pendiente | Análisis actual y mitigación por uso/versión comprobada |
| Responsive/accesibilidad operativa | No verificable actualmente | Revisión navegador y dispositivos con datos reales/controlados |
| Assets del mismo commit | Entregados por HTTP; build actual no verificado | Compilación y comparación en copia aislada, licencias conservadas |
| Backups y restauración | No acreditados para despliegue vigente | Prueba de recuperación, retención y responsable operativo |
| Logs, cron y alertas operativas | Infraestructura parcial, operación externa NV | Errores rastreables, ejecuciones y seguimiento de fallos |
| HTTPS/cookies/secretos/permisos hosting | No verificable | Certificado y redirección, Secure, storage privado/escribible sin 777, DB mínima |
| Guía de despliegue | Contradicciones AY-25 | Instalación limpia reproducible por tercero |

El dictamen no afirma que exista ya un despliegue de producción defectuoso: no se inspeccionó un dominio productivo. Evalúa que **este código y el entorno observado no permiten aprobar la salida completa**.

## L. Preguntas y decisiones pendientes

1. ¿Cuál es el commit y el ambiente que se propone liberar? ¿La base local migrada es una instalación nueva o contiene operación real que debe preservarse? Esto determina el tipo de conciliación, no autoriza eliminar nada.
2. ¿Qué capacidades se mantendrán en el primer lanzamiento y cuáles se aplazarán formalmente? Especialmente SMTP/recuperación, trazabilidad por lote/serie, conteos y actualizaciones por Excel. El alcance original sigue siendo la referencia hasta documentar un cambio.
3. ¿La verificación debe aceptar cobertura autorizada de otra sucursal antes de emitir? ¿Se permite trasladar relacionado a un borrador o se exige abastecer primero mediante traslado genérico? Resolver la circularidad AY-04 sin inventar disponibilidad.
4. ¿quotes.history gobierna también eventos y notas de seguimiento? ¿Qué permiso gobierna historial de recepción sin documento? ¿Tomar la primera atención y reasignarla son permisos distintos? Alinear UI/backend y casos legacy.
5. ¿Desactivar un módulo impide nuevas escrituras privadas además de publicación, manteniendo históricos? El texto actual indica habilitación global/por sucursal; concretar transición y excepciones operativas antes de cerrar AY-07.
6. ¿Qué significa «pedido» para el negocio: solicitud, cotización aceptada o documento independiente? ¿«Almacén» equivale a sucursal? No se añadirá un módulo sin requisito confirmado.
7. ¿Cuál será la plantilla real de intercambio y las reglas de actualización por SKU/varias variantes? ¿Se conserva Excel XLSX o se autoriza explícitamente CSV como formato definitivo?
8. ¿Qué usuarios reciben cada notificación pendiente y qué datos mínimos pueden ver? La elección no se deduce de que sean gerentes o globales.
9. ¿Qué criterio fiscal, precios con/sin impuesto, listas y límites de descuento se aprueban? ¿Cuál es el costo de proveedor comparable entre unidades y fechas?
10. ¿Qué hora/fecha y universo definen recepción, primera atención y cierre? Hay datos UTC en persistencia y presentación America/Hermosillo; no debe medirse desde crear el documento si se quiere tiempo desde recepción.
11. ¿Quién aporta y aprueba legales, identidad, contactos, coordenadas, catálogo y saldos iniciales? ¿Qué evidencia demostrará consentimiento y publicación real?
12. ¿Qué mecanismo de hosting o aplicación cumplirá retención diaria/mensual, copia privada, restauración y supervisión? ¿Quién recibe y atiende los fallos? No se asume que la cola haga respaldos.
13. ¿Qué modelo de lector y formato de etiqueta se usarán para los códigos de barras ya comprometidos? No se prescribe adquisición de equipo sin información.

### Respuestas finales solicitadas

1. **Estado real:** sistema con implementación considerable y controles estructurales, pero alcance parcial, defectos puntuales y verificación operativa incompleta.
2. **Módulos terminados y verificados:** ninguno de los módulos empresariales completos puede certificarse así en esta sesión. Sí se verificaron rutas/destinos, denegación anónima, HTTP básico, aislamiento local y entrega de assets en el alcance descrito en G.
3. **Funciones incompletas:** catálogo/lista públicos, operaciones avanzadas M05, excepciones/servicios/mapa público, importación/actualización/historial, avisos M12, reportes comprometidos, SMTP/recuperación y cierre M14.
4. **Procesos sin cierre acreditado:** recepción pública local está indisponible; lista cruzando búsquedas está incompleta; cobertura vinculada con faltantes tiene condiciones incompatibles; no se ejecutaron cadenas autenticadas completas.
5. **Errores/inconsistencias:** 26 hallazgos: 13 P1, 12 P2 y 1 P3; incluyen estado de preparación, pendientes y riesgos de evidencia, no solo bugs. Sin P0 demostrado. Cada uno conserva naturaleza, impacto, evidencia y validación en C.
6. **Falta para completar alcance original:** cerrar H con pruebas y decisiones L; no añadir POS, contabilidad, compras por orden, portal o integraciones fuera de alcance.
7. **Módulos que requieren corrección/integración:** seguimiento M08 con M09/M05, permisos/activación, M01, M05, M12, M13 y operación/auditoría; el resto necesita validación actual antes de declararse terminado.
8. **Pruebas pendientes:** PHP/lint, SQL/schema/grants/datos, permisos autenticados, concurrencia, exportaciones/importación real, responsive, rendimiento, backup/restore y staging Apache del mismo commit.
9. **Riesgos que impiden aprobar producción:** objetivo público actualmente no operativo, alcance crítico parcial, autorización inconsistente, resultado poscommit ambiguo y ausencia de evidencia vigente de integridad/recuperación/hosting.
10. **Orden exacto recomendado:** los doce pasos de J, primero entorno verificable y seguridad, después datos/inventario y flujo comercial, luego público/intercambio/avisos/reportes, finalmente UX, operación y ensayo de despliegue.

**¿FERRETERÍA AYALA ESTÁ LISTO PARA OPERAR EN PRODUCCIÓN? — NO.**

Para aprobar el alcance completo faltan, como mínimo: recepción pública real utilizable con legales/datos autorizados; cierre de autorización y activación; flujo coherente de cotización/faltantes/apartado/entrega; correcciones y pendientes funcionales detallados; pruebas actuales del esquema y procesos sobre base aislada; restauración/retención acreditadas y despliegue seguro validado. Un lanzamiento reducido requiere aceptación explícita del negocio, límites operativos y sus propias pruebas; este informe no lo autoriza ni lo certifica.
