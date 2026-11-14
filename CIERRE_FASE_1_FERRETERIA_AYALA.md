# Cierre de Fase 1 — Ferretería Ayala

Fecha de corte: 13 de noviembre de 2026.

## Estado final

**Fase parcialmente completada y validada hasta donde permiten la configuración y el entorno actuales.** Se corrigieron los defectos determinables de AY-02, AY-03, AY-05 y AY-06, y se incorporó enforcement de disponibilidad configurada para las escrituras centrales M08, M09 y M10 de AY-07. AY-01 quedó diagnosticado contra la base principal y está bloqueado por configuración/datos faltantes. AY-04 requiere una decisión empresarial que el código y los requisitos actuales no resuelven.

Con autorización expresa del propietario se leyó únicamente la configuración necesaria de `.env`. No se imprimieron ni copiaron secretos. La base principal se inspeccionó dentro de una transacción de sólo lectura y no recibió escrituras, migraciones ni fixtures. Las escrituras de prueba se limitaron a bases aisladas con identidad y nombre de prueba verificados.

## Diagnóstico inicial

- El repositorio se encontraba limpio en `main`, alineado con `origin/main` antes de esta intervención.
- PHP CLI 8.5.4, Node 22.22.1 y npm 9.2.0 están disponibles.
- PHP dispone de PDO, `pdo_mysql` y `mysqli`.
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

Estado: **confirmado y bloqueado por decisión empresarial; no cerrado**.

La circularidad permanece: el seguimiento exige existencia local para verificar y emitir, mientras la transferencia relacionada sólo acepta una versión emitida `sent` o `accepted`. No se retiró ninguna validación de inventario y no se alteraron apartados, movimientos ni coberturas.

Alternativas que requieren autorización:

1. **Transferencia vinculada antes de emitir.** Permitir que un borrador o seguimiento inmutable solicite cobertura; definir en qué estado la cobertura cuenta para verificación (solicitada, aprobada, despachada o recibida), cómo se invalida al cambiar partidas y cómo se convierte en apartado al aceptar. Mantiene trazabilidad comercial, pero exige ampliar el contrato M08/M09 y sus pruebas concurrentes.
2. **Abastecimiento previo mediante transferencia genérica.** Recibir físicamente en destino y sólo después verificar/emitir. Reutiliza el flujo actual y conserva disponibilidad local estricta, pero la transferencia no queda vinculada desde el inicio a la necesidad comercial y requiere un procedimiento operativo externo.

No debe contarse mercancía solicitada o en tránsito como disponible sin una regla aprobada. Tampoco debe apartarse simultáneamente en origen y destino.

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
- `app/Application/Services/QuoteWorkflowService.php`
- `app/Application/Services/TransferManagementService.php`
- `app/Application/Services/DeliveryManagementService.php`
- `app/Controllers/Admin/QuoteWorkflowsController.php`
- `app/Controllers/Public/CmsController.php`
- `app/Core/AppKernel.php`
- `app/Data/CmsHomeData.php`
- `app/Views/components/public/product-card.php`
- `app/Views/public/home.php`
- `app/Views/public/cms/request.php`
- `resources/js/app.js`
- `resources/js/components/interactions.js`
- `resources/js/components/public-quote-request.js`
- `public/assets/js/app.js` (generado por compilación)
- `tests/phase1-central-flow.php`
- `tests/mysql-quote-workflow-integration.php`
- `tests/admin-browser.mjs`
- `tests/run.php`
- `CIERRE_FASE_1_FERRETERIA_AYALA.md`

## Pruebas ejecutadas

| Verificación | Resultado | Alcance |
|---|---|---|
| `php -l` sobre todos los archivos PHP de aplicación y pruebas | PASS | Sintaxis de aplicación, configuración, vistas y pruebas |
| `php tests/run.php` | PASS: 1585 | Aplicación pública y contratos M00–M13, incluidos los 9 controles nuevos de Fase 1 |
| `npm run build` | PASS | Tailwind 4 y esbuild; bundle público regenerado |
| Suite SQL M00 (`tests/mysql-integration.php`) | PASS | Migraciones, bloqueo, estado, duplicados y rollback en base aislada |
| Suite SQL M02 (`tests/mysql-m02-integration.php`) | PASS | Administrador inicial, login, sesión, autorización, alcance, revocación y auditoría |
| Suite HTTP/navegador M02 (`tests/mysql-m02-http-integration.php`) | PASS | Flujos autenticados, escrituras, CSRF, autorización, revocación, auditoría, panel responsive y limpieza aislada |
| Rutas públicas `/` y `/solicitar-cotizacion` | HTTP 200 | La solicitud muestra correctamente que la recepción no está disponible con la configuración actual |
| Inspección de base principal | PASS, sólo lectura | Estado real de migraciones, módulos, legales, catálogo y sucursales receptoras; cero escrituras |
| Recuperación de base aislada tras intento central | PASS | Se validó identidad de prueba y se restauró a inventario vacío |
| `git diff --check` | PASS | Sin errores de espacios o marcadores de conflicto |

## Pruebas no ejecutadas

- La suite SQL de seguimiento central (`tests/mysql-quote-workflow-integration.php`) no puede completar su fixture porque PHP GD no está instalado (`imagecreatetruecolor` no existe). No se instalaron paquetes del sistema sin autorización.
- Las configuraciones aisladas M03, M05 y M07 están completas y autorizadas, pero rechazaron la conexión antes de cualquier migración o escritura.
- M01 está configurado, pero su identidad no cumple el contrato de autorización aislada; no se ejecutó. M04, M06, M08, M09, M10 y M12 no tienen configuración aislada completa.
- Los casos SQL nuevos exactos de AY-06 y AY-07 quedan pendientes de GD y de una base central aislada funcional. No se presentan como exitosos.
- No se hizo una matriz visual completa a 360/768/1440/2560 del catálogo publicado porque la base principal no tiene catálogo publicable. El panel autenticado de M02 sí pasó su recorrido responsive automatizado.

## Regresiones revisadas

- Las consultas históricas permanecen disponibles aunque un módulo esté deshabilitado.
- Las rutas mutadoras conservan permisos, CSRF de controlador, alcance de sucursal y transacciones existentes.
- No se modificaron reglas de disponibilidad, apartados, consumos, movimientos o inventario negativo.
- Los enlaces publicados de producto siguen llevando al formulario; el código de lista demo no intercepta el modo publicado.
- Todo contenido dinámico nuevo de vistas usa `e()`.
- La selección temporal sólo contiene cantidades por ID y el servidor vuelve a validar cada partida.

## Riesgos y decisiones pendientes

1. Autorizar una de las políticas de AY-04.
2. Definir el permiso para historia de solicitudes sin documento.
3. Confirmar si la ausencia de un registro de módulo debe significar “legado permitido” o “denegado por defecto” después de una migración controlada.
4. Habilitar PHP GD y completar/corregir las conexiones de bases aisladas para ejecutar las suites SQL centrales sin tocar la base principal.
5. Cargar y aprobar privacidad, catálogo, M08 y sucursales receptoras para habilitar AY-01 sin inventar datos.
6. Decidir paginación y filtros por atributos para catálogos públicos mayores a 100 productos.

## Criterio de cierre

No se declara cerrada la Fase 1. El código determinable quedó corregido, compila y supera 1585 controles; M00 y M02 también superaron integración real aislada. Faltan la decisión de cobertura AY-04, la política de historial inicial, configurar AY-01 y ejecutar los casos SQL centrales exactos de AY-06/AY-07 en un entorno con GD. Ningún resultado no ejecutado se presenta como exitoso.
