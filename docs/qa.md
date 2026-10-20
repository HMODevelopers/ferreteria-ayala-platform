# Verificación de la plataforma

Fecha de última ejecución: 19 de octubre de 2026. Entorno: Ubuntu, Node 22.22.1, npm 9.2.0 y PHP CLI 8.5.4. El código utiliza sintaxis compatible con PHP 8.2; no se ejecutó una matriz de versiones 8.2/8.3/8.4. PDO y el controlador `pdo_mysql` están disponibles en el PHP CLI de este entorno.

## Ejecutado

- Sintaxis de los archivos PHP: sin errores.
- Pruebas PHP `tests/run.php`: **477 comprobaciones** correctas. Conservan las
  406 comprobaciones previas; las 71 nuevas de M01.4 cubren conexión diferida,
  consultas preparadas, parámetros, paginación, rehidratación, duplicados
  controlados, orden estable, matrices de disponibilidad, transacciones,
  rollback y el protocolo de bloqueo/recarga/validación de horarios. Las 82 de
  M01.3 continúan cubriendo códigos, nombres,
  descripciones opcionales, límites, estados lógicos, identificadores y orden de
  asociaciones, desempate determinista, matriz de disponibilidad, ausencia de
  entidades/relación, descubrimiento, SQL, claves, índices y rollbacks
  protegidos mediante dobles de PDO.
- `npm run dev`: observación iniciada correctamente y detenida después de comprobar el modo desarrollo.
- `npm run build`: Tailwind 4.3.3 y esbuild correctos; assets locales minificados y fuentes/licencias copiadas.
- Dos compilaciones consecutivas produjeron assets idénticos. Tailwind usa fuentes explícitas de vistas y JavaScript para impedir que clases incidentales de PHP técnico alteren el CSS.
- Instalación/auditoría npm: **0 vulnerabilidades reportadas** después del override de `@parcel/watcher` a 2.6.x. Tailwind CLI fijaba 2.5.1 vulnerable; override limitado a herramienta de observación local.
- Navegador a 360, 390, 768, 1024 y 1440px: sin overflow horizontal; todas las imágenes cargadas. Capturas completas y de viewport en tests/artifacts (ignoradas por Git).
- Flechas/indicadores del hero con áreas táctiles mínimas de 44×44px, filtro por categoría, búsqueda, estado vacío y reset: correctos.
- Agregar producto, cambiar cantidad, contador y descarga TXT: correctos.
- Menú móvil, Escape, ciclo Tab/Shift+Tab dentro del diálogo, recuperación de foco y link de categoría: correctos.
- Cambio dinámico de reduced motion: velocidad del slider 0ms.
- HTTP GET / 200, HEAD / 200, POST / 405, ruta inexistente 404; archivos privados, router de desarrollo y asset inexistente no expuestos.
- Sin errores de JavaScript ni errores relevantes de consola durante los flujos probados.
- Sin JavaScript: hero principal y seis productos visibles; acciones dependientes de JS permanecen disabled.

## Pendiente / límite del alcance

La integración `npm run test:mysql` no se ejecutó porque no se ha autorizado modificar una base MariaDB de pruebas. La prueba ahora exige una base vacía terminada en `_test`, una cuenta no administrativa con privilegios exactos, identidad efectiva verificable, inventario visible de tablas, vistas, triggers, eventos y rutinas, exclusión durante el ciclo y propiedad demostrable antes de limpiar `schema_migrations`.

Las migraciones M01.1, M01.2 y M01.3 tampoco se ejecutaron. Las pruebas aisladas verifican el
SQL y el comportamiento de sus callbacks, pero no sustituyen la validación de
`CHECK`, claves foráneas, índices, precisión decimal, creación ni rollback en
MariaDB 10.11/MySQL. Los `CHECK` no sustituyen la validación entre varias filas;
las escrituras de M01.4 ya revalidan el calendario bajo un bloqueo de sucursal,
pero los dobles no prueban serialización, timeouts ni deadlocks reales. Las
claves foráneas, unicidad y restricciones de M01.3 también
requieren integración real. Esa integración solo procede después de resolver M00 y con
autorización expresa sobre una base exclusiva.

Como antecedente informado, antes de esta tarea se verificó la conexión de la aplicación al minilab MariaDB y se inicializó `schema_migrations` en la base de desarrollo. Esa operación no se repitió aquí y no sustituye la integración en `ferreteria_ayala_test`. M00 no está completo hasta que dicha integración pase. Continúan sin probarse Apache/cPanel real, FTP, TLS, instalación en subdominio, iOS Safari, Android real ni lectores de pantalla. No se confirma stock, precios ni ningún flujo administrativo porque no existen en esta fase.

## Revisión de aprobación

La Home fue aprobada como base el 17 de octubre de 2026. Permanecen pendientes
el logo oficial, fotos reales y datos de contacto verificados para reemplazar
los placeholders. La infraestructura de persistencia está implementada. M01.1,
M01.2, M01.3 y M01.4 están implementados y validados únicamente de forma aislada,
sin tablas comerciales aplicadas. El administrador y M02–M14 permanecen
fuera del alcance.
