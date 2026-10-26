# Roadmap

## Fase 1 · Home pública (aprobada)

MVC, componentes, diseño responsive, contenido demo, búsqueda y lista local descargable; verificación y guía de cPanel. La Home fue aprobada como base el 17 de octubre de 2026. Esta aprobación permite iniciar el levantamiento y diseño de persistencia; no confirma datos comerciales demostrativos como datos reales.

## Fase 2 · Requisitos y contenido real (levantamiento aprobado)

Confirmar logo y manual, fotografías autorizadas, contactos, redes, privacidad, productos/unidades/marcas, precios/impuestos, sucursales y flujo de cotización. Aprobar contratos y esquema de datos junto con el negocio. Preparar SEO/indexación del dominio definitivo.

Las decisiones funcionales están consolidadas en [requisitos](requirements.md) y el diseño de persistencia en [modelo de datos](data-model.md). Permanecen pendientes los archivos y valores reales que debe entregar el negocio.

## Fase 3 · Persistencia y administración (M00 + M01 + M02 integrados)

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
Productos, categorías, marcas y el CMS continúan sin implementarse.

## Fase 4 · Cotizaciones e inventario

Solicitudes públicas, versiones de cotización, clientes internos opcionales, precios, apartados, inventario por sucursal, transferencias, entradas por factura de proveedor, entregas y devoluciones. No incluye ventas financieras, pagos, facturación, contabilidad ni portal de clientes.

## AyalaTable (administración futura)

Componente propio, sin DataTables. Paginación server-side, búsqueda, ordenamiento, filtros avanzados, selección múltiple, acciones masivas, columnas configurables, exportaciones, carga y actualizaciones Fetch. Diseñar contrato de respuesta paginada y accesibilidad antes de programarlo; validar permisos de cada acción, límites y filtros en servidor. No hay código de AyalaTable en esta entrega.

## Fase 5 · Operación y calidad

Dashboard y estadísticas basadas en datos reales; copias de seguridad, restauración, rendimiento, accesibilidad formal, límites de solicitudes y observabilidad. Pagos, facturación y contabilidad requieren proyectos aprobados aparte.
