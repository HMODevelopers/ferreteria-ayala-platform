# Roadmap

## Fase 1 · Home pública (aprobada)

MVC, componentes, diseño responsive, contenido demo, búsqueda y lista local descargable; verificación y guía de cPanel. La Home fue aprobada como base el 17 de octubre de 2026. Esta aprobación permite iniciar el levantamiento y diseño de persistencia; no confirma datos comerciales demostrativos como datos reales.

## Fase 2 · Requisitos y contenido real (levantamiento aprobado)

Confirmar logo y manual, fotografías autorizadas, contactos, redes, privacidad, productos/unidades/marcas, precios/impuestos, sucursales y flujo de cotización. Aprobar contratos y esquema de datos junto con el negocio. Preparar SEO/indexación del dominio definitivo.

Las decisiones funcionales están consolidadas en [requisitos](requirements.md) y el diseño de persistencia en [modelo de datos](data-model.md). Permanecen pendientes los archivos y valores reales que debe entregar el negocio.

## Fase 3 · Persistencia y administración (M00–M07 integrados)

M00 y M01.1–M01.4 están implementados y pasan pruebas aisladas e integración
real en MariaDB 10.11. La primera ejecución M01 falló porque un hijo de
`pcntl_fork()` heredó la conexión PDO del padre. El estado sintético exacto fue
respaldado, restaurado en una base temporal y recuperado con el ejecutor
protegido. La repetición usa procesos independientes y demostró restricciones,
repositorios, transacciones, serialización de horarios, timeout y deadlock.

La base exclusiva de pruebas quedó vacía. Las siete migraciones permanecen
aplicadas en `ferreteria_ayala` sin semillas ni datos comerciales. M01 no expone
rutas públicas o administrativas y no activa módulos automáticamente.

M02 implementa usuarios, roles, permisos, alcance por sucursal,
sesiones/CSRF, límite de login, auditoría, panel y operaciones iniciales de M01.
Pasa pruebas PHP, build, integración MariaDB y navegador autenticado. Existe una
cuenta M02 de pruebas separada con privilegios mínimos; la base aislada queda
vacía. Desarrollo tiene quince migraciones y un Administrador global activo.
El CMS y los módulos posteriores continúan sin implementarse.

Actualización del 1 de noviembre de 2026: M03 está integrado con catálogo
administrable, doce migraciones aditivas y 723 comprobaciones PHP acumuladas.
Las suites MariaDB y HTTP autenticada pasan con una cuenta limitada propia, las
migraciones están aplicadas en desarrollo y el catálogo comercial permanece
vacío. M04 está integrado con 797 comprobaciones, build, MariaDB y navegador
autenticado aprobados. Sus ocho migraciones están aplicadas en desarrollo como
lote 4, para 35 acumuladas; las tablas comerciales M04 permanecen vacías. M05
quedó integrado con seis migraciones adicionales, saldos y movimientos por
sucursal-variante, permisos, panel, concurrencia real y activación en
desarrollo. M06 quedó integrado el 2 de noviembre con proveedores, facturas,
partidas y recepciones parciales/completas coordinadas con M05. Sus suites
MariaDB y HTTP autenticada aprobaron idempotencia, rollback y concurrencia real;
la base de pruebas terminó vacía. Desarrollo conserva 46 migraciones y no se
cargaron proveedores, facturas ni existencias. M07 quedó integrado el 2 de
noviembre con una identidad MariaDB exclusiva, 966 comprobaciones locales,
integración y HTTP/Playwright autenticado en cuatro anchos. La base de pruebas
terminó vacía; desarrollo se respaldó, conserva 48 migraciones y no contiene
clientes ficticios. M08 y módulos posteriores no se iniciaron.

## Fase 4 · Cotizaciones e inventario

Solicitudes públicas, versiones de cotización, clientes internos opcionales, precios, apartados, inventario por sucursal, transferencias, entradas por factura de proveedor, entregas y devoluciones. No incluye ventas financieras, pagos, facturación, contabilidad ni portal de clientes.

## AyalaTable (administración futura)

Componente propio, sin DataTables. Paginación server-side, búsqueda, ordenamiento, filtros avanzados, selección múltiple, acciones masivas, columnas configurables, exportaciones, carga y actualizaciones Fetch. Diseñar contrato de respuesta paginada y accesibilidad antes de programarlo; validar permisos de cada acción, límites y filtros en servidor. No hay código de AyalaTable en esta entrega.

## Fase 5 · Operación y calidad

Dashboard y estadísticas basadas en datos reales; copias de seguridad, restauración, rendimiento, accesibilidad formal, límites de solicitudes y observabilidad. Pagos, facturación y contabilidad requieren proyectos aprobados aparte.

Actualización del 8 de noviembre de 2026: se implementó, sin migrar desarrollo,
el núcleo mínimo de trabajos requerido por M12/M14. Incluye cola durable,
historial, CLI/cron, concurrencia, leases protegidos, latidos, recuperación, reintentos e
idempotencia. La suite local pasa y la suite SQL multiproceso aprobó contra MariaDB 10.11.15 con 60 reclamaciones concurrentes, recuperación y rollback aislado.
El núcleo queda operativo en minilab. El primer incremento vertical M12 se
implementó posteriormente sin usar la cola para el evento transaccional de
incidencia; SMTP, devoluciones, M13 y respaldos continúan no iniciados; detalle en
[operación de trabajos](operation-jobs.md).

## Incremento administrativo M08 · 3 de noviembre de 2026

Cotizaciones con borradores, versiones, M04, cliente M07, PDF, aceptación y apartado M05 implementados. El entorno vigente es Docker local con MySQL 8.4, confirmado por el propietario. Cierre de pruebas/activación: [integración M08](integration-m08.md). No se inicia M09. Solicitud pública y SMTP conservan las fronteras descritas allí.

## Incremento M09 · 3 de noviembre de 2026

Transferencias entre sucursales con aprobación/apartado, preparación, despacho, tránsito y recepción. La cobertura de faltantes se integra con M08 sin cambiar versiones comerciales ni disponer de apartados ajenos. Estado verificado y fronteras en [integración M09](integration-m09.md). El siguiente módulo es M10, sin implementación dentro de este encargo.

Cierre M09 confirmado: SQL/HTTP, respaldo restaurado y activación en desarrollo PASS; 57 migraciones, sin datos ficticios. M10 permanece pendiente de un encargo independiente.

## Incremento M10 · Entregas

Implementación de entregas parciales desde cotizaciones aceptadas, preparación, asignación logística, salida física contra apartados M08/M09 y confirmación del cliente. Incluye incidencias, cancelación previa a salida, vehículos, repartidores y rutas manuales. Cuatro migraciones aditivas; el cierre verificable de SQL, concurrencia, HTTP, responsive y activación se registra en [integración M10](integration-m10.md). Los resultados anteriores se conservan como historia; no se trasladan automáticamente a este incremento.

Cierre M10 confirmado: 61 migraciones, nueve tablas InnoDB/utf8mb4, 23 FKs restrictivas, 20 índices únicos y once permisos GLOBAL_ADMIN. npm test: 1168 comprobaciones; PHP/JS/build/diff, SQL y Chrome M10, regresiones SQL/HTTP M08–M09 y respaldo restaurado PASS. Las 63 tablas previas conservaron sus datos; base aislada vacía y sin fixtures en desarrollo. Los pendientes se calculan en persistencia y las vistas reciben arrays explícitos. Siguiente módulo oficial M11, sin iniciarlo.


## Incremento M11 · CMS público · 4 de noviembre de 2026

Implementación y activación administrativa verificadas: contenido estructurado, publicación, medios raster, legales versionados, plantillas por sucursal, galerías M03, comprobantes M06, evidencias M10 y recepción pública real M08 con consentimiento/antispam. Desarrollo conserva 68 migraciones, 93 tablas y ningún fixture M11. Respaldo restaurado, dominio/SQL/HTTP/responsive y las seis regresiones M08–M10 aprobaron. La publicación pública sigue apagada hasta cargar insumos reales y habilitarla explícitamente. Cierre y límites: [integración M11](integration-m11.md). Siguiente módulo oficial M12; no se inicia.

## Incremento M12 · Notificaciones internas · 10 de noviembre de 2026

Primer evento vertical implementado: la incidencia formal de una entrega M10.
Evento, destinatarios y operación confirman o revierten juntos; los usuarios
destino deben conservar permiso de consulta sobre el recurso para verlo o
marcarlo. El panel ofrece campana, contador, recientes, historial, filtros,
paginación y lectura individual con CSRF. La suite aislada aprobó 27 aserciones,
71 migraciones y cinco productores concurrentes; la regresión del núcleo de
trabajos aprobó 50 aserciones y 60 reclamaciones. Chrome headless verificó
360/768/1440 px, teclado, polling, lectura, navegación y vacío. No se migró
desarrollo ni se implementó SMTP. Cierre: [integración M12](integration-m12.md).

## Incremento M13 · Reportes e intercambio · 10 de noviembre de 2026

Implementación en código terminada: centro de reportes con cinco áreas,
aislamiento por sucursal, paginación y filtros compartidos por UI/XLSX/PDF.
Importación CSV de productos básicos con vista previa, catálogos existentes,
confirmación ligada al usuario y transacción atómica. La suite local, sintaxis,
build y validadores reales de XLSX/PDF pasan. Las consultas SQL fueron
ejecutadas en MariaDB 10.11.15 con alcance vacío. La suite aislada y Chrome
autenticado están pendientes por indisponibilidad de las credenciales de
prueba; desarrollo no se migró. Véase [integración M13](integration-m13.md).
M14 no se desarrolla en este incremento.

## Devoluciones operativas · 10 de noviembre de 2026

Incremento independiente implementado para devoluciones de clientes basadas en
recepciones M10 y devoluciones a proveedor basadas en recepciones M06. Incluye
74 migraciones acumuladas, siete permisos, cantidades `DECIMAL`, estados,
historial, auditoría, alcance conjunto sobre devolución y documento fuente,
idempotencia y movimientos M05 únicamente al confirmar el hecho físico. La
suite PHP (1576), build y Chrome en 360/768/1440/2560 px aprobaron. MariaDB
aislada está bloqueada por rechazo de la identidad configurada; M05 reproduce
el mismo fallo. No se migró desarrollo ni se inició M14. Cierre provisional:
[integración de devoluciones](integration-returns.md).
