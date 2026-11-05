# Navegación administrativa agrupada

Implementación del 4 de noviembre de 2026. Se conserva el estilo negro/naranja, las 19 rutas existentes y sus permisos. No hay migraciones, cambios de negocio, cuentas nuevas ni modificaciones de .env.

| Grupo | Accesos |
| --- | --- |
| Comercial | Clientes, solicitudes públicas, cotizaciones, condiciones por sucursal |
| Inventario y logística | Inventario, compras y recepciones, transferencias, entregas |
| Catálogo y precios | Catálogo, precios, impuestos y reglas |
| Sitio web | Contenido y medios, documentos legales, publicación del sitio |
| Administración | Usuarios, roles y permisos, sucursales, auditoría |

Resumen permanece fuera del área desplazable cuando el usuario tiene permiso para dashboard. Los grupos vacíos y los enlaces no autorizados se omiten en PHP mediante el navAccess existente; JavaScript no concede permisos ni crea enlaces. La pantalla actual abre su grupo desde el servidor y conserva aria-current en su enlace.

Todos los grupos autorizados permanecen visibles. Se usan section y encabezados h2 estáticos: hacer clic en un título no colapsa enlaces. No se guardan ni restauran preferencias de apertura; las preferencias antiguas de localStorage se ignoran. Los permisos se filtran en el servidor.

Cada acceso tiene icono SVG propio local. La barra estrecha permanece transparente en reposo y aparece al pasar el cursor, enfocar o desplazar el menú. Dos degradados sutiles indican si hay opciones fuera de vista arriba o abajo. Los encabezados de grupo permanecen visibles mientras se recorren sus enlaces; se reduce el espacio vertical en escritorio y se conservan objetivos táctiles de 44 px en móvil. El área conserva overflow-y:auto, scroll con rueda/trackpad/táctil y un foco propio para desplazamiento por teclado. Resumen y la marca permanecen fijos al desplazar grupos.

En móvil se mantiene el panel lateral: cierre visible, fondo para cerrar y Escape, foco inicial en la opción actual, Tab dentro del panel y retorno al botón de apertura al cerrar. El contenido posterior queda inert mientras el panel está abierto; el panel cerrado no recibe foco. El cambio a escritorio restaura la interacción del contenido. Se respeta prefers-reduced-motion.

## Validación ejecutada

- PHP: 378 archivos con sintaxis válida.
- Pruebas locales M00–M11 y contrato de navegación: PASS, 1250 comprobaciones. Las quince nuevas comprueban las 19 rutas, iconos, grupo activo y filtrado de perfiles.
- Sintaxis de JavaScript modificado/nuevo y compilación Tailwind/esbuild: PASS.
- Chrome: PASS, 47 comprobaciones; memoria por usuario, permisos visibles, Enter/Espacio, rutas, almacenamiento bloqueado, escritorio sin JS, reduced motion, cierre/foco/Tab y responsive 360/768/1024/1440 px.
- Capturas de escritorio y móvil inspeccionadas visualmente. Se espera a que el panel termine de desplegarse antes de comprobar su posición/capturarlo.
- Consola, scripts y solicitudes fallidas: ninguna incidencia en la ejecución final.
- Rutas reales sin sesión: login 200; /admin, /admin/cms, /admin/quotes y /admin/inventory 303.
- git diff --check: PASS. No se ejecutó add, commit ni push.

Los recorridos Chrome usan el layout y componente reales renderizados con perfiles de prueba, servidos por un servidor local de solo lectura; no crean cuentas ni acceden a MySQL. La autorización del servidor permanece en los controladores existentes. No se repitieron las suites SQL porque no se modificó persistencia, servicios ni esquema.

Ejecutores:

    docker exec ferreteria-ayala-php-1 php tests/m11-lint.php
    docker exec ferreteria-ayala-php-1 php tests/run.php
    docker exec ferreteria-ayala-php-1 php tests/admin-navigation-fixtures.php
    docker compose --env-file .env.example run --rm assets npm run build
    node tests/admin-navigation-browser.mjs

Evidencias ignoradas por Git: tests/artifacts/admin-navigation/pass.json, overview-1440.png, desktop-1024.png, desktop-1440.png y mobile-360.png/mobile-768.png. Los procesos Node pertenecen únicamente a compilación y pruebas locales, no a producción.


La preferencia del propietario es ver todo expandido al inicio. La versión v2 de las preferencias evita restaurar los grupos cerrados por la configuración anterior; se siguen recordando los cierres que el usuario elija manualmente y el grupo actual siempre se abre al navegar.

Verificación del ajuste final: PASS, 1266 comprobaciones locales, 381 archivos PHP válidos, compilación y 50 comprobaciones Chrome. Incluye todos los grupos abiertos inicialmente, barra oculta, desplazamiento con rueda y PageDown y funcionamiento sin JavaScript.

Ajuste de orientación y densidad: PASS, 54 comprobaciones Chrome (señales al inicio, mitad y final; encabezado visible durante scroll; teclado y responsive), 1266 comprobaciones locales, sintaxis de 381 archivos PHP, JS y build. Capturas finales de escritorio y móvil revisadas.

Simplificación visual solicitada: se retiran las flechas de los grupos y los separadores horizontal de Resumen y verticales de submenús. Se conservan los iconos, la indentación, la marca naranja de la opción activa y los controles nativos. Build, sintaxis PHP y 56 comprobaciones Chrome aprobadas; capturas responsive revisadas.

Estado final solicitado: títulos estáticos sin colapsado, flechas ni separadores. Verificado con y sin JavaScript, clic en los cinco títulos y navegación móvil. PASS: 51 comprobaciones Chrome, 1266 locales, sintaxis PHP/JS y build.

Jerarquía visual simplificada: encabezados de grupo sin iconos, en gris y de menor tamaño; opciones con iconos alineadas sin la sangría anterior. El naranja queda en el enlace activo. Se conservan los encabezados estáticos, todos los grupos visibles y el scroll discreto. Sintaxis PHP, build, diff y 53 comprobaciones Chrome aprobadas; capturas de escritorio y móvil revisadas.

Ajuste final de Resumen: ahora es el primer enlace dentro del mismo contenedor desplazable de los grupos, con tamaño, icono y selección equivalentes a las demás opciones. La marca permanece fija; Resumen se desplaza con el menú. Esta preferencia reemplaza la decisión anterior de fijarlo. PASS: 1391 comprobaciones locales, sintaxis PHP, build y 53 comprobaciones Chrome, incluyendo desplazamiento conjunto en móvil.
