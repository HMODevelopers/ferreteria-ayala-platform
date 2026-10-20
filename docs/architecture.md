# Arquitectura

## Flujo de peticiones

Apache dirige las peticiones al front controller `public/index.php`, salvo assets permitidos. `bootstrap/app.php` registra autoload equivalente PSR-4, configura zona horaria y crea el router. Request captura método y ruta; el router resuelve controlador/acción; HomeController recibe DemoHomeData, renderiza Home dentro de layout y devuelve Response.

Response centraliza código, cabeceras de seguridad, HTML y redirecciones locales. HEAD utiliza GET sin cuerpo. Las rutas no encontradas producen 404; métodos no implementados para una ruta existente producen 405. No hay resolución dinámica de archivos o clases a partir de parámetros públicos.

View acepta rutas internas limitadas, mantiene buffers limpios incluso ante errores y pasa variables explícitas con EXTR_SKIP. `e()` escapa texto/atributos. El HTML de componentes y `$content` proviene exclusivamente de nuestras vistas; no admite HTML comercial sin sanitización futura.

El router del servidor PHP solo permite servir extensiones de assets bajo `public/assets`, comprobando el camino real. Las reglas Apache limitan archivos directos; la protección principal es el document root correcto. Nunca arrancar `php -S` desde la raíz sin `-t public public/router.php`.

Las excepciones HTTP se registran en `storage/logs/app.log`; no se muestran trazas al visitante. La respuesta 500 es independiente de los datos comerciales. `APP_ENV` y `APP_DEBUG` son configuración descriptiva: no activan trazas visibles. La aplicación consume variables mediante `getenv()` y no carga `.env` automáticamente. El contrato de sesión permanece preparado pero no inicia sesiones.

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

El registro ocurre después de `up()` y se elimina después de `down()`. Como el
DDL de MySQL/MariaDB puede causar commits implícitos, un fallo puede dejar un
esquema parcial; el comando se detiene y exige inspección en vez de afirmar
atomicidad. No existen comandos destructivos ni migraciones comerciales.

Antes de avanzar, `migrate` y `rollback` validan que cada registro conserve su
archivo, identificador, nombre y secuencia de lote, y rechazan migraciones
pendientes anteriores al último identificador aplicado. La tabla técnica se
crea de forma atómica sin usar `IF NOT EXISTS` como evidencia de propiedad.

M01.1 reutiliza esta infraestructura con tres definiciones ordenadas para
`branches`, `modules` y `branch_modules`. Sus cierres `down()` consultan si la
tabla contiene registros y rechazan una eliminación con datos. Los archivos
están preparados, no ejecutados contra un motor real.

M01.2 agrega dos definiciones ordenadas para `branch_hours` y
`branch_special_hours`, también con rollbacks que se niegan a eliminar datos.
Las restricciones SQL protegen cada fila, claves, duplicados e integridad
referencial; las reglas que comparan varias filas viven además en el dominio y
se ejecutan bajo una escritura transaccional en M01.4.

M01.3 agrega `services` y `branch_services` mediante dos definiciones ordenadas.
La clave compuesta impide repetir un servicio en una sucursal, las referencias
usan `RESTRICT` y ambos estados nacen deshabilitados. Los rollbacks conservan el
patrón de negarse a eliminar tablas con registros. M01.4 añade consumidores PDO
sin alterar la conexión diferida.

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
Las pruebas aisladas verifican el orden y rollback; falta probar el motor real.

La integración real añade una exclusión propia, valida la identidad efectiva y
los privilegios limitados de la cuenta, exige inventario vacío de tablas,
vistas, triggers, eventos y rutinas, y conserva cualquier objeto si no puede
demostrar que fue creado por la ejecución actual y que sigue siendo seguro
retirarlo.

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

No existe todavía un esquema comercial aplicado. M00 solo ha permitido la tabla
técnica `schema_migrations`; M01.1, M01.2 y M01.3 aportan archivos locales pendientes de
integración real en una base exclusiva de pruebas. Existe el antecedente de la
inicialización técnica en desarrollo, que no cierra M00. El levantamiento
funcional y las entidades propuestas están documentados en
[requisitos](requirements.md) y [modelo de datos](data-model.md). Antes de
cargar información real, el negocio debe entregar los catálogos y valores
concretos de impuestos, unidades, límites de descuento, productos, precios,
existencias y sucursales.

## Seguridad futura

PDO y consultas preparadas; validación de entradas; sesiones seguras, CSRF en escrituras, permisos por acción, límites de solicitudes, validación/reprocesamiento de imágenes y auditoría. Se implementarán junto con módulos reales y pruebas específicas. La CSP actual permite únicamente recursos locales; revisar cualquier nueva integración sin relajarla globalmente.

## Referencias técnicas

- [Tailwind CLI](https://tailwindcss.com/docs/installation/tailwind-cli): compilación a CSS estático y `@source` para PHP/JS.
- [Swiper API](https://swiperjs.com/swiper-api): navegación, pagination y A11y; aquí no se usa autoplay para evitar movimiento automático.
