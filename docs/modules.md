# Catálogo de módulos

Este archivo es el índice para solicitar trabajo a Codex. Los identificadores
son estables: una tarea debe mencionar el módulo, sus fuentes y el incremento
concreto que se desea implementar.

Estado general al 4 de noviembre de 2026: la Home demostrativa está aprobada.
M00 y M01.1–M01.4 están implementados y pasan sus pruebas aisladas e integración
real en MariaDB 10.11. La ejecución M01 fallida fue respaldada, restaurada en un
destino temporal y recuperada de forma controlada; la repetición completa pasó
con trabajadores independientes y dejó la base de pruebas vacía. Las siete
migraciones están aplicadas en desarrollo sin semillas. M02 está integrado y
pasa pruebas PHP, build, MariaDB y navegador autenticado con una cuenta de
integración exclusiva. Sus ocho migraciones están aplicadas en desarrollo, el
Administrador global inicial existe y las pruebas MariaDB/HTTP dejan la base
aislada vacía. M03 está integrado en MariaDB, migrado en desarrollo y aprobado
con flujos HTTP autenticados. M04 está integrado, activado en desarrollo y pasa
MariaDB y HTTP autenticado. M05 también está integrado, migrado y aprobado con
concurrencia real y HTTP autenticado. M06 también está integrado, migrado y
aprobado con recepciones parciales, idempotencia y concurrencia real. M07 está integrado. M08 administrativo está activado en MySQL Docker local, con 53 migraciones y SQL/HTTP PASS. M09 está activado en MySQL Docker local con 57 migraciones, SQL/HTTP y respaldo restaurado verificados. M10 de entregas está activado en MySQL local con 61 migraciones y SQL/HTTP PASS. M11 está implementado, probado y migrado en desarrollo con 68 migraciones; su publicación pública permanece desactivada hasta cargar contenido real. Cierre en integration-m11.md. El primer incremento vertical M12 está implementado y probado en base aislada con incidencias M10; no se ha migrado a desarrollo. M13–M14 continúan pendientes.

| ID | Módulo | Alcance principal | Depende de | Activación |
| --- | --- | --- | --- | --- |
| M00 | Persistencia | PDO, migraciones, transacciones y utilidades CLI | Core actual | Técnica, siempre activa |
| M01 | Sucursales y módulos | Sucursales, horarios, coordenadas y funciones habilitadas | M00 | Global y por sucursal |
| M02 | Acceso y auditoría | Usuarios, sesiones, roles, permisos, alcance por sucursal y bitácora | M00, M01 | Técnica, siempre activa |
| M03 | Catálogo | Categorías, marcas, unidades, productos, variantes, atributos, imágenes y códigos | M00, M01, M02 | Global y por sucursal |
| M04 | Precios e impuestos | Precio público, listas, reglas, impuestos, historial y autorizaciones | M01, M02, M03 | Global y por sucursal |
| M05 | Inventario | Existencias, apartados, mínimos, movimientos, conteos, lotes y series | M01, M02, M03 | Por sucursal |
| M06 | Entradas por factura | Proveedores, facturas, costos y entradas de inventario | M02, M03, M05 | Por sucursal |
| M07 | Clientes internos | Directorio, contactos y lista comercial; sin cuentas públicas | M01, M02, M04 | Por sucursal |
| M08 | Cotizaciones | Solicitud pública, bandeja, versiones, PDF, SMTP, descuentos y aceptación | M01–M05, M07 | Por sucursal |
| M09 | Transferencias | Solicitud, aprobación, tránsito y recepción entre sucursales | M02, M05, M08 | Por sucursal |
| M10 | Entregas y devoluciones | Recolección, domicilio, parciales, vehículos, rutas, evidencia y devoluciones | M02, M05, M08 | Por sucursal |
| M11 | CMS público | Home, banners, promociones, eventos, testimonios, páginas, medios, SEO y legales | M01, M02 | Global; contenido asociable a sucursales |
| M12 | Notificaciones internas | Bandeja y avisos de acciones pendientes | M02 y módulo emisor | Global y por sucursal |
| M13 | Reportes e intercambio | AyalaTable, Excel/PDF, importaciones, exportaciones y códigos de barras | M02 y módulo origen | Según módulo y sucursal |
| M14 | Operación | Respaldos, restauración, tareas programadas, observabilidad y despliegues | M00–M13 | Técnica, siempre activa |

## Límites por módulo

### M00 · Persistencia

Incluye conexión PDO, registro/ejecutor de migraciones, transacciones, errores
seguros y pruebas. M00 pasó la integración en una base exclusiva autorizada y
confirmó limpieza final. No incluye tablas comerciales ni autenticación.

### M01 · Sucursales y módulos

Incluye altas ilimitadas de sucursales, datos de contacto, horarios,
geolocalización y habilitación de funciones. No incluye inventario ni mapas de
rutas de entrega.

Incrementos:

- **M01.1 integrado:** migraciones de
  `branches`, `modules` y `branch_modules`; validación de claves, nombres y
  coordenadas; estados deshabilitados por defecto y regla pura de
  disponibilidad. No hay semillas, rutas ni escrituras públicas.
- **M01.2 integrado:** horarios semanales y
  excepcionales, múltiples intervalos, cruces de medianoche, cierres por fecha,
  precedencia determinista y separación entre horario programado, habilitación
  operativa y disponibilidad pública. Las
  invariantes entre filas deberán revalidarse al escribir desde M01.4.
- **M01.3 integrado:** catálogo de servicios
  comerciales, configuración independiente por sucursal, desactivación lógica,
  orden determinista y regla pura de disponibilidad. No hay semillas,
  repositorios, rutas, medios ni publicación de datos comerciales.
- **M01.4 integrado:** contratos de repositorio,
  adaptadores PDO, servicios de aplicación, lecturas paginadas/acotadas,
  activación lógica y disponibilidad efectiva. Las escrituras de horarios
  bloquean la fila de sucursal, releen el calendario y validan antes del DML.
  No hay rutas administrativas; la concurrencia real pasó con conexiones
  independientes en MariaDB 10.11.

M01 está integrado para revisión final: esquema, repositorios, restricciones y
concurrencia pasaron en MariaDB 10.11. Ningún módulo del catálogo se inserta o
activa automáticamente.

### M02 · Acceso y auditoría

Incluye Administrador global, gerentes, Ventas, Inventario, Logística,
Administrador de catálogo y Administrador de la página. No incluye clientes
con acceso ni segundo factor obligatorio.

Integración completada el 25 de octubre de 2026: ocho migraciones aditivas para
usuarios/roles/permisos, asignaciones, sesiones y auditoría; creación inicial
exclusiva por CLI; login/logout, expiración y revocación; CSRF; límite de
intentos; autorización denegada por defecto y por sucursal; protección del
último administrador y contra escalamiento; panel de usuarios, roles,
sucursales y auditoría. La recuperación por correo se difiere hasta disponer de
SMTP seguro. El punto de control MariaDB y la prueba HTTP autenticada pasaron
con una identidad separada; desarrollo conserva quince migraciones acumuladas,
el catálogo de 17 permisos y un Administrador global creado interactivamente.

### M03 · Catálogo

Incluye árbol de categorías, productos/variantes, unidades y conversiones,
atributos dinámicos, galería, fichas, SKU y códigos de barras. No incluye
precios, existencias ni promociones comerciales.

Integración cerrada el 1 de noviembre de 2026: doce migraciones aditivas,
dominio, repositorio PDO, servicio transaccional y panel para categorías,
marcas, unidades, atributos, productos, variantes, códigos y conversiones. Los
permisos son globales y se agregan al Administrador global mediante migración.
La infraestructura de archivos y galería se reserva a M11: M03 no almacena
rutas arbitrarias ni ofrece subidas. Las integraciones aisladas MariaDB y HTTP
autenticada pasan con `TEST_DB_*_M03`; desarrollo conserva las doce migraciones
M03 en lote 3 y ningún registro comercial fue insertado.

### M04 · Precios e impuestos

Incluye precios por variante/sucursal/lista, descuento general y excepciones,
tasas configurables, límites de vendedor y aprobación gerencial. No calcula
margen ni cambia precios automáticamente por costo.

Integración del 1 de noviembre de 2026: ocho migraciones aditivas,
aritmética decimal por cadenas, repositorio/servicio transaccional, historial,
versión optimista, vigencias sin solapamiento, permisos y panel. La precedencia
es excepción explícita de lista y, en su ausencia, precio público de la misma
sucursal menos la regla general vigente. Sin tasa efectiva no se devuelve un
cálculo. No se sembraron valores. MariaDB y HTTP autenticado pasan, desarrollo
conserva 35 migraciones en cuatro lotes y todas las tablas M04 están vacías.

### M05 · Inventario

Incluye físico, apartado, disponible, mínimos, movimientos, conteos, ajustes,
lotes, caducidad, series y merma. No incluye compras, ubicaciones internas ni
retazos individualizados.

Integración del 1 de noviembre de 2026: seis migraciones aditivas, cantidades
exactas, repositorio PDO, servicio transaccional, siete permisos y panel. Saldo
y libro se actualizan en una transacción con bloqueos estables e idempotencia.
MariaDB y HTTP autenticado pasaron, incluida concurrencia real y rollback por
timeout. Desarrollo conserva 41 migraciones y las siete tablas M05 están vacías.

### M06 · Entradas por factura

Incluye proveedor, serie/folio, partidas, cantidad, costo e historial de
recepciones. Cinco migraciones aditivas crean proveedores/contactos, facturas,
partidas y recepciones; siete permisos se asignan al Administrador global.
Guardar o confirmar una factura no altera inventario. Una recepción física
parcial o completa bloquea factura y partidas, comprueba el pendiente y compone
el movimiento M05, el saldo y la auditoría en una sola transacción. La clave de
operación impide reenvíos y la concurrencia real impide sobre-recepción.
MariaDB y HTTP autenticado pasan; desarrollo conserva 46 migraciones y las seis
tablas comerciales M06 están vacías. No incluye archivos M11, órdenes de
compra, pagos, cuentas por pagar, SAT ni contabilidad.

### M07 · Clientes internos

M07 integrado: clientes persona/empresa con código estable, sucursal,
datos fiscales opcionales declarados, contacto y dirección principal, estado
lógico y lista comercial M04 opcional. Las consultas se limitan por sucursal,
las escrituras usan transacción y versión optimista, y código/RFC tienen
unicidad SQL. Coincidencias de correo o teléfono sólo advierten. No existe
registro ni portal público; la integración histórica M07 está documentada en [su cierre](integration-m07.md).

### M08 · Cotizaciones

Alcance oficial: solicitud, toma por vendedor, versiones históricas, vigencia, PDF, SMTP, enlace de WhatsApp, autorizaciones, aceptación y cancelación; sin cobros, facturación ni ventas financieras.

Incremento administrativo activado el 3 de noviembre de 2026: borradores, cliente M07, variantes M03, motor M04, disponibilidad/apartados M05, revisiones, autorizaciones, emisión, PDF, estados e historial. SQL y HTTP PASS, 53 migraciones y nueve permisos. Solicitud pública y SMTP no se declaran implementados en este incremento; véanse [fronteras y cierre M08](integration-m08.md). M09 quedó integrado posteriormente; véase su cierre.

### M09 · Transferencias

Integrado y activado el 3 de noviembre de 2026: solicitudes, aprobación con apartado en origen, preparación, despachos y recepciones parciales, tránsito, incidencias, cobertura de cotizaciones y movimientos M05. Cuatro migraciones aditivas elevan el total a 57; diez permisos configurables por sucursal. SQL, concurrencia independiente, HTTP/Chrome y respaldo restaurado: PASS. No genera compras ni implementa M10. Alcance y límites de lotes/series: [integración M09](integration-m09.md).

### M10 · Entregas (devoluciones pendientes)

Incluye recolección/domicilio, entregas parciales, rutas manuales, vehículos
propios, repartidores sin acceso y evidencia. El código vigente no implementa
devoluciones; ese flujo necesita un incremento independiente y reglas de
disposición física aprobadas. No incluye GPS continuo ni mantenimiento de
flotilla.

### M11 · CMS público

Incluye formularios estructurados, reglas de imágenes, publicación, navegación,
mapa de sucursales, SEO, aviso de privacidad versionado y condiciones de
cotización. No incluye constructor libre de páginas.

### M12 · Notificaciones internas

Primer incremento integrado el 10 de noviembre de 2026: una incidencia M10
confirmada crea, dentro de la misma transacción, un evento persistente y una
entrega por cada usuario activo con `deliveries.view` y
`deliveries.incident` en la sucursal. Campana, contador, resumen, historial,
filtros, paginación y lectura revalidan el permiso actual del recurso. Las
restricciones únicas protegen evento y destinatario incluso con productores
concurrentes. No envía correo, SMS o WhatsApp y no cambia el estado del
documento de negocio. Arquitectura, rutas, pruebas y límites:
[integración M12](integration-m12.md).

### M13 · Reportes e intercambio

Incluye consulta paginada en servidor, filtros, Excel/PDF, importaciones con
vista previa e impresión/lectura de códigos. Toda acción respeta permisos y
sucursales.

### M14 · Operación

Incluye local, minilab y producción, respaldos diarios por 30 días, mensuales
por 12 meses, restauración probada y tareas programadas. Despliegues y secretos
requieren autorización separada.

## Orden recomendado de implementación

1. M00: persistencia.
2. M01 y M02: sucursales, módulos, acceso y auditoría.
3. M03 y la base de M11: catálogo, medios y contenido administrable.
4. M04 y M05: precios e inventario.
5. M06 y M07: entradas por factura y clientes internos.
6. M08 y M09: cotizaciones, apartados y transferencias.
7. M10: entregas y devoluciones.
8. M12 y M13: notificaciones, reportes e intercambio.
9. M14: endurecimiento operativo, respaldos y despliegue.

Cada paso debe dividirse en incrementos pequeños con migraciones, pruebas y un
criterio de aceptación observable. Terminar un módulo no autoriza comenzar el
siguiente automáticamente.

## Infraestructura M14 iniciada · 8 de noviembre de 2026

Núcleo durable de trabajos implementado con CLI privado, origen
manual/programado/reintento, historial por intento, reclamación concurrente,
leases con token aleatorio, latidos, recuperación de interrupciones, reintento exponencial, intervención e
idempotencia de cola. Las comprobaciones aisladas y la suite SQL multiproceso pasan en MariaDB 10.11.15: 50 aserciones, 71 migraciones y 60 reclamaciones concurrentes con rollback vacío. M12 usa consistencia transaccional directa para su primer evento y no encola un trabajo innecesario; SMTP
y respaldos aún no están implementados. Véase [operación de trabajos](operation-jobs.md).

## Cómo solicitar un módulo a Codex

Ejemplo:

```text
Trabaja únicamente en M00 · Persistencia.

Antes de modificar código lee AGENTS.md, docs/modules.md,
docs/requirements.md, docs/data-model.md, docs/architecture.md y
docs/roadmap.md. Inspecciona además config/database.php, bootstrap/app.php,
app/Core/, .env.example y tests/run.php.

Haz primero una inspección de solo lectura. Presenta alcance, archivos,
migraciones, pruebas y riesgos. No implementes M01 ni módulos posteriores.
Espera mi aprobación antes de escribir código.
```

Para otro módulo se sustituye el identificador y se agregan sus componentes
actuales. Los requisitos detallados permanecen en `requirements.md`; este índice
no los reemplaza.

## Incremento M10 · Entregas

Implementación de entregas parciales desde cotizaciones aceptadas, preparación, asignación logística, salida física contra apartados M08/M09 y confirmación del cliente. Incluye incidencias, cancelación previa a salida, vehículos, repartidores y rutas manuales. Cuatro migraciones aditivas; el cierre verificable de SQL, concurrencia, HTTP, responsive y activación se registra en [integración M10](integration-m10.md). Los resultados anteriores se conservan como historia; no se trasladan automáticamente a este incremento.

Cierre M10 confirmado: 61 migraciones, nueve tablas InnoDB/utf8mb4, 23 FKs restrictivas, 20 índices únicos y once permisos GLOBAL_ADMIN. npm test: 1168 comprobaciones; PHP/JS/build/diff, SQL y Chrome M10, regresiones SQL/HTTP M08–M09 y respaldo restaurado PASS. Las 63 tablas previas conservaron sus datos; base aislada vacía y sin fixtures en desarrollo. Los pendientes se calculan en persistencia y las vistas reciben arrays explícitos. Siguiente módulo oficial M11, sin iniciarlo.


## Incremento M11 · CMS público

Contenido y publicación estructurados, biblioteca raster segura, galerías/fichas M03, comprobantes privados M06, fotografías de confirmaciones/incidencias M10, aviso versionado y plantillas por sucursal. Recepción pública real M08 autorizada con consentimiento y antispam, sin SMTP. Detalles y resultados actuales: [integración M11](integration-m11.md). Siguiente módulo oficial M12, sin iniciarlo.

Cierre M11 confirmado: dominio 1235, SQL 71 y HTTP/Chrome 80 comprobaciones registradas; sintaxis PHP/JS y build PASS; regresiones SQL/HTTP M08–M10 PASS. Respaldo restaurado y conservación de las 72 tablas anteriores verificados. Hay 93 tablas, trece permisos M11 y cero fixtures en las 21 tablas nuevas. El incremento vertical M12 posterior se documenta por separado.
