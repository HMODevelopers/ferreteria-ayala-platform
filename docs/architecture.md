# Arquitectura

## Flujo de peticiones

Apache dirige las peticiones al front controller `public/index.php`, salvo assets permitidos. `bootstrap/app.php` registra autoload equivalente PSR-4, configura zona horaria y crea el router. Request captura método y ruta; el router resuelve controlador/acción; HomeController recibe DemoHomeData, renderiza Home dentro de layout y devuelve Response.

Response centraliza código, cabeceras de seguridad, HTML y redirecciones locales. HEAD utiliza GET sin cuerpo. Las rutas no encontradas producen 404; métodos no implementados para una ruta existente producen 405. No hay resolución dinámica de archivos o clases a partir de parámetros públicos.

View acepta rutas internas limitadas, mantiene buffers limpios incluso ante errores y pasa variables explícitas con EXTR_SKIP. `e()` escapa texto/atributos. El HTML de componentes y `$content` proviene exclusivamente de nuestras vistas; no admite HTML comercial sin sanitización futura.

El router del servidor PHP solo permite servir extensiones de assets bajo `public/assets`, comprobando el camino real. Las reglas Apache limitan archivos directos; la protección principal es el document root correcto. Nunca arrancar `php -S` desde la raíz sin `-t public public/router.php`.

Las excepciones HTTP se registran en `storage/logs/app.log`; no se muestran trazas al visitante. La respuesta 500 es independiente de los datos comerciales. `APP_ENV` y `APP_DEBUG` son configuración descriptiva: no activan trazas visibles. La aplicación web consume variables mediante `getenv()` y no carga `.env`. Los comandos CLI usan `bootstrap/cli.php` y un parser de datos sin evaluación de shell; respetan variables ya exportadas y las integraciones fuerzan `testing` solo durante su proceso. Las sesiones se inician únicamente al visitar rutas administrativas y se guardan en `storage/sessions`, fuera del document root.

## Seguridad y administración M02

Las rutas `/admin/*` usan controladores explícitos y métodos GET/POST
registrados. `SessionManager` configura cookies HttpOnly, SameSite Lax/Strict,
Secure bajo HTTPS o por configuración, modo estricto y almacenamiento privado.
El identificador se regenera al autenticar. La sesión PHP guarda únicamente el
id del usuario, un token aleatorio y CSRF; `user_sessions` conserva solo el hash
del token y permite expiración, revocación y cierre al desactivar o cambiar una
contraseña.

`AuthenticationService` devuelve el mismo error para identidad inexistente,
contraseña incorrecta o cuenta inactiva. Los rechazos se auditan con huellas
SHA-256 separadas del dato original y cinco fallos en quince minutos bloquean
temporalmente por identidad o IP. Las contraseñas usan Argon2id cuando PHP lo
ofrece y se rehashean después de una autenticación válida.

`PdoAccessRepository::hasPermission()` consulta las asignaciones vigentes en
cada petición. Una asignación global puede operar todas las sucursales; una
asignación local requiere coincidencia de `user_roles.branch_id` y
`user_branches`. El sistema deniega por defecto, impide conceder roles o
permisos superiores a los propios y protege al último Administrador global
activo. Los cambios de roles tienen efecto en la petición siguiente y los
cambios de asignaciones revocan sesiones.

Las escrituras sensibles agregan auditoría dentro de la misma transacción. Los
rechazos de login, CSRF y autorización se registran como eventos controlados.
No existe endpoint para modificar `audit_logs`.

## Persistencia M00

`DatabaseConfig` valida el resultado de `config/database.php`; `Connection`
comprueba `pdo_mysql` y crea una única instancia PDO solo al llamar `pdo()`.
La Home no construye estas clases y continúa con `DemoHomeData`. Las opciones
obligatorias activan excepciones, desactivan consultas preparadas emuladas y
devuelven arrays asociativos; el DSN usa `utf8mb4` y un timeout acotado.

`MigrationDiscovery` carga definiciones privadas desde `database/migrations/`
en orden determinista. `Migrator` compara esas definiciones con
`schema_migrations`, asigna lotes y serializa `migrate`/`rollback` mediante un
bloqueo asesor ligado a la misma conexión. El índice primario sigue siendo la
protección final contra registros duplicados. El bloqueo se libera aun cuando
la operación falla.

El registro ocurre después de `up()` y se elimina después de `down()`. Toda
migración reversible declara también un preflight. `Migrator` ejecuta los
preflights del último lote completo, en orden de reversión, antes del primer
`down()`; el propio `down()` repite la misma guarda justo antes del DDL. Como el
DDL de MySQL/MariaDB puede causar commits implícitos, una carrera, caída o fallo
posterior todavía puede dejar un esquema parcial; el comando se detiene y exige
inspección en vez de afirmar atomicidad.

Antes de avanzar, `migrate` y `rollback` validan que cada registro conserve su
archivo, identificador, nombre y secuencia de lote, y rechazan migraciones
pendientes anteriores al último identificador aplicado. La tabla técnica se
crea de forma atómica sin usar `IF NOT EXISTS` como evidencia de propiedad.

M01.1 reutiliza esta infraestructura con tres definiciones ordenadas para
`branches`, `modules` y `branch_modules`. `branches.code` y `modules.code`
conservan colación y unicidad ASCII existentes; sus `CHECK` comparan mediante
`CAST(... AS BINARY)` para exigir mayúsculas. Sus preflights y cierres `down()`
consultan si la tabla contiene registros y rechazan una eliminación con datos.
Una ejecución inicial fallida dejó el esquema de pruebas preservado. Se verificó
un dump mediante restauración aislada, se recuperó el estado sintético exacto y
la suite M01 completa pasó posteriormente en MariaDB 10.11.

M01.2 agrega dos definiciones ordenadas para `branch_hours` y
`branch_special_hours`, también con rollbacks que se niegan a eliminar datos.
Las restricciones SQL protegen cada fila, claves, duplicados e integridad
referencial; las reglas que comparan varias filas viven además en el dominio y
se ejecutan bajo una escritura transaccional en M01.4.

M01.3 agrega `services` y `branch_services` mediante dos definiciones ordenadas.
La clave compuesta impide repetir un servicio en una sucursal, las referencias
usan `RESTRICT` y ambos estados nacen deshabilitados. `services.code` aplica la
misma comparación binaria de mayúsculas sin alterar su índice único. Los
rollbacks conservan el patrón de negarse a eliminar tablas con registros. M01.4
añade consumidores PDO sin alterar la conexión diferida.

Los contratos viven en `Application/Repositories`, los adaptadores en
`Infrastructure/Persistence` y los coordinadores en `Application/Services`.
Construir un adaptador no solicita `pdo()`. Todas las consultas son preparadas;
la rehidratación vuelve a aplicar invariantes de dominio y los errores de
duplicado o restricción se traducen sin filtrar detalles del controlador.
`Page` limita listados generales a 100 filas y conserva desempates estables.
La recarga integral necesaria para validar escrituras de horarios especiales
tiene un máximo explícito de 10 000 filas; superar el límite cancela y revierte
la operación en vez de validar un calendario parcial.

`Connection` implementa `TransactionManager`. El servicio que inicia una
operación de varios pasos es dueño de commit/rollback; ningún repositorio
confirma transacciones. Para horarios, el coordinador inicia la transacción,
bloquea primero `branches.id` con `SELECT ... FOR UPDATE`, relee el calendario,
crea un `BranchSchedule` y ejecuta el DML. El orden siempre comienza por la
sucursal. Así se evita decidir sobre una lectura consistente antigua bajo el
aislamiento habitual `REPEATABLE READ` de InnoDB, siempre que todos los escritores
usen el mismo protocolo. No hay reintentos automáticos de deadlocks o timeouts.
Las pruebas aisladas verifican el orden y rollback. La integración real demuestra
serialización, timeout y deadlock con conexiones independientes.

La integración real añade una exclusión propia, valida la identidad efectiva y
los privilegios limitados de la cuenta, exige inventario vacío de tablas,
vistas, triggers, eventos y rutinas, y conserva cualquier objeto si no puede
demostrar que fue creado por la ejecución actual y que sigue siendo seguro
retirarlo. Sus pruebas de concurrencia usan un proceso PHP aislado iniciado
antes de abrir la conexión PDO principal; ningún trabajador hereda la sesión
que mantiene el bloqueo asesor de integración.

## Responsabilidades

| Carpeta | Responsabilidad |
| --- | --- |
| Core | HTTP, rutas, renderizado, escape y persistencia técnica |
| Controllers | Coordinación de petición y proveedor de contenido |
| Data | Datos y contratos de demostración |
| Domain | Objetos y reglas puras de negocio sin acceso a PDO |
| Application | Casos de uso internos, contratos de repositorio y lecturas |
| Infrastructure | Implementaciones PDO de los contratos de persistencia |
| Views | Composición semántica y componentes con datos explícitos |
| resources | Fuentes CSS/JS compilables |
| public | Front controller y assets distribuibles |
| storage | Logs privados, no contenido público |
| database/migrations | Definiciones privadas de migración |
| bin | Entradas CLI privadas |

Solo se crean carpetas nuevas cuando una fase real las necesita. M01.1 añade
`Domain/Branches` y `Domain/Modules`: `Branch` valida y normaliza datos sin
persistirlos; `Module` mantiene sus indicadores; `ModuleAvailability` evalúa
existencia, habilitación global, contexto/estado de sucursal y relación por
sucursal. Un proveedor de contenido de MySQL podrá reemplazar DemoHomeData
conservando los contratos. Middleware se añadirá al existir rutas protegidas.

M01.2 amplía `Domain/Branches` con intervalos regulares, excepciones por fecha,
un calendario validado, un resultado que separa estado programado y operativo,
y `BranchScheduleService`. El servicio convierte cada instante con
`DateTimeImmutable` y `DateTimeZone('America/Hermosillo')`, usa intervalos
semiabiertos `[apertura, cierre)` y no consulta PDO. Una excepción controla su
día civil completo: reemplaza el horario regular y suprime arrastres del día
anterior; si no existe excepción, sí se evalúa el intervalo nocturno originado
el día previo. Un intervalo especial nocturno continúa al día siguiente salvo
que ese siguiente día tenga su propia excepción.

M01.3 añade `Domain/Services`. `Service` normaliza y valida el catálogo sin
persistirlo; `BranchServiceConfiguration` valida identificadores, habilitación y
orden con desempate estable; `ServiceAvailability` exige servicio activo,
sucursal activa y relación habilitada. Esta regla no consulta ni combina el
calendario de M01.2. Los identificadores numéricos permiten relacionar medios en
M11 sin acoplar ahora el dominio a almacenamiento de archivos.

M01.4 conserva esa separación. `BranchManagementService` coordina sucursales;
`ModuleAvailabilityService` reutiliza `ModuleAvailability`;
`BranchServiceManagementService` mantiene catálogo/relaciones y reutiliza
`ServiceAvailability`; `BranchScheduleManagementService` reutiliza
`BranchSchedule` y `BranchScheduleService`. Ninguno conoce Request, Response,
sesiones, cookies o vistas.

Sus contratos internos devuelven entidades de dominio, `Page` para listados que
pueden crecer, listas ordenadas y `BranchScheduleStatus`, que separa apertura
programada, activación operativa y disponibilidad pública. No existe API pública
ni AyalaTable en este incremento.

## Catálogo administrativo M03

`Domain/Catalog` valida claves, nombres, SKU, códigos, precisión y banderas sin
conocer PDO. `CatalogRepository` y `PdoCatalogRepository` concentran consultas
preparadas, límites, filtros y rehidratación. `CatalogManagementService`
coordina transacciones, ciclos de categorías, códigos estables, precisión de
unidades asociadas y pertenencia de variantes antes de auditar el cambio.

Las rutas `/admin/products`, `/admin/categories`, `/admin/brands`,
`/admin/units` y `/admin/attributes` reutilizan sesión, autorización, CSRF,
auditoría, layout y navegación M02. Los listados se paginan en servidor y las
altas nacen inactivas. M03 no consulta ni persiste precios o inventario. Las
imágenes permanecen en la frontera de M11 para evitar un gestor duplicado.

## Precios e impuestos M04

`Domain/Pricing` implementa validación y operaciones decimales mediante cadenas
de dígitos; no convierte importes o tasas a `float`. `PricingRepository` y
`PdoPricingRepository` concentran consultas efectivas, historial y bloqueos.
`PricingManagementService` coordina transacciones, versiones optimistas,
vigencias, precedencia de listas y auditoría atómica.

Las rutas `/admin/prices`, `/admin/prices/calculate` y `/admin/taxes` reutilizan
sesión, permisos por sucursal/globales y CSRF de M02. Los precios pertenecen a
variante, sucursal y lista. Una lista comercial puede heredar el precio público
de la misma sucursal aplicando su descuento general; una excepción explícita
prevalece. El calculador redondea subtotal e impuesto a dos decimales con mitad
hacia arriba. M04 no almacena cotizaciones ni inventario.

## Contratos actuales

Todos se devuelven desde `DemoHomeData::all()`. Identificadores únicos y slugs estables. Vistas de contenido comercial reciben arrays; no conectan a SQL. Campos `null` indican datos no confirmados y deben tener presentación honesta.

| Entidad | Campos |
| --- | --- |
| Site | name, tagline, phone?, email?, socials[], demo_notice |
| Navigation | label, href, filter? (slug de categoría) |
| Banner | id, title, subtitle, image_desktop, image_mobile, button, href, type, status, priority, starts_at?, ends_at?, alignment, contrast, eyebrow |
| Category | id (slug), name, description, image |
| Product | id, sku, name, category, category_id, brand?, unit, image, price?, badge?, published |
| Brand | Nombre tipográfico provisional; al administrar será id, name, logo?, authorization |
| Promotion | id, title, description, image, label, href, starts_at?, ends_at?, branch_id?, category_id?, status |
| Event | id, title, description, image, date?, location, type, status |
| Review | name, rating, comment, date?, verified, status |
| Branch | id, name, address?, phone?, hours?, map_url?, image? |

En esta fase el hero usa alineación izquierda y contraste oscuro; la administración de variantes, vigencias y estados se implementará después. Los campos modelan el contrato, no prometen ya un programador de campañas. Todos los registros son de demo, publicados explícitamente desde el proveedor, sin fechas comerciales ficticias. La lista JS solo usa id, nombre, SKU, unidad y cantidad; no tiene precio ni información personal.

## Entidades futuras y relaciones conceptuales

- Producto pertenece a categoría y opcionalmente marca; unidades y presentaciones deberán levantarse con el negocio. Categorías pueden requerir jerarquía.
- Banner y promoción podrán relacionarse con categoría, sucursales y vigencia. Relación muchos-a-muchos con sucursales si la operación lo requiere.
- Evento pertenece a una sucursal o múltiples ubicaciones según requisitos. Reseña requiere fuente, consentimiento, publicación y verificación real.
- Cotización tendrá partidas, cantidades, cliente, sucursal, estado y snapshot de información comercial. No usar los datos demo como inventario.
- Usuarios, roles y permisos controlarán módulos administrativos; auditoría relacionará actor, acción, entidad y momento.
- Configuración y páginas mantendrán contenido institucional y canales verificados.

El esquema M01 y M02 aprobado está aplicado en desarrollo con quince registros
en `schema_migrations`; las siete tablas M01 permanecen vacías y M02 conserva
únicamente el catálogo inicial, un Administrador global y su auditoría. La base
exclusiva de pruebas quedó sin objetos después de las integraciones MariaDB y
HTTP. El levantamiento
funcional y las entidades propuestas están documentados en
[requisitos](requirements.md) y [modelo de datos](data-model.md). Antes de
cargar información real, el negocio debe entregar los catálogos y valores
concretos de impuestos, unidades, límites de descuento, productos, precios,
existencias y sucursales.

## Escrituras de inventario M05

`InventoryManagementService` valida cantidad, precisión, conversión, tipo,
motivo e idempotencia. Dentro de la transacción bloquea sucursal y variante,
materializa la fila única de saldo, la bloquea y registra saldo y movimiento
juntos. Una clave repetida devuelve el movimiento existente; si los datos
difieren se rechaza. La existencia inicial es explícita y única. No hay SQL en
controladores o vistas ni reintentos automáticos inciertos.

Las rutas `/admin/inventory`, `/admin/inventory/movements` y
`/admin/inventory/adjustments` reutilizan sesión M02, CSRF, permisos por
sucursal y auditoría. Filtros y paginación se resuelven en servidor. M04 y M05
permanecen independientes.

## Proveedores y recepciones M06

`Domain/Purchasing` valida identificadores, documentos, fechas, cantidades e
importes con `Decimal`. `PurchasingRepository` y `PdoPurchasingRepository`
concentran listados, bloqueos y escrituras preparadas. El documento conserva
estados `draft`, `pending`, `partial`, `received` y `cancelled`; solo el borrador
admite cambios y confirmarlo no afecta saldos.

`PurchasingManagementService::receive()` es dueño de la transacción exterior:
bloquea primero factura y partidas en orden estable, relee pendientes y crea la
recepción. Después llama al adaptador composable de M05, que no inicia otra
transacción, bloquea sucursal/variante/saldo y registra un `adjustment_in` con
origen `goods_receipt`. Partida de recepción, progreso facturado, saldo,
movimiento y auditoría confirman o revierten juntos. La clave única y un hash
del pedido hacen idempotente el reenvío; el bloqueo impide sobre-recepción
concurrente.

Las rutas `/admin/suppliers`, `/admin/purchases` y
`/admin/purchases/receipts/create` reutilizan sesión, CSRF, permisos M02,
alcance por sucursal y escape de salida. M06 no administra archivos, precios de
venta, pagos, órdenes de compra ni validación SAT.

## Seguridad pendiente para módulos futuros

M02 ya aporta consultas preparadas, validación, sesiones seguras, CSRF,
permisos, límite de login y auditoría. M03 reutiliza estas barreras; M04–M14 deberán reutilizar estos
contratos. La validación/reprocesamiento de imágenes y los límites de
formularios públicos llegarán con sus módulos. La CSP actual permite únicamente
recursos locales; revisar cualquier integración sin relajarla globalmente.

## Referencias técnicas

- [Tailwind CLI](https://tailwindcss.com/docs/installation/tailwind-cli): compilación a CSS estático y `@source` para PHP/JS.
- [Swiper API](https://swiperjs.com/swiper-api): navegación, pagination y A11y; aquí no se usa autoplay para evitar movimiento automático.
