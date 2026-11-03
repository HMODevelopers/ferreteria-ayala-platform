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

## Incremento administrativo M08 · 3 de noviembre de 2026

Cotizaciones con borradores, versiones, M04, cliente M07, PDF, aceptación y apartado M05 implementados. El entorno vigente es Docker local con MySQL 8.4, confirmado por el propietario. Cierre de pruebas/activación: [integración M08](integration-m08.md). No se inicia M09. Solicitud pública y SMTP conservan las fronteras descritas allí.
