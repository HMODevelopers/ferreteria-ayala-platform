# Catálogo de módulos

Este archivo es el índice para solicitar trabajo a Codex. Los identificadores
son estables: una tarea debe mencionar el módulo, sus fuentes y el incremento
concreto que se desea implementar.

Estado general al 18 de octubre de 2026: la Home demostrativa está aprobada.
M00 tiene implementación y pruebas aisladas, pero permanece **pendiente de
integración MariaDB real**; M01–M14 no están implementados.

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
seguros y pruebas. La implementación local existe, pero M00 no se considera
completo hasta ejecutar satisfactoriamente la integración en una base exclusiva
autorizada. No incluye tablas comerciales ni autenticación.

### M01 · Sucursales y módulos

Incluye altas ilimitadas de sucursales, datos de contacto, horarios,
geolocalización y habilitación de funciones. No incluye inventario ni mapas de
rutas de entrega.

### M02 · Acceso y auditoría

Incluye Administrador global, gerentes, Ventas, Inventario, Logística,
Administrador de catálogo y Administrador de la página. No incluye clientes
con acceso ni segundo factor obligatorio.

### M03 · Catálogo

Incluye árbol de categorías, productos/variantes, unidades y conversiones,
atributos dinámicos, galería, fichas, SKU y códigos de barras. No incluye
precios, existencias ni promociones comerciales.

### M04 · Precios e impuestos

Incluye precios por variante/sucursal/lista, descuento general y excepciones,
tasas configurables, límites de vendedor y aprobación gerencial. No calcula
margen ni cambia precios automáticamente por costo.

### M05 · Inventario

Incluye físico, apartado, disponible, mínimos, movimientos, conteos, ajustes,
lotes, caducidad, series y merma. No incluye compras, ubicaciones internas ni
retazos individualizados.

### M06 · Entradas por factura

Incluye proveedor, folio, partidas, cantidad, costo, archivo privado opcional y
alerta de cambio de costo. No incluye órdenes de compra, pagos, cuentas por
pagar ni contabilidad.

### M07 · Clientes internos

Incluye datos de clientes frecuentes y asignación de lista comercial. El
contacto de una solicitud puede quedar sin convertirse en cliente. No existe
registro ni portal público.

### M08 · Cotizaciones

Incluye solicitud, toma por vendedor, versiones históricas, vigencia, PDF,
correo SMTP, enlace de WhatsApp, autorizaciones, aceptación y cancelación. No
incluye cobros, facturación ni ventas financieras.

### M09 · Transferencias

Incluye faltantes de cotizaciones y movimientos entre sucursales con aprobación
configurable. No genera compras cuando la red completa carece de existencia.

### M10 · Entregas y devoluciones

Incluye recolección/domicilio, entregas parciales, rutas manuales, vehículos
propios, repartidores sin acceso, evidencia y devoluciones. No incluye GPS
continuo ni mantenimiento de flotilla.

### M11 · CMS público

Incluye formularios estructurados, reglas de imágenes, publicación, navegación,
mapa de sucursales, SEO, aviso de privacidad versionado y condiciones de
cotización. No incluye constructor libre de páginas.

### M12 · Notificaciones internas

Incluye avisos dentro del panel. No envía notificaciones internas por correo,
SMS o WhatsApp.

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
