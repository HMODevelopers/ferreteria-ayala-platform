# Sistema de diseño público

Identidad industrial editorial: títulos condensados, fotografía con contraste, fondos claros y bordes sutiles. Naranja para acciones y detalles; el negro organiza hero, cotización y footer. Evitar sombras pesadas y radios exagerados.

## Tokens

| Token Tailwind | Valor | Uso |
| --- | --- | --- |
| primary | #FF6B00 | Fondos de botones, indicadores y acentos |
| primary-hover | #E65F00 | Hover de botones |
| dark | #151515 | Texto, bloques oscuros |
| surface | #F4F5F7 | Fondos secundarios |
| muted | #64748B | Texto secundario |
| border | #E5E7EB | Líneas y tarjetas |

Los valores son aproximaciones del brief; el manual oficial tiene prioridad cuando esté disponible. Se usa `#A54300` para texto naranja pequeño sobre blanco por contraste; botones naranja llevan texto oscuro. Foco con outline visible de 3px.

Fuentes locales con SIL OFL: **Inter** 400/500/600/700 para UI y texto; **Barlow Condensed** 600/700/800 para títulos y marca tipográfica temporal. No se inventa el logotipo oficial. Licencias copiadas a `public/assets/licenses/`.

Escala: cuerpo 12–15px, títulos de sección fluidos 32–46px, hero 44–78px. Etiquetas secundarias 9–11px; confirmar legibilidad real con contenido definitivo. Base de espaciado de 4px; secciones 44px en móvil y 72px en escritorio; tarjetas entre 18 y 26px de padding. Container máximo 1280px y márgenes móviles 18px.

Radios principales de 3–4px; chips redondos solo para contador y avatar. Transiciones 180–250ms; slide 500ms. Reduced motion elimina animaciones y navegación suave. Hero manual sin autoplay.

Breakpoints Tailwind por defecto (640, 768, 1024, 1280, 1536px) y ajustes de composición a 767, 1100 y 1440px. Categorías 2/3/6 columnas; productos 2/3; eventos/reseñas 1/3; sucursales 2/4. No hay carruseles de catálogo innecesarios.

## Componentes

| Componente | Implementación |
| --- | --- |
| Button | `button.php` para links; `.btn` para acciones y variante `.btn-secondary` |
| Container | Clase `.container`, sin wrapper PHP abstracto |
| SectionHeading | `section-heading.php`, eyebrow, título, descripción y link opcionales |
| ProductCard / CategoryCard | Tarjetas con datos explícitos e imágenes locales |
| PromotionCard | `promotion-banner.php`, texto sobre fotografía y marcado demo |
| BranchCard / ReviewCard | Datos pendientes o ejemplos identificados |
| Badge | `badge.php`, etiqueta contextual |
| EmptyState | `empty-state.php`, búsqueda sin coincidencias y reset |
| MobileNavigation | `mobile-menu.php`, dialog nativo y controles de teclado |

Botones de interacciones requieren JS y permanecen disabled hasta estar listos. Links llevan destinos reales de la Home; no hay botones comerciales de envío. Inputs tienen labels; diálogos usan título, Escape y recuperación de foco. Las diapositivas inactivas quedan inert. La lista usa textContent para datos dinámicos.

## Próximas mejoras visuales

Integrar el logo original con área de protección, fotografías de producto recortadas de igual tamaño, imágenes propias de obra/sucursal y copy comercial aprobado. Mantener densidad de información sin hacer tarjetas gigantes. Revalidar contraste y tamaños con la fotografía final y el manual de marca.
