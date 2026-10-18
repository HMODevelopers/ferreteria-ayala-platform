# Diseño de Ferretería Ayala

## Referencia visual oficial de esta etapa

La maqueta autocontenida
[`Anexo_A_Maqueta_Ferreteria_Ayala.html`](Anexo_A_Maqueta_Ferreteria_Ayala.html)
es la fuente visual principal para la interfaz. Define composición, jerarquía,
colores, espaciado, tarjetas, formularios y comportamiento responsive esperado.

La aplicación no debe publicar ni incrustar directamente el anexo. El diseño se
traslada a las vistas y componentes PHP existentes, a clases detectables por
Tailwind y a JavaScript modular dentro de `resources/js/`. De esta forma el
anexo funciona como especificación y el sistema conserva MVC, accesibilidad,
seguridad y mantenimiento.

En caso de diferencia visual entre este documento, la implementación actual y
el anexo, prevalece el anexo. Las reglas de seguridad, accesibilidad y alcance
del proyecto siempre prevalecen sobre cualquier comportamiento simulado en la
maqueta.

## Alcance

La parte pública del anexo es el objetivo de la fase actual:

- anuncio superior, encabezado fijo, identidad provisional y buscador;
- hero oscuro con acento naranja, ilustración y controles de campaña;
- beneficios, categorías, filtros y cuadrícula de productos;
- llamada a cotización, promociones/eventos, servicios, sucursales y reseñas;
- footer, lista de materiales, formularios y estados vacíos;
- adaptaciones de escritorio, tableta y móvil.

El panel administrativo incluido en el mismo HTML es una **visión visual
futura**, no una autorización para implementar administración, autenticación o
MySQL en esta fase. Tampoco deben trasladarse como funciones reales el guardado
en `localStorage`, el envío ficticio de cotizaciones, los folios simulados ni la
publicación de reseñas. Esas pantallas podrán servir como referencia cuando se
aprueben sus requisitos y seguridad.

## Identidad y tokens

La dirección visual es industrial, limpia y comercial. El naranja identifica
acciones y acentos; negro y grafito estructuran la marca, hero, navegación y
footer; las superficies claras mantienen legibilidad y separación.

| Token | Valor de referencia | Uso |
| --- | --- | --- |
| `primary` | `#FF6B00` | Botones, indicadores, acentos y marca provisional |
| `primary-text` | `#E9650A` | Etiquetas pequeñas sobre fondos claros |
| `dark` | `#1C1C1C` | Botones y bloques oscuros |
| `dark-deep` | `#111111` | Barra superior, navegación administrativa y footer |
| `cream` | `#FFF8F3` | Texto cálido y fondos de apoyo |
| `background` | `#F6F7F8` | Fondo general |
| `ink` | `#1F2328` | Texto principal |
| `muted` | `#737780` | Texto secundario |
| `border` | `#E7E7E7` | Bordes y divisores |
| `white` | `#FFFFFF` | Superficies y contraste |
| `shadow` | `0 14px 45px rgba(20,45,34,.09)` | Elevación puntual |

El naranja para texto debe ajustarse si no alcanza contraste suficiente en el
contexto final. El foco visible usa un contorno naranja de 3 px con separación
de 3 px. El manual de marca y logotipo oficiales tendrán prioridad cuando sean
entregados.

## Tipografía

La maqueta utiliza **Inter** para interfaz, texto, títulos y marca tipográfica
provisional, con pesos aproximados de 400 a 900. En producción se usan archivos
locales y no se consulta Google Fonts ni otro CDN. Hasta recibir el logotipo
oficial, la marca sigue siendo texto y no debe sustituirse por un logotipo
externo.

- Cuerpo: 12–15 px según el componente.
- Títulos de sección: alrededor de 31 px en escritorio y 26 px en móvil.
- Hero: fluido hasta aproximadamente 62 px; 42 px en móvil.
- Etiquetas y metadatos: 10–12 px, con contraste y legibilidad verificados.

## Retícula y espaciado

- Contenedor máximo: 1260 px.
- Padding horizontal: 30 px en escritorio y 20 px debajo de 900 px.
- Separación entre secciones: 67 px en escritorio y 44 px en móvil.
- Hero: mínimo 460 px, dos columnas `1.05fr / .95fr` y radio de 22 px.
- Tarjetas: radios entre 14 y 19 px, bordes suaves y sombras solo al destacar.
- Botones: radio aproximado de 10 px y áreas táctiles mínimas de 44×44 px
  cuando corresponda.

### Cuadrículas

| Sección | Escritorio | Tableta | Móvil |
| --- | --- | --- | --- |
| Categorías | 5 columnas | 3 columnas | 2 columnas |
| Productos | 4 columnas; 3 debajo de 1150 px | 3 columnas | 2 columnas |
| Eventos y reseñas | 3 columnas | 3 columnas | 1 columna |
| Sucursales | 4 columnas | 2 columnas | 2 columnas |
| Bloques de servicios | 2 columnas | 2 columnas | 1 columna |

Los puntos de cambio principales de la maqueta son 1150, 900 y 640 px. Tailwind
puede expresar los mismos resultados aunque sus breakpoints internos no tengan
el mismo nombre.

## Componentes

| Elemento visual | Implementación del sistema |
| --- | --- |
| Identidad y encabezado | `identity.php`, `header.php`, `navigation.php`, `mobile-menu.php` |
| Hero | `hero-slider.php`, navegación manual y sin autoplay |
| Categorías y productos | `category-card.php`, `product-card.php`, filtros y búsqueda |
| Promociones y eventos | `promotion-banner.php`, `event-card.php` |
| Sucursales y reseñas | `branch-card.php`, `review-card.php` |
| Lista de materiales | `quote-dialog.php`; local, descargable y marcada como demo |
| Elementos comunes | `button.php`, `badge.php`, `section-heading.php`, `empty-state.php` |
| Cierre de página | `footer.php` |

El contenedor se expresa con `.container`; no se crea un wrapper PHP adicional.
Los componentes reciben arrays explícitos. Todo texto dinámico se escapa con
`e()` y el HTML de usuario nunca se renderiza directamente.

## Interacción y accesibilidad

- Hero manual, sin autoplay, con flechas, indicadores y gesto táctil.
- Navegación, formularios y diálogos utilizables con teclado.
- Foco visible, Escape, bloqueo de scroll y recuperación de foco en diálogos.
- `prefers-reduced-motion` elimina movimiento no esencial y navegación suave.
- Inputs con `label`; los avisos no dependen solo del color.
- Imágenes locales, optimizadas, con dimensiones y texto alternativo adecuados.
- Los botones que dependan de funciones no implementadas permanecen desactivados
  o se presentan inequívocamente como demostración.

Los `onclick` inline, formularios simulados y renderizado por plantillas de
JavaScript del anexo no se copian literalmente. Son una demostración visual que
debe convertirse en eventos modulares y renderizado seguro.

## Criterios de comparación

La implementación se considerará visualmente alineada cuando:

1. Orden, proporciones, colores y jerarquía coincidan con el anexo.
2. Las cuadrículas respondan correctamente a 1440, 1024, 768, 390 y 360 px.
3. No exista desbordamiento horizontal ni texto cortado.
4. Estados de hover, foco, vacío, filtros y lista sean consistentes.
5. La página continú mostrando contenido esencial sin JavaScript.
6. Ningún flujo demo parezca enviar una operación comercial real.

## Recursos pendientes

Integrar el logotipo oficial, fotografías homogéneas de productos, imágenes
autorizadas de obras y sucursales, datos de contacto verificados y copy aprobado.
Hasta entonces deben conservarse las advertencias de contenido demostrativo y
la desactivación de indexación.
