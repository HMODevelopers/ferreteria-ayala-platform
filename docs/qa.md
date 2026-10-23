# Verificación de la plataforma

Fecha de última ejecución local: 23 de octubre de 2026. Entorno: Ubuntu, Node 22.22.1, npm 9.2.0, PHP CLI 8.5.4 de 64 bits y MariaDB 10.11.15. El código utiliza sintaxis compatible con PHP 8.2; no se ejecutó una matriz de versiones 8.2/8.3/8.4. PDO, `pdo_mysql` y `proc_open` están disponibles.

## Ejecutado

- Sintaxis de **106 archivos PHP**: sin errores.
- Pruebas PHP `tests/run.php`: **537 comprobaciones** correctas. Las nuevas
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
- Las siete migraciones quedaron aplicadas en lote 1 sobre `ferreteria_ayala`;
  sus siete tablas comerciales permanecen vacías y no se cargaron semillas.
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

El DDL de MariaDB conserva commits implícitos; las guardas reducen el riesgo,
pero una caída durante un rollback futuro todavía exigiría inspección manual.
Continúan sin probarse Apache/cPanel real, FTP, TLS, instalación en subdominio,
iOS Safari, Android real ni lectores de pantalla. No se confirma stock, precios
ni ningún flujo administrativo porque no existen en esta fase.

## Revisión de aprobación

La Home fue aprobada como base el 17 de octubre de 2026. Permanecen pendientes
el logo oficial, fotos reales y datos de contacto verificados para reemplazar
los placeholders. M00 y M01.1–M01.4 están integrados y listos para revisión
final; las tablas de desarrollo están aplicadas sin datos comerciales. El
administrador y M02–M14 permanecen fuera del alcance.
