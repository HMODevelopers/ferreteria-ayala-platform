# Verificación de la plataforma

## Devoluciones operativas · 10 de noviembre de 2026

- `php tests/run.php`: PASS, 1576 comprobaciones.
- `npm run build`: PASS con Tailwind 4.3.3.
- `npm run test:browser:returns`: PASS, 12 escenarios en Chrome headless a
  360, 768, 1440 y 2560 px sin desbordamiento.
- `npm run test:mysql:returns`: bloqueada antes de migrar por autenticación de
  la identidad aislada configurada. `php tests/mysql-m05-integration.php`
  reproduce el mismo fallo, por lo que no se sustituyó por `DB_*` ni se tocó
  desarrollo.
- Alcance detallado: [integración de devoluciones](integration-returns.md).

Fecha de última ejecución local: 10 de noviembre de 2026. Entorno vigente:
Linux, PHP CLI 8.5.4, Node 22.22.1, npm 9.2.0 y Chrome headless. El servidor
MariaDB reportado es 10.11.15, pero la identidad aislada configurada fue
rechazada antes de poder consultar su versión en esta ejecución. Los cierres
anteriores de M10 y sus regresiones se conservan como historial, no se
reinterpretan como validación SQL de devoluciones.

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

La base local tiene el administrador pero no contiene sucursales, clientes, variantes, precios ni existencias. Faltan los datos reales del negocio; no se insertaron datos comerciales ficticios. Solicitud pública y SMTP permanecen fuera del incremento administrativo por privacidad/canal de envío pendientes y las restricciones expresas del encargo. M10 de entregas está activado; M11–M14 no están implementados; M09 quedó integrado posteriormente, con su cierre documentado.

El PDF conserva UTF-8 en SQL/HTML y translitera fuera de Windows-1252 al generar el archivo. La expiración se opera manualmente; no hay proceso residente. El DDL conserva commits implícitos y un fallo de migración exige inspección.

No se probaron Apache/cPanel real, FTP, TLS, subdominio, iOS Safari, Android real ni lectores de pantalla. Tampoco se declara un PASS de MariaDB remoto en el entorno Windows actual. La identidad del administrador real no se mostró ni reutilizó: el navegador utilizó cuentas aleatorias de testing.

## Revisión de aprobación

La Home fue aprobada como base el 17 de octubre de 2026. Permanecen pendientes
el logo oficial, fotos reales y datos de contacto verificados para reemplazar
los placeholders. M00 y M01.1–M01.4 están integrados y listos para revisión
final; las tablas de desarrollo están aplicadas sin datos comerciales. M02 está
integrado en MariaDB, migrado en desarrollo y activado con un Administrador
global. M03, M04, M05 y M06 están integrados, migrados y aprobados en
MariaDB/HTTP. M07 está integrado, migrado y validado. M08 administrativo está activado y verificado en MySQL local; M10 de entregas está activado; M11–M14 no se han iniciado; M09 ya tiene cierre documentado.

## Ejecución M08 · Windows/Docker, 3 de noviembre de 2026

PHP 8.2.35, MySQL 8.4.10, Node 22 Docker y Chrome Windows. Regresión: 1052 comprobaciones PASS; sintaxis PHP, build y JavaScript PASS. PDF rasterizado con Poppler e inspeccionado. HTTP autenticado PASS con alta, cliente, búsqueda, partidas, cálculo, validación, emisión, PDF, aceptación/cancelación, CSRF, permisos y sucursal ajena; cuatro viewports sin overflow y base vacía al terminar. SQL definitivo PASS con snapshots, descuentos, versiones, inventario, idempotencia, concurrencia, folios, timeout y rollback multilínea. Desarrollo quedó con 53 migraciones, nueve permisos M08 y cero cotizaciones, tras respaldo verificado y conservación de las 50 tablas previas. No se declara MariaDB remoto ni cPanel probado. Resultado final y activación: [integración M08](integration-m08.md).

## Ejecución M09 · Windows/Docker

Regresión PHP M00–M09: 1105 comprobaciones PASS. La primera suite SQL M09 pasó con 57 migraciones, operaciones parciales, conservación de stock, protección de apartados, cobertura M08, rollback por auditoría, concurrencia independiente e inventario final vacío. La suite definitiva ampliada, Chrome y activación se registran en [integración M09](integration-m09.md). No se heredan los PASS históricos de MariaDB como ejecuciones en Windows.

Cierre definitivo M09: SQL y HTTP/Chrome PASS después del ajuste de locks y snapshots iniciales; rutas opuestas y competencia M08 probadas con PDO independientes. Regresiones SQL/HTTP M08 y navegador público: PASS. Desarrollo conserva 57 migraciones y 59 tablas previas sin cambios de datos, diez permisos M09 y cero transferencias. Respaldo privado restaurado y comparado íntegramente; base aislada vacía al finalizar. Evidencia de activación: tests/artifacts/m09-activation.json (privada e ignorada).

## Incremento M10 · Entregas

Implementación de entregas parciales desde cotizaciones aceptadas, preparación, asignación logística, salida física contra apartados M08/M09 y confirmación del cliente. Incluye incidencias, cancelación previa a salida, vehículos, repartidores y rutas manuales. Cuatro migraciones aditivas; el cierre verificable de SQL, concurrencia, HTTP, responsive y activación se registra en [integración M10](integration-m10.md). Los resultados anteriores se conservan como historia; no se trasladan automáticamente a este incremento.

Cierre M10 confirmado: 61 migraciones, nueve tablas InnoDB/utf8mb4, 23 FKs restrictivas, 20 índices únicos y once permisos GLOBAL_ADMIN. npm test: 1168 comprobaciones; PHP/JS/build/diff, SQL y Chrome M10, regresiones SQL/HTTP M08–M09 y respaldo restaurado PASS. Las 63 tablas previas conservaron sus datos; base aislada vacía y sin fixtures en desarrollo. Los pendientes se calculan en persistencia y las vistas reciben arrays explícitos. Siguiente módulo oficial M11, sin iniciarlo.


## Cierre M11 · 4 de noviembre de 2026

PASS: 1235 comprobaciones locales M00–M11; 375 archivos PHP y 26 JS/MJS con sintaxis válida; build local; SQL M11 con 71 aserciones registradas y carreras entre conexiones independientes; HTTP/Chrome M11 con 80 comprobaciones registradas, sesiones/CSRF/permisos/sucursal, contenido/legales/plantillas/galería, antispam y archivos privados. Responsive 360/768/1024/1440 px en Home, formulario, bandeja, publicación, evidencias y editor. Capturas revisadas visualmente tras corregir contraste público y corte de folios; sin errores inesperados de consola/red en la ejecución final.

Las seis regresiones SQL y HTTP/Chrome de M08–M10 aprobaron y dejaron vacío el esquema aislado. El respaldo final se restauró y comparó antes de aplicar únicamente siete migraciones en desarrollo; se preservaron las 72 tablas previas. Estado final: 68 migraciones, 93 tablas, trece permisos nuevos, ninguna cuenta creada en desarrollo y 21 tablas M11 sin fixtures. Publicación pública aún apagada por insumos reales pendientes. No se declaran cPanel/Apache remoto, dispositivos físicos ni lectores de pantalla probados. Evidencias y limitaciones en [integración M11](integration-m11.md).


## Mejora de navegación administrativa · 4 de noviembre de 2026

PASS: 1250 comprobaciones locales, sintaxis PHP (378 archivos), JS modificado/nuevo, build y 47 comprobaciones Chrome de grupos, preferencias, permisos, teclado, móvil y responsive. Pruebas sobre el layout real con datos de presentación, sin MySQL ni cuentas nuevas. Rutas reales sin sesión conservan la redirección al login. Ver [navegación administrativa](admin-navigation.md) para método y evidencias.


## Diseño de publicación del sitio · 4 de noviembre de 2026

PASS: 1265 comprobaciones locales, 381 archivos PHP válidos, build y 45 comprobaciones Chrome de estados, permisos de accesos, contrato de formularios, teclado y responsive hasta 1920 px. Capturas revisadas. Sin cambios de configuración ni uso de MySQL; se conservan las mejoras del menú previas. Ver [diseño de publicación](publication-settings-design.md).


Ajuste final del menú: grupos expandidos por defecto y barra de desplazamiento oculta, conservando rueda, teclado y Resumen fijo. PASS: 1266 comprobaciones locales, 381 archivos PHP, build y 50 comprobaciones Chrome. Sin cambios de cuentas ni base de datos.

Orientación del scroll administrativo: menú compacto, encabezados de grupo persistentes, degradados condicionados al contenido fuera de vista y barra estrecha al interactuar. PASS: 54 comprobaciones Chrome, 1266 locales, sintaxis PHP/JS y build. Capturas desktop/móvil revisadas.


## Diseño y modales de contenido del sitio · 4 de noviembre de 2026

PASS: 1307 comprobaciones locales, sintaxis de 385 archivos PHP y JS modificado, build; 80 comprobaciones Chrome del diseño en las siete categorías y responsive 360/768/1440/2560; regresiones de menú (51) y publicación (63). La regresión autenticada M11 aprobó 80 aserciones y dejó limpio el esquema aislado. Se verificaron valores preservados en errores, confirmación del guardado, publicación, archivado y programación. Se corrigió la pérdida del mensaje al usar respuestas parciales; los dos fixtures fallidos se respaldaron y verificaron antes de recuperar exclusivamente el esquema aislado. Sin datos nuevos ni cambios de configuración en desarrollo. Ver [diseño del contenido](cms-content-design.md).

Editores específicos del contenido: PASS, 1377 comprobaciones locales, 393 PHP válidos, build y 128 comprobaciones Chrome. Se revisaron formularios de las siete categorías en escritorio/móvil, campos propios y orden, y se conservó el modal compartido. Sin cambios en datos ni servicios.


## Biblioteca de imágenes y carga guiada · 4 de noviembre de 2026

PASS: 1391 comprobaciones locales, 398 archivos PHP válidos, JS y build; 38 comprobaciones Chrome de biblioteca/vista previa/arrastre/errores/carga y responsive hasta 2560 px; regresión de editores por tipo con 128 comprobaciones. HTTP/Chrome M11: 81 aserciones con carga pública real, rechazo de archivo inválido, galerías privadas y limpieza aislada. Capturas vacías, con imágenes y de carga revisadas en escritorio/móvil. Ver [biblioteca de imágenes](media-library-design.md).

Ajuste visual del menú: títulos sin iconos, neutros y pequeños; opciones alineadas con sus iconos y un único enlace activo en naranja. PASS: PHP del componente, build, diff y 53 comprobaciones Chrome responsive. Sin cambios de rutas, permisos ni persistencia.


## Clientes: diseño y modales · 4 de noviembre de 2026

PASS: 1427 comprobaciones locales, 403 PHP válidos, JS y build; 25 comprobaciones Chrome de Clientes y responsive hasta 2560 px, más regresiones del modal compartido de contenido (128) y biblioteca (38). HTTP/Chrome M11 --customers: 92 aserciones y limpieza aislada, con 11 comprobaciones autenticadas de Clientes. Se conservaron contactos, direcciones, validación, control de versiones, filtros y auditoría. Ver [diseño de Clientes](customers-design.md).


## Cotizaciones unificadas — 2026-11-04

- Pruebas locales: 1453 comprobaciones aprobadas; sintaxis PHP: 421 archivos.
- Navegador con páginas de prueba: menú 53, CMS 128, biblioteca 38 y clientes 25 comprobaciones aprobadas tras los cambios del modal compartido.
- Flujo de cotizaciones con páginas de prueba: 28 comprobaciones aprobadas, incluyendo escritorio y móvil, canales, captura de múltiples materiales, conservación de búsquedas y validación en modal.
- Documentación operativa: [flujo de cotizaciones](quote-workflow.md).

- Integración real SQL/HTTP y Chrome: **54 comprobaciones aprobadas**, incluyendo los cuatro orígenes de captura manual y el sitio web, ausencia de duplicados, consulta y respuesta del cliente, cálculo financiero, verificación con existencias, rechazo de emisión sin verificar, PDF real, registro de entrega de la versión emitida y denegación al editor de páginas. Limpieza aislada completada. Evidencia: `tests/artifacts/m11-workflow-sql-pass.json`.
- Verificación final: sintaxis PHP 421 archivos, JavaScript 38 archivos, 1453 comprobaciones y compilación local aprobadas.
- Actualización de desarrollo aplicada: **69 migraciones y 95 tablas**. Se conservaron los registros de las 92 tablas previas de negocio y acceso; solo cambió el historial de migraciones. Las dos tablas nuevas quedaron vacías; no se crearon usuarios ni datos sintéticos en desarrollo.
- Respaldo privado previo: `pre-quote-workflow-20261105-025611.json`, SHA-256 `7b2e2693f7527aeaf84ba26c8777057aa34256040f751138c54028d1f639d448`. El actualizador verificó el respaldo y comparó los registros previos después de migrar.
- Alcance pendiente: envío automático por SMTP/WhatsApp. La interfaz genera el PDF y registra una entrega manual confirmada.


## Condiciones para cotizaciones — 2026-11-04

- Biblioteca por sucursal con contexto de uso, vista previa, filtros y estados de disponibilidad; creación y edición en modal compartido. [Comportamiento documentado](terms-design.md).
- Navegador con servidor de páginas de prueba: **30 comprobaciones aprobadas** en anchos 360, 768, 1440 y 2560; creación y edición modal, cancelación, respuesta 422 con valores conservados, confirmación de guardado, filtros, paginación, texto escapado y formulario nativo sin JavaScript. Evidencia: `tests/artifacts/terms-design/pass.json`. No se escribieron datos en la base.
- Regresión de editores CMS: **128 comprobaciones aprobadas** tras ampliar las rutas del modal.
- Verificación local: **1453 comprobaciones**, sintaxis PHP de **424 archivos**, sintaxis JavaScript de **39 archivos** y compilación de assets aprobadas. No se modificaron tablas ni reglas de guardado comercial.
