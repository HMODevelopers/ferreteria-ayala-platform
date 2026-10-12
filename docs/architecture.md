# Arquitectura

## Flujo de peticiones

Apache dirige las peticiones al front controller `public/index.php`, salvo assets permitidos. `bootstrap/app.php` registra autoload equivalente PSR-4, configura zona horaria y crea el router. Request captura método y ruta; el router resuelve controlador/acción; HomeController recibe DemoHomeData, renderiza Home dentro de layout y devuelve Response.

Response centraliza código, cabeceras de seguridad, HTML y redirecciones locales. HEAD utiliza GET sin cuerpo. Las rutas no encontradas producen 404; métodos no implementados para una ruta existente producen 405. No hay resolución dinámica de archivos o clases a partir de parámetros públicos.

View acepta rutas internas limitadas, mantiene buffers limpios incluso ante errores y pasa variables explícitas con EXTR_SKIP. `e()` escapa texto/atributos. El HTML de componentes y `$content` proviene exclusivamente de nuestras vistas; no admite HTML comercial sin sanitización futura.

El router del servidor PHP solo permite servir extensiones de assets bajo `public/assets`, comprobando el camino real. Las reglas Apache limitan archivos directos; la protección principal es el document root correcto. Nunca arrancar `php -S` desde la raíz sin `-t public public/router.php`.

Las excepciones se registran en `storage/logs/app.log`; no se muestran trazas al visitante. La respuesta 500 es independiente de los datos comerciales. APP_ENV es configuración descriptiva: no activa depuración visible. No hay carga automática de `.env`, sesiones, API ni conexiones SQL.

## Responsabilidades

| Carpeta | Responsabilidad |
| --- | --- |
| Core | HTTP, rutas, renderizado, escape |
| Controllers | Coordinación de petición y proveedor de contenido |
| Data | Datos y contratos de demostración |
| Views | Composición semántica y componentes con datos explícitos |
| resources | Fuentes CSS/JS compilables |
| public | Front controller y assets distribuibles |
| storage | Logs privados, no contenido público |

Solo se crean carpetas nuevas cuando una fase real las necesita. Un proveedor de contenido de MySQL podrá reemplazar DemoHomeData conservando los contratos; extraer una interfaz de repositorio al incorporar ese segundo proveedor, no antes. Services atenderá operaciones comerciales; Models/Repositories encapsulará persistencia PDO; Middleware añadirá controles al existir rutas protegidas.

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

No existe todavía esquema SQL. Antes de diseñarlo se deben confirmar impuestos, unidades, variantes, stock por sucursal, precios, flujos de cotización y permisos.

## Seguridad futura

PDO y consultas preparadas; validación de entradas; sesiones seguras, CSRF en escrituras, permisos por acción, límites de solicitudes, validación/reprocesamiento de imágenes y auditoría. Se implementarán junto con módulos reales y pruebas específicas. La CSP actual permite únicamente recursos locales; revisar cualquier nueva integración sin relajarla globalmente.

## Referencias técnicas

- [Tailwind CLI](https://tailwindcss.com/docs/installation/tailwind-cli): compilación a CSS estático y `@source` para PHP/JS.
- [Swiper API](https://swiperjs.com/swiper-api): navegación, pagination y A11y; aquí no se usa autoplay para evitar movimiento automático.
