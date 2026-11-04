# Verificación de la plataforma

Fecha de última ejecución local: 3 de noviembre de 2026. Entorno vigente: Windows, PHP CLI 8.2.35, MySQL 8.4.10 y Node 22 en Docker; Chrome Windows para HTTP. Los resultados Ubuntu anteriores se conservan como historial. M09: 1105 comprobaciones, 313 archivos PHP, 18 fuentes JS, build, SQL, HTTP/Chrome y activación PASS. Regresiones SQL/HTTP M08 y navegador público PASS; base aislada vacía y respaldo restaurado.

## Ejecutado

- Ejecución M06 del 2 de noviembre de 2026: `npm test` alcanzó **914
  comprobaciones**; sintaxis PHP/JavaScript, `npm run build` y
  `git diff --check`: PASS.
- `tests/mysql-m06-integration.php`: PASS en MariaDB 10.11 con 46 migraciones.
  Validó proveedores, documentos, partidas, recepción parcial/completa,
  idempotencia, saldo M05, rollback atómico y dos procesos concurrentes; la base
  aislada terminó vacía.
- `tests/mysql-m06-http-integration.php`: PASS autenticado. Recorrió proveedor,
  factura, partida, confirmación, recepción, inventario, movimiento y auditoría;
  validó CSRF y 360/768/1024/1440 px sin overflow ni errores de consola.
- La primera ejecución detectó que la cuenta mínima no concede `ALTER`; preservó
  45 migraciones propias. Se verificaron tablas vacías y 26 permisos técnicos,
  se recuperó con el migrador oficial y se rediseñó la integración M05 sin
  elevar privilegios. La repetición pasó.
- Respaldo privado `m06-pre-migration-20261102-132706.json`, SHA-256
  `fedc236c71adf65292c98754fdb38ee8d4e998c9b3025223ee0da395aecb1d67`:
  JSON, 41 tablas y manifiesto verificados con permisos `600`.
- Desarrollo conserva **46 migraciones**; M06 está en lote 6. Las seis tablas
  comerciales M06 tienen cero filas, los conteos previos se conservaron y
  `GLOBAL_ADMIN` recibió siete permisos.
- Regresión MariaDB M05 posterior: PASS con descubrimiento acotado a sus 41
  migraciones, concurrencia, timeout/rollback y limpieza final.

- Ejecución M05 del 1 de noviembre de 2026: `npm test` alcanzó **858
  comprobaciones**. Sintaxis PHP, JavaScript, `npm run build` y
  `git diff --check`: PASS.
- `tests/mysql-m05-integration.php`: PASS en MariaDB 10.11 con 41 migraciones,
  precisión, conversiones, inicial, ajustes, idempotencia, saldo insuficiente,
  concurrencia real, timeout/rollback, mínimos y limpieza final.
- `tests/mysql-m05-http-integration.php`: PASS autenticado; validó CSRF,
  inicial, ajuste, mínimo, auditoría y 360/768/1024/1440 px. La base aislada
  terminó vacía.
- Regresiones M00, M01, M02 MariaDB/HTTP, M03 MariaDB/HTTP y M04 MariaDB/HTTP:
  PASS después de M05. Los ejecutores M04 ahora limitan descubrimiento a M04.
- La primera regresión M04 posterior aplicó 41 migraciones y se detuvo antes de
  fixtures. Se verificaron 41 tablas, 41 historiales y solo 26 permisos; ese
  estado sintético se recuperó y se confirmó inventario vacío antes de repetir.
- Respaldo privado `m05-pre-migration-20261101-215204.json`, SHA-256
  `28a7da4b2afd00aa6ce466caccf365677220f07bde48566f23fc962e6d90b97c`:
  JSON, 34 tablas y manifiesto verificados con permisos `600`.
- Desarrollo conserva **41 migraciones**; las seis M05 están en lote 5. Las
  siete tablas M05 tienen cero filas y `GLOBAL_ADMIN` recibió siete permisos.

- Ejecución M04 del 1 de noviembre de 2026: `npm test` alcanzó **797
  comprobaciones**. Cubre decimal sin `float`, límites, redondeo, impuesto
  incluido/excluido, cero frente a ausencia, esquema, permisos y rutas.
- `npm run build`: PASS con Tailwind 4.3.3 y esbuild. `git diff --check`: PASS.
- `npm run test:browser`: PASS fuera del sandbox con servidor PHP temporal;
  Home en cinco viewports y login en tres, sin overflow ni errores de consola.
- `tests/mysql-m04-integration.php`: PASS en MariaDB 10.11 con las credenciales
  limitadas existentes mapeadas únicamente al proceso M04. Validó 35 migraciones,
  `DECIMAL`, precedencia, impuestos, historial, solapamientos, versión
  optimista, ausencia de precio y limpieza final.
- Las dos primeras ejecuciones M04 preservaron estado sintético al detectar un
  marcador PDO repetido y luego una expectativa errónea de vigencias. Cada
  estado fue inspeccionado y recuperado antes del siguiente intento. El tercero
  pasó desde inventario vacío.
- `tests/mysql-m04-http-integration.php`: PASS autenticado con el mismo mapeo
  temporal, CSRF, configuración,
  cálculo, auditoría y cuatro viewports. La base aislada terminó vacía.
- Respaldo privado `m04-pre-migration-20261101-205350.json`, SHA-256
  `f8fe37b12440c8a41d3c56441b4f52ae5633aa7f949eaf7dcee8628c8fb7f72b`:
  JSON, 27 tablas y checksum verificados.
- Desarrollo conserva **35 migraciones**; las ocho M04 están en lote 4. Las
  siete tablas M04 tienen cero filas y el Administrador global recibió 10
  permisos técnicos nuevos.
- Regresiones reales posteriores: M00, M01, M02 MariaDB/HTTP y M03 MariaDB/HTTP
  pasan y dejan sus bases aisladas vacías.

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

M08 administrativo quedó activado con 53 migraciones en MySQL Docker local y nueve permisos asignados al Administrador global. Se conservaron las 50 tablas previas y la base aislada terminó con cero objetos. Los respaldos y resultados exactos están en [integración M08](integration-m08.md).

La base local tiene el administrador pero no contiene sucursales, clientes, variantes, precios ni existencias. Faltan los datos reales del negocio; no se insertaron datos comerciales ficticios. Solicitud pública y SMTP permanecen fuera del incremento administrativo por privacidad/canal de envío pendientes y las restricciones expresas del encargo. M10–M14 no están implementados; M09 quedó integrado posteriormente, con su cierre documentado.

El PDF conserva UTF-8 en SQL/HTML y translitera fuera de Windows-1252 al generar el archivo. La expiración se opera manualmente; no hay proceso residente. El DDL conserva commits implícitos y un fallo de migración exige inspección.

No se probaron Apache/cPanel real, FTP, TLS, subdominio, iOS Safari, Android real ni lectores de pantalla. Tampoco se declara un PASS de MariaDB remoto en el entorno Windows actual. La identidad del administrador real no se mostró ni reutilizó: el navegador utilizó cuentas aleatorias de testing.

## Revisión de aprobación

La Home fue aprobada como base el 17 de octubre de 2026. Permanecen pendientes
el logo oficial, fotos reales y datos de contacto verificados para reemplazar
los placeholders. M00 y M01.1–M01.4 están integrados y listos para revisión
final; las tablas de desarrollo están aplicadas sin datos comerciales. M02 está
integrado en MariaDB, migrado en desarrollo y activado con un Administrador
global. M03, M04, M05 y M06 están integrados, migrados y aprobados en
MariaDB/HTTP. M07 está integrado, migrado y validado. M08 administrativo está activado y verificado en MySQL local; M10–M14 no se han iniciado; M09 ya tiene cierre documentado.

## Ejecución M08 · Windows/Docker, 3 de noviembre de 2026

PHP 8.2.35, MySQL 8.4.10, Node 22 Docker y Chrome Windows. Regresión: 1052 comprobaciones PASS; sintaxis PHP, build y JavaScript PASS. PDF rasterizado con Poppler e inspeccionado. HTTP autenticado PASS con alta, cliente, búsqueda, partidas, cálculo, validación, emisión, PDF, aceptación/cancelación, CSRF, permisos y sucursal ajena; cuatro viewports sin overflow y base vacía al terminar. SQL definitivo PASS con snapshots, descuentos, versiones, inventario, idempotencia, concurrencia, folios, timeout y rollback multilínea. Desarrollo quedó con 53 migraciones, nueve permisos M08 y cero cotizaciones, tras respaldo verificado y conservación de las 50 tablas previas. No se declara MariaDB remoto ni cPanel probado. Resultado final y activación: [integración M08](integration-m08.md).

## Ejecución M09 · Windows/Docker

Regresión PHP M00–M09: 1105 comprobaciones PASS. La primera suite SQL M09 pasó con 57 migraciones, operaciones parciales, conservación de stock, protección de apartados, cobertura M08, rollback por auditoría, concurrencia independiente e inventario final vacío. La suite definitiva ampliada, Chrome y activación se registran en [integración M09](integration-m09.md). No se heredan los PASS históricos de MariaDB como ejecuciones en Windows.

Cierre definitivo M09: SQL y HTTP/Chrome PASS después del ajuste de locks y snapshots iniciales; rutas opuestas y competencia M08 probadas con PDO independientes. Regresiones SQL/HTTP M08 y navegador público: PASS. Desarrollo conserva 57 migraciones y 59 tablas previas sin cambios de datos, diez permisos M09 y cero transferencias. Respaldo privado restaurado y comparado íntegramente; base aislada vacía al finalizar. Evidencia de activación: tests/artifacts/m09-activation.json (privada e ignorada).
