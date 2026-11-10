# M13 · Reportes, exportación e importación

Estado al 10 de noviembre de 2026: implementación funcional completa en
código; validación local aprobada. La migración y las pruebas destructivas de
integración no se ejecutaron porque las credenciales de las bases de prueba
configuradas no aceptaron conexión. La base de desarrollo sólo se consultó con
alcance vacío y no fue modificada.

## Arquitectura y permisos

- ReportFilters normaliza tipo, periodo máximo de 731 días, sucursal, estado,
  canal y búsqueda. La misma estructura alimenta pantalla, XLSX y PDF.
- PdoReportRepository aplica paginación, parámetros preparados y alcance de
  sucursal en cada consulta. Transferencias consideran origen o destino.
- reports.view, reports.export y catalog.import separan consulta, descarga y
  escritura. Cada petición revalida sesión y permiso.
- La migración 20261110130000_register_reporting_permissions.php es aditiva,
  asigna los permisos a GLOBAL_ADMIN y protege el rollback si otro rol ya los
  utiliza. No crea tablas comerciales duplicadas.

## Reportes

| Reporte | Fecha base | Contenido |
| --- | --- | --- |
| Cotizaciones | quotes.created_at | estados reales M08, canal M11, sucursal, antigüedad y total documental estimado |
| Inventario | saldo actual M05 | físico, apartado, disponible y mínimo configurado |
| Compras | supplier_invoices.document_date | proveedor, sucursal, estado y total documentado |
| Transferencias | transfers.created_at | origen, destino y estados reales M09 |
| Entregas | deliveries.created_at | pendientes, completadas, incidencias y seguimiento M10 |

El valor de cotizaciones nunca se presenta como ingreso, cobro o utilidad. El
reporte de inventario declara que es saldo actual y no una fotografía histórica.
Los resultados se limitan a 25 por página y las exportaciones a 10,000 filas.

## XLSX y PDF

XlsxWriter genera un paquete OOXML .xlsx sin Composer, macros, fórmulas ni
contenido activo. Todas las celdas son texto y los valores que comienzan con
=, +, - o @ reciben un apóstrofo defensivo. El archivo se genera en memoria con
límite de filas y conserva UTF-8.

ReportPdf genera PDF 1.4 A4 horizontal, identidad tipográfica disponible, fecha
local, alcance, criterio temporal, encabezado y paginación. Utiliza fuentes
estándar y no incorpora imágenes ni servicios externos.

## Importación CSV

La primera versión acepta CSV UTF-8 de hasta 2 MB y 1,000 filas. La plantilla
contiene product_code, product_name, description, category_code, brand_code,
sku, variant_name y unit_code.

La vista previa valida extensión, MIME, columnas, cantidad, reglas M03,
duplicados internos y existentes, y catálogos activos. Se almacena fuera de
public con modo 0600, hash SHA-256, usuario propietario y caducidad de 30
minutos. La confirmación obtiene bloqueo exclusivo no bloqueante, verifica hash,
usuario, caducidad y estado, y vuelve a analizar contra la base. El alta de
producto y primera variante se ejecuta en una única transacción; cualquier
fallo revierte todo. Los registros se crean inactivos. No se crean catálogos,
precios, saldos ni movimientos.

## Validación ejecutada

- npm test: 1,537 comprobaciones aprobadas.
- Sintaxis de todos los PHP: aprobada.
- npm run build: aprobado con Tailwind 4.3.3.
- unzip -t: las seis entradas del XLSX son íntegras; file lo reconoce como
  Microsoft Excel 2007+.
- pdfinfo: PDF 1.4 válido, A4 horizontal, tres páginas, sin JavaScript.
- Las cinco consultas se ejecutaron contra MariaDB 10.11.15 con alcance vacío:
  SQL válido y cero datos leídos.
- npm run test:mysql:m07: no ejecutado con éxito; la conexión aislada falló
  incluso fuera del sandbox. No se sustituyó por la base de desarrollo.

## Producción

Antes de habilitar M13 se debe aplicar la migración con el proceso habitual,
asignar permisos a roles no globales y ejecutar integración y navegador sobre
una base aislada restaurable. storage/private/imports debe permanecer fuera del
document root y escribible por PHP.
