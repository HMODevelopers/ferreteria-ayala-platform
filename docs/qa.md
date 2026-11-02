# Verificación de la plataforma

Fecha de última ejecución local: 1 de noviembre de 2026. Entorno: Ubuntu, Node 22.22.1, npm 9.2.0 y PHP CLI 8.5.4 de 64 bits. El código utiliza sintaxis compatible con PHP 8.2; no se ejecutó una matriz de versiones 8.2/8.3/8.4. PDO, `pdo_mysql` y `proc_open` están disponibles.

## Ejecutado

- Ejecución del 1 de noviembre de 2026: `tests/run.php` alcanzó **723
  comprobaciones** correctas. M03 añade dominio, contratos de once tablas,
  permisos, rutas, CSRF y protección sin sesión.
- Sintaxis PHP de todos los archivos de aplicación, migraciones y pruebas: sin
  errores. `npm run build` completó Tailwind 4.3.3 y esbuild;
  `git diff --check` fue correcto.
- `npm run test:mysql:m03`: PASS en MariaDB 10.11 con cuenta exclusiva; aplicó,
  probó y revirtió 27 migraciones y dejó inventario vacío.
- Respaldo privado `m03-pre-migration-20261101-183125.json`, SHA-256
  `830d7f1d286ecfb0394617dc3de25e96e9dbd2dd658d3f82104267e9c208a36a`:
  sus 16 tablas fueron restauradas, comparadas y limpiadas en la base aislada.
- `php bin/migrations.php status` confirma 27 migraciones aplicadas; las doce
  M03 pertenecen al lote 3. Sus once tablas comerciales continúan vacías.
- `npm run test:browser`: PASS con servidor local temporal; Home en cinco
  viewports y login administrativo en tres, sin overflow ni errores de consola.
- `npm run test:mysql:m03:http`: PASS autenticado. Productos, categorías,
  marcas, unidades y atributos se revisaron a 360, 768, 1024 y 1440 px, con
  CSRF, altas, búsqueda por SKU/código, activaciones, auditoría y limpieza final.
- Regresión real posterior a M03: `npm run test:mysql` PASS;
  `npm run test:mysql:m02` PASS; `npm run test:mysql:m02:http` PASS con flujos
  autenticados y limpieza final. M01 se detuvo antes de conectar porque
  `M01_TEST_AUTHORIZATION` no estaba exportada para esta ejecución.

- Sintaxis de **152 archivos PHP**: sin errores.
- Pruebas PHP `tests/run.php`: **633 comprobaciones** correctas. M02 añade
  contratos de contraseña, normalización de usuarios, catálogo de permisos,
  Request seguro, huellas de auditoría, CSRF, migraciones, rutas protegidas y
  CLI inicial sin contraseña predeterminada. Las pruebas anteriores incluyen
  regresiones cubren la comparación binaria de los tres códigos, el contrato de
  preflight de migraciones y que un fallo en cualquier elemento del último lote
  ocurra antes de ejecutar el primer `down()`. También verifican el protocolo
  JSON del proceso aislado y que el diagnóstico incluya etapa/tipo sin filtrar
  el mensaje de `PDOException`. Las 71 comprobaciones de M01.4 cubren conexión diferida,
  consultas preparadas, parámetros, paginación, rehidratación, duplicados
  controlados, orden estable, matrices de disponibilidad, transacciones,
  rollback y el protocolo de bloqueo/recarga/validación de horarios. Las 82 de
  M01.3 continúan cubriendo códigos, nombres,
  descripciones opcionales, límites, estados lógicos, identificadores y orden de
  asociaciones, desempate determinista, matriz de disponibilidad, ausencia de
  entidades/relación, descubrimiento, SQL, claves, índices y rollbacks
  protegidos mediante dobles de PDO.
- El recuperador M01 quedó validado con pruebas aisladas y ejecución real: modo destructivo
  apagado por defecto, endpoint/base/cuenta exactos, inventario y estructura,
  historial, sesiones/bloqueos visibles, propiedad fila por fila, prueba del
  respaldo, orden FK, rollback ante fallo DML y diagnósticos sin secretos. Los
  modos `inspect` y `recover` pasaron contra MariaDB después de verificar el dump
  con una restauración independiente.
- `npm run test:mysql`: PASS con `ayala_m00_test`; dejó inventario vacío.
- `npm run test:mysql:m01`: PASS con `ayala_m01_test`; validó esquema,
  repositorios, transacciones, solapamiento serializado, timeout y deadlock, y
  dejó inventario vacío.
- `npm run test:mysql:m02`: PASS con la cuenta exclusiva M02; validó las quince
  migraciones, inventario de 16 tablas, administrador inicial sintético,
  autenticación correcta/incorrecta, sesión, autorización global y por
  sucursal, protección del último administrador, revocación y auditoría sin
  secretos. Confirmó inventario final vacío.
- `npm run test:mysql:m02:http`: PASS. Aplicó el esquema en la base aislada,
  creó fixtures aleatorios y ejerció login, dashboard, usuarios, roles,
  sucursales, horarios/servicios vacíos, CSRF, permisos, cambio de contraseña,
  revocación, auditoría y logout. Revisó 360, 768, 1024 y 1440 px sin overflow
  ni errores de consola y confirmó limpieza final completa.
- Desarrollo conserva las siete migraciones M01 en lote 1 y las ocho M02 en
  lote 2. La verificación oficial enumeró quince registros y `migrate` devolvió
  que no había archivos pendientes. Las tablas M01 permanecen vacías.
- El esquema real de desarrollo confirmó InnoDB/utf8mb4, los índices y diez FKs
  M02 esperadas, 17 permisos, 17 asociaciones al rol global, exactamente un
  Administrador global activo, hash unidireccional aprobado y auditoría inicial.
- `npm run dev`: observación iniciada correctamente y detenida después de comprobar el modo desarrollo.
- `npm run build`: Tailwind 4.3.3 y esbuild correctos; assets locales minificados y fuentes/licencias copiadas.
- Dos compilaciones consecutivas produjeron assets idénticos. Tailwind usa fuentes explícitas de vistas y JavaScript para impedir que clases incidentales de PHP técnico alteren el CSS.
- Instalación/auditoría npm: **0 vulnerabilidades reportadas** después del override de `@parcel/watcher` a 2.6.x. Tailwind CLI fijaba 2.5.1 vulnerable; override limitado a herramienta de observación local.
- Navegador a 360, 390, 768, 1024 y 1440px: sin overflow horizontal; todas las imágenes cargadas. Capturas completas y de viewport en tests/artifacts (ignoradas por Git).
- Login administrativo revisado a 360, 768 y 1440px: sin overflow, etiquetas
  correctas, foco por teclado, assets locales y redirección 303 desde `/admin`
  sin sesión.
- Panel autenticado revisado a 360, 768, 1024 y 1440px. Se corrigió el patrón
  HTML de la clave de sucursal para escapar el guion bajo el modo Unicode `v`
  de Chrome; la regresión confirma que ya no produce errores de consola.
- Flechas/indicadores del hero con áreas táctiles mínimas de 44×44px, filtro por categoría, búsqueda, estado vacío y reset: correctos.
- Agregar producto, cambiar cantidad, contador y descarga TXT: correctos.
- Menú móvil, Escape, ciclo Tab/Shift+Tab dentro del diálogo, recuperación de foco y link de categoría: correctos.
- Cambio dinámico de reduced motion: velocidad del slider 0ms.
- HTTP GET / 200, HEAD / 200, POST / 405, ruta inexistente 404; archivos privados, router de desarrollo y asset inexistente no expuestos.
- Sin errores de JavaScript ni errores relevantes de consola durante los flujos probados.
- Sin JavaScript: hero principal y seis productos visibles; acciones dependientes de JS permanecen disabled.

## Pendiente / límite del alcance

M03 queda activado en desarrollo sin catálogo comercial. Continúan pendientes
los datos reales del negocio y los módulos M04–M14, fuera de este cierre.

La identidad y contraseña del Administrador global pertenecen al propietario y
no se mostraron ni reutilizaron en las pruebas. Su existencia, estado activo,
rol, permisos, hash y auditoría sí fueron verificados; los flujos autenticados
se probaron con identidades aleatorias dentro de la base exclusiva de testing.

El DDL de MariaDB conserva commits implícitos; las guardas reducen el riesgo,
pero una caída durante un rollback futuro todavía exigiría inspección manual.
Continúan sin probarse Apache/cPanel real, FTP, TLS, instalación en subdominio,
iOS Safari, Android real ni lectores de pantalla. No se confirman stock,
precios, catálogo real ni módulos M04–M14 porque no existen en esta fase.

## Revisión de aprobación

La Home fue aprobada como base el 17 de octubre de 2026. Permanecen pendientes
el logo oficial, fotos reales y datos de contacto verificados para reemplazar
los placeholders. M00 y M01.1–M01.4 están integrados y listos para revisión
final; las tablas de desarrollo están aplicadas sin datos comerciales. M02 está
integrado en MariaDB, migrado en desarrollo y activado con un Administrador
global. M03 está integrado, migrado y aprobado en MariaDB/HTTP; M04–M14 no se
han iniciado.
