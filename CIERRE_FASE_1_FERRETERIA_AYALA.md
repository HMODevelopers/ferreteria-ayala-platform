# Cierre de Fase 1 — Ferretería Ayala

Fecha de corte: 14 de noviembre de 2026.

## Estado final

**Fase parcialmente completada y validada hasta donde permiten la configuración y el entorno actuales.** Se corrigieron los defectos determinables de AY-02, AY-03, AY-05 y AY-06, se incorporó enforcement de disponibilidad configurada para las escrituras centrales M08, M09 y M10 de AY-07 y se implementó en código la decisión empresarial de AY-04. AY-01 continúa bloqueado por configuración/datos faltantes. AY-04 no se declara validado en SQL real hasta ejecutar la suite central en una base aislada autorizada.

Con autorización expresa del propietario se leyó únicamente la configuración necesaria de `.env`. No se imprimieron ni copiaron secretos. La base principal se inspeccionó dentro de una transacción de sólo lectura y no recibió escrituras, migraciones ni fixtures. Las escrituras de prueba se limitaron a bases aisladas con identidad y nombre de prueba verificados.

## Diagnóstico inicial

- El repositorio se encontraba limpio en `main`, alineado con `origin/main` antes de esta intervención.
- PHP CLI 8.5.4, Node 22.22.1 y npm 9.2.0 están disponibles.
- PHP dispone de PDO, `pdo_mysql` y `mysqli`.
- PHP CLI no dispone de GD; `imagecreatetruecolor` no existe.
- En esta continuación no hay ejecutable Docker disponible.
- `.env` conserva credenciales de la base principal, pero no contiene identidades aisladas completas para M08, M09 o M10. No se reutilizó la identidad principal para pruebas.
- Google Chrome está disponible para pruebas posteriores.
- No se localizó cliente `mysql` ni Composer en PATH. El proyecto no depende de Composer para su arquitectura actual.
- Se localizaron 401 archivos PHP en `app`, `config`, `bootstrap` y `tests`, además de suites unitarias, SQL, HTTP y navegador.
- `bootstrap/app.php` carga `.env` explícitamente. Sólo se utilizó después de la autorización del propietario.
- La configuración principal no está marcada como entorno de pruebas. Por ello se mantuvo estrictamente en modo lectura.

## Hallazgos trabajados

### AY-01 — Recepción pública

Estado: **diagnosticado; bloqueado por configuración/datos y no cerrado**.

El código exige aviso de privacidad publicado, M11 global habilitado, M08 habilitado globalmente y para una sucursal activa. La inspección de sólo lectura confirmó 74 migraciones, 103 tablas y cuatro sucursales activas, pero ningún producto o variante activos, ningún aviso de privacidad publicado y ninguna sucursal receptora. Entre M08, M09, M10 y M11 sólo existe M11, habilitado globalmente y no acotado por sucursal. Por tanto, la recepción pública no puede operar correctamente con la configuración real actual. No se inventaron legales, catálogo, módulos, sucursales ni datos empresariales.

### AY-02 — Catálogo y filtros públicos

Estado: **corregido en código; contrato automatizado y rutas públicas verificados**.

Causa confirmada: las tarjetas publicadas omitían `data-product`, por lo que `interactions()` terminaba antes de registrar búsqueda y filtros. Además, la consulta se truncaba a seis categorías y seis productos.

Correcciones:

- Las tarjetas publicadas ahora participan en búsqueda y filtrado por categoría.
- La lógica de lista demostrativa sólo se activa cuando existe su diálogo; el sitio publicado conserva sus enlaces reales de solicitud.
- El estado de resultados distingue productos publicados de productos de referencia.
- La consulta publicada admite hasta 100 categorías y 100 productos por carga, y la búsqueda de servidor sigue permitiendo localizar coincidencias fuera de la carga inicial.

Riesgo restante: no se implementó paginación pública ni filtros por atributos porque exceden el defecto confirmado y requieren definir UX y política de publicación. Con catálogos mayores a 100 productos debe usarse la búsqueda; una página de catálogo paginada sigue siendo trabajo posterior.

### AY-03 — Selección entre búsquedas

Estado: **corregido en código; contrato de persistencia automatizado**.

Causa confirmada: cada GET generaba una clave de operación nueva y la interfaz sólo enviaba cantidades visibles en la búsqueda actual.

Correcciones:

- La clave de operación se conserva durante búsquedas válidas de la misma sesión.
- IDs y cantidades seleccionadas se conservan en `sessionStorage`; no se guardan allí nombre, teléfono, correo ni comentario.
- Antes del envío se agregan las cantidades seleccionadas en búsquedas anteriores.
- El backend conserva la validación autoritativa de variante activa, unidad, precisión, límite de 100 partidas, consentimiento, sucursal, módulos, idempotencia y aviso legal.
- La selección temporal se elimina del navegador después de una recepción exitosa.

### AY-04 — Cotización y transferencia con faltante

Estado: **decisión empresarial implementada en código; validación SQL central pendiente y por ello no cerrado**.

Se adoptó la transferencia vinculada a una cotización antes de su emisión. La implementación mantiene separados tres conceptos: existencia física, disponible local y cobertura comercial aprobada.

Reglas aplicadas:

- El formulario de transferencias admite el borrador actual `preparing`/`pending_approval`, además de la versión emitida actual `sent`/`accepted`.
- Cada transferencia vinculada conserva un snapshot JSON inmutable, su hash verificable y la cantidad cotizada por partida.
- Una solicitud `requested` no cuenta como cobertura. Sólo la cantidad aprobada y aún no recibida de una cobertura vigente puede completar el disponible local para verificar, emitir y aceptar.
- Aprobar conserva la regla M09: valida el faltante real, bloquea cotización/sucursales/saldos y aparta exclusivamente en origen.
- La cobertura nunca incrementa existencia física ni disponible en destino. La entrada física sólo ocurre en `receive`.
- La aceptación aparta en destino únicamente la parte local; la cobertura pendiente permanece apartada en origen. Al recibir después de aceptar, M09 crea la entrada física y el apartado de destino en la misma transacción.
- Una partida recibida es la única parte transferida elegible para entrega. La mercancía solicitada, aprobada o en tránsito no se entrega.
- Cambiar variante, unidad o cantidad invalida la cobertura de la partida afectada, conserva snapshot/eventos y libera sólo lo aprobado que aún no fue despachado. Nunca revierte una salida ni libera mercancía físicamente en tránsito.
- Cancelar, rechazar, vencer o revisar la cotización invalida cobertura vigente. La cancelación de una cotización aceptada libera apartados locales y recibidos no consumidos; los consumos y movimientos físicos permanecen.
- Las transferencias genéricas sin cotización conservan su flujo anterior.
- `operation_key`, hash de petición, `lock_version`, transacciones y el orden cotización → sucursales → variantes/saldos continúan siendo las barreras de idempotencia y concurrencia.

La migración aditiva `20261113090000_add_quote_transfer_coverage` eleva el catálogo de código a 75 migraciones. Agrega snapshot, cobertura y eventos por partida, además de `source_released_quantity` para impedir liberaciones dobles. No se aplicó a la base principal.

### AY-05 — Historial de seguimiento

Estado: **defecto confirmado corregido; política de solicitud sin documento pendiente**.

Los eventos de un seguimiento vinculado a una cotización ahora se entregan sólo con `quotes.history` en la sucursal correspondiente, independientemente de que el usuario posea `quotes.view`. La restricción se aplica antes de consultar los eventos.

Para una solicitud pública que aún no tiene cotización se mantiene `quote_requests.view`, porque no existe `quote_requests.history` y el informe exige no inventar ese permiso. El negocio debe decidir si crea un permiso histórico dedicado o si el permiso de lectura incluye esos eventos iniciales.

### AY-06 — Acción `take`

Estado: **corregido en código; caso SQL negativo incorporado, ejecución central pendiente por GD**.

El servicio ahora exige `quotes.assign` para toda toma inicial y reasignación, alineado con la interfaz. Cuando existe solicitud pública también exige `quote_requests.assign`. La reasignación conserva la exigencia adicional de un motivo; tomar por primera vez no inventa esa obligación.

La suite SQL central incluye ahora el caso de un rol con lectura/creación pero sin asignación. Ese caso no llegó a ejecutarse porque el fixture compartido requiere la extensión PHP GD, ausente en este entorno. La matriz general de permisos M02 sí pasó en SQL y HTTP autenticado.

### AY-07 — Disponibilidad modular

Estado: **corregido para el flujo central configurado; caso SQL incorporado y no cerrado globalmente**.

Se añadió un guard reutilizable para operaciones nuevas. Cuando el módulo está registrado:

- M08 bloquea guardar o accionar cotizaciones y mutar su seguimiento si está deshabilitado globalmente, para la sucursal o si la sucursal está inactiva.
- M09 bloquea crear o accionar transferencias si origen o destino no tienen el módulo disponible.
- M10 bloquea crear/accionar entregas y administrar sus recursos si el módulo no está disponible.
- Las lecturas e históricos no se bloquean.
- Un reintento idempotente ya confirmado puede recuperar el resultado histórico antes del guard, sin crear efectos nuevos.
- Instalaciones heredadas sin registro del módulo conservan su comportamiento; la ausencia de configuración no se interpreta retroactivamente como una desactivación.

El resto de módulos privados no se modificó por control estricto de alcance. Para cerrar AY-07 globalmente será necesario inventariar y aplicar el mismo contrato a cada módulo aprobado.

La suite SQL central contiene un caso que deshabilita M08 para la sucursal y exige rechazo sin crear evento. Su ejecución quedó bloqueada antes de llegar al caso por la ausencia de GD. La base aislada utilizada en ese intento fue restaurada a inventario vacío después de validar exactamente sus migraciones y objetos.

## Archivos modificados

- `app/Application/Services/ModuleOperationGuard.php`
- `app/Application/Services/QuoteManagementService.php`
- `app/Application/Services/QuoteReservationService.php`
- `app/Application/Services/QuoteWorkflowService.php`
- `app/Application/Services/TransferManagementService.php`
- `app/Application/Repositories/TransferRepository.php`
- `app/Infrastructure/Persistence/PdoTransferRepository.php`
- `app/Application/Services/DeliveryManagementService.php`
- `app/Controllers/Admin/QuotesController.php`
- `app/Controllers/Admin/TransfersController.php`
- `app/Controllers/Admin/QuoteWorkflowsController.php`
- `app/Controllers/Public/CmsController.php`
- `app/Core/AppKernel.php`
- `app/Data/CmsHomeData.php`
- `app/Views/components/public/product-card.php`
- `app/Views/public/home.php`
- `app/Views/public/cms/request.php`
- `app/Views/admin/quotes/detail.php`
- `app/Views/admin/transfers/create.php`
- `app/Views/admin/transfers/detail.php`
- `database/migrations/20261113090000_add_quote_transfer_coverage.php`
- `resources/js/app.js`
- `resources/js/components/interactions.js`
- `resources/js/components/public-quote-request.js`
- `public/assets/js/app.js` (generado por compilación)
- `tests/phase1-central-flow.php`
- `tests/mysql-quote-workflow-integration.php`
- `tests/mysql-m08-integration.php`
- `tests/mysql-m12-jobs-integration.php`
- `tests/mysql-m12-notifications-integration.php`
- `tests/mysql-returns-integration.php`
- `tests/admin-browser.mjs`
- `tests/run.php`
- `docs/integration-m08.md`
- `docs/integration-m09.md`
- `docs/integration-m10.md`
- `docs/integration-returns.md`
- `docs/quote-workflow.md`
- `docs/roadmap.md`
- `package.json`
- `CIERRE_FASE_1_FERRETERIA_AYALA.md`

## Pruebas ejecutadas

| Verificación | Resultado | Alcance |
|---|---|---|
| `php -l` sobre todos los archivos PHP de aplicación y pruebas | PASS | Sintaxis de aplicación, configuración, vistas y pruebas |
| `php tests/run.php` | PASS: 1591 | Aplicación pública y contratos M00–M13, incluidos los 15 controles nuevos de Fase 1 |
| `npm run build` | PASS | Tailwind 4 y esbuild; bundle público regenerado |
| Descubrimiento de migraciones | PASS | 75 migraciones ordenadas; AY-04 es la última migración aditiva |
| Suite SQL M00 (`tests/mysql-integration.php`) | PASS | Migraciones, bloqueo, estado, duplicados y rollback en base aislada |
| Suite SQL M02 (`tests/mysql-m02-integration.php`) | PASS | Administrador inicial, login, sesión, autorización, alcance, revocación y auditoría |
| Suite HTTP/navegador M02 (`tests/mysql-m02-http-integration.php`) | PASS | Flujos autenticados, escrituras, CSRF, autorización, revocación, auditoría, panel responsive y limpieza aislada |
| Rutas públicas `/` y `/solicitar-cotizacion` | HTTP 200 | La solicitud muestra correctamente que la recepción no está disponible con la configuración actual |
| Inspección de base principal | PASS, sólo lectura | Estado real de migraciones, módulos, legales, catálogo y sucursales receptoras; cero escrituras |
| Recuperación de base aislada tras intento central | PASS | Se validó identidad de prueba y se restauró a inventario vacío |
| `git diff --check` | PASS | Sin errores de espacios o marcadores de conflicto |

## Pruebas no ejecutadas

- La suite SQL de seguimiento central (`tests/mysql-quote-workflow-integration.php`) no puede completar su fixture porque PHP GD no está instalado (`imagecreatetruecolor` no existe). No se instalaron paquetes del sistema sin autorización.
- La suite central contiene los casos AY-04 de solicitud sin aprobación, aprobación/apartado, snapshot, reintento idempotente, recepción parcial, bloqueo de entrega en tránsito, entrega parcial recibida, cancelación, cambio de partidas, preservación de movimiento físico y transferencia genérica. Esos casos quedaron incorporados pero **no ejecutados**.
- Las configuraciones aisladas M03, M05 y M07 están completas y autorizadas, pero rechazaron la conexión antes de cualquier migración o escritura.
- M01 está configurado, pero su identidad no cumple el contrato de autorización aislada; no se ejecutó. M04, M06, M08, M09, M10 y M12 no tienen configuración aislada completa.
- Los casos SQL nuevos exactos de AY-04, AY-06 y AY-07 quedan pendientes de GD y de una base central aislada funcional. No se presentan como exitosos.
- No se hizo una matriz visual completa a 360/768/1440/2560 del catálogo publicado porque la base principal no tiene catálogo publicable. El panel autenticado de M02 sí pasó su recorrido responsive automatizado.

## Regresiones revisadas

- Las consultas históricas permanecen disponibles aunque un módulo esté deshabilitado.
- Las rutas mutadoras conservan permisos, CSRF de controlador, alcance de sucursal y transacciones existentes.
- AY-04 amplía deliberadamente la regla de verificación/emisión a `disponible local + cobertura aprobada vigente`; no confunde esa cobertura con existencia física ni disponible local.
- Los apartados de origen, destino y consumos de entrega permanecen separados y trazables; ninguna invalidación revierte movimientos físicos.
- Los enlaces publicados de producto siguen llevando al formulario; el código de lista demo no intercepta el modo publicado.
- Todo contenido dinámico nuevo de vistas usa `e()`.
- La selección temporal sólo contiene cantidades por ID y el servidor vuelve a validar cada partida.

## Riesgos y decisiones pendientes

1. Ejecutar la suite SQL central AY-04/AY-06/AY-07 con PHP GD y credenciales aisladas completas; incluir las carreras de última unidad ya existentes en M09.
2. Definir el permiso para historia de solicitudes sin documento.
3. Confirmar si la ausencia de un registro de módulo debe significar “legado permitido” o “denegado por defecto” después de una migración controlada.
4. Cargar y aprobar privacidad, catálogo, M08 y sucursales receptoras para habilitar AY-01 sin inventar datos.
5. Decidir paginación y filtros por atributos para catálogos públicos mayores a 100 productos.

## Criterio de cierre

No se declara cerrada la Fase 1. AY-04 quedó implementado y la regresión local compila y supera 1591 controles; M00 y M02 ya habían superado integración real aislada. Faltan validar AY-04 en SQL/concurrencia real, resolver la política de historial inicial, configurar AY-01 y ejecutar los casos centrales exactos de AY-06/AY-07 en un entorno con GD y base aislada autorizada. Ningún resultado no ejecutado se presenta como exitoso.
