# Ferretería Ayala · Plataforma

Home comercial y base modular en PHP puro, con arquitectura MVC mínima y assets locales. Identidad naranja, negro y blanco; catálogo y campañas demostrativos. No requiere Node.js en producción.

## Requisitos

- PHP **8.2+**, extensiones estándar `filter` y `PDO`; `pdo_mysql` es obligatorio para usar la persistencia y los comandos de migración.
- MariaDB 10.11 o MySQL compatible, con una base y un usuario de privilegios mínimos creados fuera de la aplicación.
- Node.js 22+ y npm para compilar en desarrollo.
- Apache 2.4, `mod_rewrite`, permiso `AllowOverride` y document root en `public/` para producción.
- Chrome para las pruebas opcionales de navegador.

## Iniciar localmente

```bash
npm ci
npm run build
cp .env.example .env
set -a
. ./.env
set +a
php -S 127.0.0.1:8080 -t public public/router.php
```

Abre **http://127.0.0.1:8080**. También puedes ejecutar `npm run serve`.

PHP lee configuración del entorno con `getenv()`. El archivo `.env` local está
ignorado por Git y no se carga automáticamente; las instrucciones `set -a` y
`. ./.env` lo exportan de forma explícita en la terminal actual. En cPanel se
deben definir las variables de producción en el entorno del sitio.

## Persistencia y migraciones

La Home no solicita una conexión y continúa usando `DemoHomeData`. PDO se crea
de forma diferida únicamente cuando un consumidor de persistencia lo solicita.
Configura `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`,
`DB_CHARSET=utf8mb4`, `DB_CONNECT_TIMEOUT` y `DB_MIGRATION_LOCK_TIMEOUT` en el
entorno del proceso. No uses una cuenta `root` ni compartas usuarios entre
desarrollo, pruebas y producción.

Después de exportar las variables, los comandos privados son:

```bash
php bin/migrations.php status
php bin/migrations.php migrate
php bin/migrations.php rollback
```

`migrate` aplica pendientes en un lote nuevo; `rollback` revierte únicamente el
último lote y se niega a comenzar si alguna migración de ese lote no tiene
reversión. Fuera de `development` y `testing`, las dos operaciones exigen
`--force`. MySQL/MariaDB puede confirmar DDL implícitamente: ante un error se
debe inspeccionar el esquema antes de reintentar; el sistema no promete una
reversión atómica de DDL.

Los archivos privados de migración viven en `database/migrations/`, se nombran
`YYYYMMDDHHMMSS_nombre_descriptivo.php` y devuelven una instancia de
`App\Core\Persistence\Migration`. M00 administra la tabla técnica
`schema_migrations`. M01.1 ya define, sin haberlas aplicado a una base real,
las tablas `branches`, `modules` y `branch_modules`; sus rollbacks rechazan
tablas con registros antes de ejecutar un `DROP TABLE`.

En otra terminal, `npm run dev` observa cambios en CSS y JavaScript. PHP se recarga al actualizar el navegador. Tras cambiar fuentes o dependencias, reinicia el observador. Usa `npm run build` antes de subir los archivos: compila y minimiza Tailwind, empaqueta los ES Modules/Swiper y copia fuentes y licencias a `public/assets/`.

Los assets compilados se incluyen en Git para poder desplegar por FTP sin npm. Nunca uses el servidor integrado PHP en producción.

## Estructura

```text
app/
  Core/                   HTTP, vistas y núcleo de persistencia PDO
  Controllers/Public/     HomeController
  Data/                   DemoHomeData: todos los contratos y ejemplos comerciales
  Domain/                 Reglas y objetos mínimos de sucursales y módulos
  Views/
    layouts/              Layout público
    components/public/    Header, navegación, tarjetas, slider, diálogos y footer
    public/               Composición de la Home
    errors/               404/405 y 500
bootstrap/                Autoload equivalente PSR-4 y arranque
bin/                      Comandos CLI privados
config/                   Configuración y registro de rutas
database/migrations/      Migraciones privadas ordenadas por identificador
resources/                CSS Tailwind y JavaScript fuente
public/                   Único document root; front controller y assets compilados
scripts/                  Build local
storage/logs/             Errores privados
tests/                    Contratos PHP y pruebas reales de navegador
docs/                     Arquitectura, diseño, roadmap, despliegue, assets y QA
```

Las carpetas de modelos, servicios, middleware y base de datos se incorporarán cuando exista una implementación real que las necesite.

## Funcionalidades

- Hero Swiper con tres campañas, flechas, indicadores, gesto táctil y navegación manual (sin autoplay).
- Menú móvil y lista de materiales con `dialog` nativo, Escape, cierre exterior, foco y bloqueo de scroll.
- Buscador de catálogo local; acepta acentos, nombres, SKU y categoría. Filtros, conteo y estado vacío.
- Agregar materiales, ajustar cantidades de 1 a 999, quitar y descargar un TXT.
- Categorías, productos, marcas, banners secundarios, sección editorial, eventos, testimonios y cuatro sucursales.
- Contenido PHP visible sin JavaScript, imágenes locales responsive, fuentes locales, CSP y escape de salida.
- Conexión PDO diferida y migraciones CLI con lotes, rollback y bloqueo asesor.
- Esquema local M01.1 para sucursales y módulos, con validación de dominio y disponibilidad determinista.
- 404 real, 405 para métodos no implementados y soporte HEAD.

## Qué es demostrativo

El logo es **tipográfico provisional**. Los productos usan SVG de referencia creados para el prototipo, sin marca ni especificaciones comerciales confirmadas. No hay precios ni stock real. Fotografías de stock no representan instalaciones de Ayala. Campañas, eventos y testimonios están marcados como demo. Las sucursales conocidas tienen direcciones, teléfonos, horarios y fotos pendientes de confirmar. No hay redes ni enlaces de contacto inventados.

La lista solo vive en memoria en la pestaña y se pierde al recargar. Descargarla no equivale a solicitar una cotización. No recopila datos personales ni envía peticiones comerciales. La Home no consulta MySQL; no hay login, CRUD comercial, pagos ni administrador.

El prototipo usa `noindex, nofollow`; el dominio, URL absoluta Open Graph, SEO definitivo y aviso de privacidad se completarán antes de publicar.

## Pruebas

```bash
npm test
find app bootstrap config public tests -name '*.php' -exec php -l {} \;
npm run build
# Solo con una base exclusiva autorizada y APP_ENV=testing:
npm run test:mysql
# Con el servidor iniciado y Chrome instalado:
CHROME_PATH=/ruta/al/chrome npm run test:browser
```

Las pruebas de navegador usan por defecto `/opt/google/chrome/chrome`. Las capturas quedan en `tests/artifacts/` (ignoradas por Git). No es necesario instalar un navegador adicional si Chrome está disponible.

La integración exige `TEST_DB_*` explícitas, una base vacía cuyo nombre termine
en `_test` y una cuenta no administrativa limitada a esa base. Nunca reutiliza
`DB_*` ni crea o elimina bases. Antes de escribir comprueba identidad efectiva,
privilegios e inventario de tablas, vistas, triggers, eventos y rutinas. Solo
retira `schema_migrations` si conserva la exclusión y puede demostrar que esa
ejecución creó la tabla y que no contiene registros ajenos. Obtén autorización
antes de ejecutarla contra un servidor compartido.

La cuenta de integración debe tener únicamente `SELECT`, `INSERT`, `DELETE`,
`CREATE`, `DROP`, `TRIGGER`, `EVENT` y `EXECUTE` sobre la base de pruebas, sin
`GRANT OPTION` ni privilegios globales distintos de `USAGE`. Los tres permisos
de metadatos permiten demostrar que no existen objetos ocultos antes de probar.

Las pruebas aisladas cubren M00 y M01.1, incluida la inspección del SQL mediante
dobles de PDO. Esto no equivale a ejecutar las nuevas restricciones en
MariaDB/MySQL; esa integración sigue pendiente y requiere autorización.

Consulta [resultados ejecutados](docs/qa.md), [diseño y maqueta visual](docs/design-system.md), [arquitectura](docs/architecture.md), [catálogo de módulos](docs/modules.md), [requisitos funcionales](docs/requirements.md), [modelo de datos](docs/data-model.md), [despliegue cPanel](docs/deployment-cpanel.md), [roadmap](docs/roadmap.md) y [procedencia de assets](docs/assets.md).

## Revisión para aprobar el diseño

1. En escritorio: jerarquía del hero, contraste, marcas, densidad del catálogo y banners.
2. En 360/390 px: encabezado, menú con teclado, legibilidad, cuadrícula y áreas táctiles.
3. Buscar “taladro”, probar filtros y un término sin resultados.
4. Agregar productos, cambiar cantidades, descargar la lista y verificar el aviso de demo.
5. Cambiar campañas con flechas, indicadores y arrastre táctil.
6. Revisar todos los textos y la claridad de los datos pendientes/demostrativos.

La Home fue aprobada como base el 17 de octubre de 2026. Las siguientes mejoras visuales son integrar el logotipo oficial, reemplazar ilustraciones por fotos homogéneas de productos reales y sustituir fotografía de stock por obras/sucursales autorizadas. La persistencia y el administrador deben implementarse por incrementos conforme a los requisitos aprobados.
