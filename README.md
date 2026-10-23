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
php -S 127.0.0.1:8080 -t public public/router.php
```

Abre **http://127.0.0.1:8080**. También puedes ejecutar `npm run serve`.

PHP lee configuración con `getenv()`. Los comandos CLI privados cargan el archivo
`.env` local mediante un parser de datos que no evalúa shell; las variables ya
exportadas tienen precedencia. Las integraciones fuerzan `APP_ENV=testing` solo
en su proceso. La aplicación web no carga `.env`; en cPanel se definen las
variables de producción en el entorno del sitio.

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
reversión. Antes del primer `down()`, valida integralmente las precondiciones de
todas las migraciones del lote; cada `down()` vuelve a comprobar su protección
inmediatamente antes del DDL. Fuera de `development` y `testing`, las dos
operaciones exigen `--force`. MySQL/MariaDB puede confirmar DDL implícitamente:
el preflight evita reversiones parciales previsibles por datos existentes, pero
una carrera, caída o error DDL posterior todavía puede dejar cambios parciales.
Ante un error se debe inspeccionar el esquema antes de reintentar.

Los archivos privados de migración viven en `database/migrations/`, se nombran
`YYYYMMDDHHMMSS_nombre_descriptivo.php` y devuelven una instancia de
`App\Core\Persistence\Migration`. M00 administra la tabla técnica
`schema_migrations`. M01.1 define `branches`, `modules` y `branch_modules`;
M01.2 añade `branch_hours` y `branch_special_hours`; M01.3 incorpora el catálogo
`services` y su configuración `branch_services`. Una integración M01 fallida
las dejó aplicadas temporalmente en la base exclusiva de pruebas; ese estado se
respaldó, verificó y recuperó antes de repetir la suite con éxito. Los códigos conservan
unicidad ASCII sin distinguir mayúsculas y sus `CHECK` fuerzan mayúsculas
mediante comparación binaria. Sus rollbacks rechazan tablas con registros antes
de ejecutar un `DROP TABLE`.

M01.4 incorpora contratos de repositorio, adaptadores PDO y servicios de
aplicación internos para sucursales, módulos, horarios y servicios comerciales.
Las escrituras no están expuestas por HTTP. Los horarios se modifican dentro de
una transacción que bloquea primero la sucursal con `SELECT ... FOR UPDATE`,
relee y valida el calendario, y solo entonces ejecuta el DML. La primera
ejecución real inicial falló por herencia de PDO al bifurcar. El estado fallido
se respaldó, restauró en un destino temporal, recuperó de forma controlada y la
suite se repitió satisfactoriamente con procesos independientes.

En otra terminal, `npm run dev` observa cambios en CSS y JavaScript. PHP se recarga al actualizar el navegador. Tras cambiar fuentes o dependencias, reinicia el observador. Usa `npm run build` antes de subir los archivos: compila y minimiza Tailwind, empaqueta los ES Modules/Swiper y copia fuentes y licencias a `public/assets/`.

Los assets compilados se incluyen en Git para poder desplegar por FTP sin npm. Nunca uses el servidor integrado PHP en producción.

## Estructura

```text
app/
  Application/            Contratos, lecturas y servicios de aplicación internos
  Core/                   HTTP, vistas y núcleo de persistencia PDO
  Controllers/Public/     HomeController
  Data/                   DemoHomeData: todos los contratos y ejemplos comerciales
  Domain/                 Reglas y objetos mínimos de sucursales, módulos y servicios
  Infrastructure/         Adaptadores PDO de los repositorios
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
- Horarios regulares y excepcionales M01.2, incluidos intervalos divididos,
  cruces de medianoche, cierres por fecha y evaluación en `America/Hermosillo`.
- Catálogo técnico M01.3 de servicios comerciales, configuración independiente
  por sucursal, orden determinista y disponibilidad separada de módulos y horarios.
- Repositorios y servicios internos M01.4 con lecturas acotadas, activación
  lógica, disponibilidad efectiva y coordinación transaccional de horarios.
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
# Con una base exclusiva autorizada; el CLI limita APP_ENV=testing a su proceso:
npm run test:mysql
# Sólo después de que M00 pase y con autorización separada para M01:
npm run test:mysql:m01
# Sólo ante una recuperación expresamente autorizada:
npm run inspect:mysql:m01-recovery
npm run recover:mysql:m01-recovery
# Con el servidor iniciado y Chrome instalado:
CHROME_PATH=/ruta/al/chrome npm run test:browser
```

Las pruebas de navegador usan por defecto `/opt/google/chrome/chrome`. Las capturas quedan en `tests/artifacts/` (ignoradas por Git). No es necesario instalar un navegador adicional si Chrome está disponible.

La integración exige `TEST_DB_*` configuradas, una base vacía cuyo nombre termine
en `_test` y una cuenta no administrativa limitada a esa base. Nunca reutiliza
`DB_*` ni crea o elimina bases. Antes de escribir comprueba identidad efectiva,
privilegios e inventario de tablas, vistas, triggers, eventos y rutinas. Solo
retira `schema_migrations` si conserva la exclusión y puede demostrar que esa
ejecución creó la tabla y que no contiene registros ajenos. Obtén autorización
antes de ejecutarla contra un servidor compartido.

`TEST_DB_USERNAME_M00`/`TEST_DB_PASSWORD_M00` identifican M00 y
`TEST_DB_USERNAME_M01`/`TEST_DB_PASSWORD_M01` identifican M01. La cuenta de
integración M00 debe tener únicamente `SELECT`, `INSERT`, `DELETE`,
`CREATE`, `DROP`, `TRIGGER`, `EVENT` y `EXECUTE` sobre la base de pruebas, sin
`GRANT OPTION` ni privilegios globales distintos de `USAGE`. M01 usa un ejecutor
independiente y exige además `UPDATE`; requiere `M01_TEST_AUTHORIZATION` igual
al nombre exacto de la base. Los tres permisos de metadatos permiten demostrar
que no existen objetos ocultos antes de probar. Consulta el
[punto de control M00 + M01](docs/integration-m00-m01.md) antes de autorizar.

La recuperación de una integración fallida usa un ejecutor independiente con
modos `inspect` y `recover`. El segundo permanece deshabilitado por defecto,
exige dos confirmaciones exactas, constancia administrativa de que no quedan
sesiones anteriores y prueba de un respaldo externo restaurado de forma
independiente. No usa `DROP DATABASE`, `TRUNCATE`, desactivación de FKs ni una
cuenta administrativa. El DML se revierte como transacción ante errores; el DDL
posterior conserva la limitación de commits implícitos de MariaDB/MySQL.

Las pruebas aisladas cubren M00 y M01.1–M01.4, incluida la inspección del SQL
mediante dobles de PDO. M00 y M01 pasan contra MariaDB 10.11; M01 demuestra con
conexiones independientes la serialización, timeout, deadlock y rollback. La
base exclusiva queda vacía y el esquema permanente de desarrollo conserva las
siete migraciones sin semillas. El futuro sistema de medios permanece reservado para M11.

Consulta [resultados ejecutados](docs/qa.md), [diseño y maqueta visual](docs/design-system.md), [arquitectura](docs/architecture.md), [catálogo de módulos](docs/modules.md), [requisitos funcionales](docs/requirements.md), [modelo de datos](docs/data-model.md), [despliegue cPanel](docs/deployment-cpanel.md), [roadmap](docs/roadmap.md) y [procedencia de assets](docs/assets.md).

## Revisión para aprobar el diseño

1. En escritorio: jerarquía del hero, contraste, marcas, densidad del catálogo y banners.
2. En 360/390 px: encabezado, menú con teclado, legibilidad, cuadrícula y áreas táctiles.
3. Buscar “taladro”, probar filtros y un término sin resultados.
4. Agregar productos, cambiar cantidades, descargar la lista y verificar el aviso de demo.
5. Cambiar campañas con flechas, indicadores y arrastre táctil.
6. Revisar todos los textos y la claridad de los datos pendientes/demostrativos.

La Home fue aprobada como base el 17 de octubre de 2026. Las siguientes mejoras visuales son integrar el logotipo oficial, reemplazar ilustraciones por fotos homogéneas de productos reales y sustituir fotografía de stock por obras/sucursales autorizadas. La persistencia y el administrador deben implementarse por incrementos conforme a los requisitos aprobados.
