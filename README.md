# Ferretería Ayala · Plataforma pública

Primera etapa: una Home comercial en PHP puro, con arquitectura MVC mínima y assets locales. Identidad naranja, negro y blanco; catálogo y campañas demostrativos. No requiere Node.js en producción.

## Requisitos

- PHP **8.2+**, extensión estándar `filter` y funciones estándar de PHP. Esta fase no requiere PDO ni MySQL; las siguientes usarán `pdo_mysql`.
- Node.js 22+ y npm para compilar en desarrollo.
- Apache 2.4, `mod_rewrite`, permiso `AllowOverride` y document root en `public/` para producción.
- Chrome para las pruebas opcionales de navegador.

## Iniciar localmente

```bash
npm ci
npm run build
php -S 127.0.0.1:8080 -t public public/router.php
```

Abre **http://127.0.0.1:8080**. También puedes ejecutar `npm run serve`.

En otra terminal, `npm run dev` observa cambios en CSS y JavaScript. PHP se recarga al actualizar el navegador. Tras cambiar fuentes o dependencias, reinicia el observador. Usa `npm run build` antes de subir los archivos: compila y minimiza Tailwind, empaqueta los ES Modules/Swiper y copia fuentes y licencias a `public/assets/`.

Los assets compilados se incluyen en Git para poder desplegar por FTP sin npm. Nunca uses el servidor integrado PHP en producción.

## Estructura

```text
app/
  Core/                   Router, Request, Response, Controller, View y helpers
  Controllers/Public/     HomeController
  Data/                   DemoHomeData: todos los contratos y ejemplos comerciales
  Views/
    layouts/              Layout público
    components/public/    Header, navegación, tarjetas, slider, diálogos y footer
    public/               Composición de la Home
    errors/               404/405 y 500
bootstrap/                Autoload equivalente PSR-4 y arranque
config/                   Configuración y registro de rutas
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
- 404 real, 405 para métodos no implementados y soporte HEAD.

## Qué es demostrativo

El logo es **tipográfico provisional**. Los productos usan SVG de referencia creados para el prototipo, sin marca ni especificaciones comerciales confirmadas. No hay precios ni stock real. Fotografías de stock no representan instalaciones de Ayala. Campañas, eventos y testimonios están marcados como demo. Las sucursales conocidas tienen direcciones, teléfonos, horarios y fotos pendientes de confirmar. No hay redes ni enlaces de contacto inventados.

La lista solo vive en memoria en la pestaña y se pierde al recargar. Descargarla no equivale a solicitar una cotización. No recopila datos personales ni envía peticiones comerciales. No hay login, CRUD, MySQL, pagos ni administrador.

El prototipo usa `noindex, nofollow`; el dominio, URL absoluta Open Graph, SEO definitivo y aviso de privacidad se completarán antes de publicar.

## Pruebas

```bash
npm test
find app bootstrap config public tests -name '*.php' -exec php -l {} \;
npm run build
# Con el servidor iniciado y Chrome instalado:
CHROME_PATH=/ruta/al/chrome npm run test:browser
```

Las pruebas de navegador usan por defecto `/opt/google/chrome/chrome`. Las capturas quedan en `tests/artifacts/` (ignoradas por Git). No es necesario instalar un navegador adicional si Chrome está disponible.

Consulta [resultados ejecutados](docs/qa.md), [arquitectura](docs/architecture.md), [sistema de diseño](docs/design-system.md), [despliegue cPanel](docs/deployment-cpanel.md), [roadmap](docs/roadmap.md) y [procedencia de assets](docs/assets.md).

## Revisión para aprobar el diseño

1. En escritorio: jerarquía del hero, contraste, marcas, densidad del catálogo y banners.
2. En 360/390 px: encabezado, menú con teclado, legibilidad, cuadrícula y áreas táctiles.
3. Buscar “taladro”, probar filtros y un término sin resultados.
4. Agregar productos, cambiar cantidades, descargar la lista y verificar el aviso de demo.
5. Cambiar campañas con flechas, indicadores y arrastre táctil.
6. Revisar todos los textos y la claridad de los datos pendientes/demostrativos.

Mejoras concretas para la siguiente revisión visual: integrar el logotipo oficial, reemplazar ilustraciones por fotos homogéneas de productos reales y sustituir fotografía de stock por obras/sucursales autorizadas. No iniciar el administrador hasta aprobación expresa de la Home.
