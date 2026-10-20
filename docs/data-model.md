# Modelo de datos propuesto

Estado: **diseño funcional levantado** el 17 de octubre de 2026. La Home está
aprobada y el alcance se detalla en [requisitos funcionales](requirements.md).
M00 incorpora la conexión PDO diferida y el ejecutor de migraciones. M01.1
define localmente las migraciones de `branches`, `modules` y `branch_modules`.
M01.2 añade las definiciones de horarios regulares y excepcionales, pero
ninguna tabla comercial se ha aplicado a MariaDB/MySQL. Como
antecedente informado, la conexión al minilab y la inicialización de la tabla
técnica `schema_migrations` se verificaron previamente en desarrollo; no se
repitieron en la corrección actual. La integración completa en una base de
pruebas exclusiva y autorizada permanece pendiente.

## Reglas de persistencia

- MySQL mediante PDO y consultas preparadas; `utf8mb4` en todas las tablas.
- Claves primarias numéricas y slugs únicos donde exista URL pública.
- Importes en `DECIMAL` y moneda única MXN; cantidades en `DECIMAL` para
  artículos fraccionables.
- Fechas consistentes y presentación en `America/Hermosillo`.
- Llaves foráneas e índices explícitos. Los registros con historial se archivan
  o revierten; no se borran físicamente desde la operación normal.
- Las vistas reciben arrays explícitos. Repositorios y servicios concentran
  consultas y reglas comerciales.
- Los registros demo actuales no serán semillas de producción.
- La infraestructura aplicada usa únicamente `schema_migrations` para
  identificador, nombre, lote y fecha de aplicación. Las primeras tablas de
  negocio están definidas en archivos de migración M01.1, no aplicadas.

## Áreas y tablas previstas

### Plataforma, acceso y sucursales

- `branches` (**M01.1 definido, no aplicado**): clave comercial normalizada,
  nombre, dirección y contacto opcionales, coordenadas decimales emparejadas,
  estado y fechas técnicas. Las altas nacen inactivas.
- `modules` (**M01.1 definido, no aplicado**): código `M00`–`M99`, nombre,
  descripción opcional, habilitación global, requisito por sucursal y fechas.
- `branch_modules` (**M01.1 definido, no aplicado**): clave primaria compuesta,
  referencias restrictivas a sucursal/módulo, estado y fechas. La ausencia de
  relación equivale a deshabilitado para módulos que la requieren.
- `branch_hours` (**M01.2 definido, no aplicado**): intervalos semanales por
  sucursal y día ISO 1–7, hora inicial/final, cruce de medianoche explícito y
  orden. Días sin filas están cerrados. Claves únicas evitan repetir orden o
  intervalo, y la FK restrictiva conserva horarios al desactivar sucursales.
- `branch_special_hours` (**M01.2 definido, no aplicado**): excepción por fecha
  local. Un cierre usa una sola fila canónica (`is_closed=1`, horas nulas,
  `interval_order=0`); una apertura usa uno o más intervalos explícitos. La
  excepción de una fecha reemplaza el horario regular y cualquier arrastre del
  día previo durante ese día civil.
- `users`, `roles`, `permissions`, `user_roles`, `role_permissions`,
  `user_branches`: acceso por acción y sucursal.
- `user_sessions`, `password_reset_tokens`: sesiones y recuperación.
- `notifications`: avisos internos del panel.
- `audit_logs`: actor, acción, entidad, identificador, fecha y contexto seguro.

Perfiles iniciales: Administrador global, gerente/administrador de sucursal,
Ventas, Inventario, Logística, Administrador de catálogo y Administrador de la
página. Los permisos son configurables y una persona puede operar en más de una
sucursal.

### Catálogo

- `categories`: árbol mediante `parent_id`.
- `brands`, `units`: marcas, unidades base y precisión.
- `products`: ficha común, categoría principal, marca y estado.
- `product_variants`: SKU vendible y presentación.
- `product_barcodes`: uno o varios códigos por variante/presentación.
- `product_images`: galería, imagen principal, orden y texto alternativo.
- `attribute_definitions`, `attribute_options`, `category_attributes`,
  `variant_attribute_values`: especificaciones y filtros configurables.
- `unit_conversions`: compra o captura en caja/rollo/tramo y control en la
  unidad base.

Cada variante tiene SKU, código de barras, unidad, precio e inventario propios.
La cantidad puede ser entera o decimal según la unidad. Lotes, caducidades y
series se activan por producto, no globalmente.

### Precios e impuestos

- `tax_categories`, `tax_rates`: tasas configurables y vigencia.
- `price_lists`: público, contratista, mayorista u otras listas.
- `price_list_rules`: descuento general de una lista.
- `branch_variant_prices`: precio por sucursal, variante y lista, incluida la
  excepción por producto.
- `price_history`: valor anterior/nuevo, responsable y fecha.
- `discount_policies`: límite del vendedor y autorización superior.

El Administrador global controla si el público ve precios. Los precios pueden
diferir por sucursal. Una cotización siempre conserva un snapshot de precio,
impuesto y descuento; los cambios posteriores no modifican su historial.

### Inventario

- `inventory_balances`: existencia física, apartada y disponible por variante,
  sucursal y, cuando aplique, lote.
- `inventory_movements`: libro inmutable de entradas, salidas, ajustes,
  transferencias, apartados liberados y devoluciones.
- `stock_thresholds`: mínimo por producto y sucursal.
- `stock_counts`, `stock_count_items`: conteos y diferencias.
- `inventory_lots`, `inventory_serials`: rastreo opcional.
- `inventory_reservations`, `inventory_reservation_items`: apartados originados
  por cotizaciones aceptadas.
- `inventory_transfers`, `inventory_transfer_items`: solicitud, aprobación,
  tránsito, recepción y cancelación entre sucursales.
- `inventory_returns`, `inventory_return_items`: devolución vendible, dañada,
  en revisión o a proveedor.

`disponible = físico - apartado`. Al aceptar una cotización se reduce el
disponible inmediatamente. La entrega no vuelve a descontar el disponible: crea
la salida física contra el apartado. Cancelar libera únicamente lo no entregado.

No se controlan ubicaciones internas, pasillos ni retazos individuales. La
merma se registra como movimiento justificado.

### Entradas por factura de proveedor

- `suppliers`, `supplier_contacts`: identificación básica del proveedor.
- `supplier_invoices`, `supplier_invoice_items`: folio, fecha, sucursal,
  variante, cantidad y costo unitario.
- `supplier_invoice_files`: comprobante privado opcional.

Confirmar una factura genera entradas de inventario. Un costo distinto produce
una alerta para revisión; nunca cambia automáticamente el precio de venta. No
existen órdenes de compra, pagos, cuentas por pagar ni contabilidad.

### Clientes y cotizaciones

- `customers`, `customer_contacts`: directorio interno opcional, sin cuentas de
  acceso para clientes.
- `quotes`: folio, sucursal, contacto inicial, vendedor responsable y estado.
- `quote_versions`: versión inmutable, vigencia elegida por el vendedor,
  importes, condiciones y modalidad acordada.
- `quote_items`: snapshot de SKU, descripción, unidad, cantidad, precio,
  descuento e impuesto.
- `quote_status_history`, `quote_assignments`, `quote_approvals`: trazabilidad,
  toma por vendedor y autorizaciones de descuento.
- `quote_contacts`: nombre, empresa opcional, teléfonos, correo y preferencia de
  contacto conservados con la cotización.

No hay registro ni portal de clientes. El vendedor decide si relaciona la
solicitud con un cliente frecuente, crea uno o conserva solo el contacto. Solo
se cotizan productos activos del catálogo; no hay partidas libres ni adjuntos
públicos.

Estados principales: recibida, asignada, en preparación, pendiente de
autorización, enviada, aceptada, parcialmente entregada, entregada, rechazada,
vencida y cancelada. La aceptación la registra el vendedor después de
confirmarla con el cliente y crea el apartado de inventario.

### Entregas

- `vehicles`: ficha básica, sucursal y disponibilidad; sin mantenimiento.
- `drivers`: ficha interna sin usuario ni acceso al sistema.
- `deliveries`, `delivery_items`: recolección o domicilio, cantidades parciales,
  dirección acordada, costo, responsable y estados.
- `delivery_runs`, `delivery_stops`: rutas y secuencia capturadas por Logística.
- `delivery_evidence`: fotografía, receptor y observaciones, capturados por
  personal interno.

Se admiten entregas parciales. Logística actualiza los estados; los repartidores
no ingresan al sistema. El costo y dirección de entrega los captura el vendedor,
no el visitante público.

### Contenido público y archivos

- `media`: metadatos, variantes optimizadas, dimensiones y texto alternativo.
- `banners`, `promotions`, `events`, `reviews`: secciones estructuradas.
- `pages`, `page_revisions`: contenido institucional y SEO.
- `legal_documents`, `legal_document_versions`, `legal_acceptances`: aviso de
  privacidad versionado y consentimiento.
- `quote_term_templates`: condiciones comerciales por sucursal.
- `site_settings`, `navigation_items`: configuración pública limitada.

Los formularios validan tipo real, peso, dimensión y proporción de imágenes.
Las promociones son contenido publicitario y no alteran precios. El
Administrador de la página es el perfil operativo que publica contenido.

## Orden de migraciones

1. Control de migraciones y conexión PDO (implementación aislada en M00;
   integración real pendiente).
2. Módulos, sucursales y configuración base.
3. Usuarios, roles, permisos, sesiones y auditoría.
4. Categorías, marcas, unidades, atributos, productos, variantes y medios.
5. Listas, precios, impuestos y políticas de descuento.
6. Inventario, mínimos, lotes, series, conteos y movimientos.
7. Proveedores y entradas por factura.
8. Clientes, cotizaciones, versiones, asignaciones y autorizaciones.
9. Transferencias, apartados, entregas y devoluciones.
10. CMS, documentos legales, navegación y SEO.
11. Notificaciones, reportes, importaciones y respaldos operativos.

Cada bloque tendrá migración hacia adelante, reversión segura cuando sea
posible y pruebas en una base vacía. Se probará primero localmente, después en
el minilab y finalmente en producción.

Las migraciones M01.1 usan InnoDB, `utf8mb4`, claves `BIGINT UNSIGNED`, índices
de disponibilidad, `CHECK` para estados/coordenadas y `RESTRICT` en las claves
foráneas. No incluyen semillas. La reversión solo elimina una tabla vacía; si
contiene registros, falla de forma explícita. DDL en MySQL/MariaDB puede causar
confirmaciones implícitas, por lo que una reversión de lote no se considera
atómica.

M01.2 conserva esas decisiones. SQL protege tipos, rangos por fila, horas de
reloj, duración no nula, representación canónica de cierres, duplicados
cubiertos por claves únicas, llaves foráneas e índices de consulta. El dominio
rechaza formatos inválidos, horarios invertidos sin cruce explícito,
solapamientos del mismo día, conflictos nocturnos entre días consecutivos y
mezcla de cierre/apertura excepcional. Un `CHECK` no puede proteger reglas
entre varias filas: M01.4 deberá leer y bloquear el calendario afectado dentro
de la escritura para revalidar solapamientos y evitar mezclar un cierre con
intervalos abiertos bajo concurrencia.

## Insumos pendientes del negocio

No bloquean el diseño, pero sí la carga y publicación reales:

- Excel de productos, SKU, presentaciones, precios y existencias.
- Catálogo inicial de categorías, marcas, unidades y listas comerciales.
- Datos oficiales y coordenadas de sucursales.
- Logo, manual, fotografías y documentos autorizados.
- Tasas y criterio de impuestos; indicación de precios con o sin impuesto.
- Límites de descuento por perfil.
- Texto aprobado del aviso de privacidad y condiciones comerciales.
- Datos SMTP, dominio definitivo y proveedor de mapas.

Las credenciales se configurarán mediante variables de entorno y nunca se
guardarán en Git ni en esta documentación.
