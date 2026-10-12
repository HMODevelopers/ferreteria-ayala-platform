# Convenciones del proyecto

- Respetar PHP puro 8.2+, MVC propio y autoload `App\` → `app/`. No introducir frameworks sin autorización.
- Document root siempre `public/`. Nunca mover app, config, storage, secretos o Git al directorio público.
- Registrar rutas en `config/routes.php`; controladores coordinan datos y renderizado. Mantener lógica comercial y consultas fuera de las vistas.
- Datos de esta fase en `DemoHomeData`; pasar arrays explícitos a cada componente. Todo contenido dinámico debe usar `e()`. `$content` es HTML ya renderizado, no entrada del usuario.
- Reutilizar `app/Views/components/public/`. Container se expresa con `.container`; evitar abstracciones excesivas.
- Mantener colores naranja/negro/blanco y fuentes locales. El logo provisional no es la identidad definitiva.
- Tailwind 4 compila localmente. No usar Play CDN, React, Vue, jQuery, DataTables ni procesos Node en producción.
- Mantener clases completas detectables por Tailwind, fuentes JS en `resources/js/` y generar `public/assets/` con `npm run build`.
- No simular compra, guardado comercial ni envío de cotizaciones. Demo claramente identificada; no inventar stock, precios, promociones reales, teléfonos, direcciones o testimonios.
- No modificar módulos ajenos sin necesidad. No iniciar MySQL, autenticación ni administrador hasta aprobación expresa de la fase pública.
- Priorizar semántica, foco visible, teclado, reduced motion, imágenes optimizadas y seguridad. Nuevas entradas requieren validación; no renderizar entrada como HTML.
- Compatibilidad cPanel/Apache y FTP obligatoria. Secretos por variables de entorno; `.env` nunca en Git, no se carga automáticamente.
- Ejecutar sintaxis PHP, pruebas relevantes, compilación y verificación de rutas. Tras cambios visuales, revisar responsive si hay navegador. Reportar pruebas pendientes con honestidad.
- Conservar licencias de fuentes/Swiper y documentar procedencia de imágenes. No reemplazar por logotipos externos sin recursos autorizados.
