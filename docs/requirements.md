# Requisitos funcionales aprobados

Levantamiento realizado con el propietario del proyecto el 17 de octubre de
2026. Este documento es la fuente funcional para diseñar migraciones y módulos.
El índice y las dependencias de implementación se encuentran en
[catálogo de módulos](modules.md).

## Objetivo y alcance

La plataforma es un sitio informativo con catálogo de ferretería, solicitudes
de cotización, seguimiento interno e inventario por sucursal. No es comercio
electrónico, punto de venta, facturación ni contabilidad.

El visitante selecciona una sucursal, consulta productos y existencia
disponible, prepara una lista y envía una solicitud. Ventas la toma, contacta al
cliente, determina condiciones y precios, genera el PDF y lo comparte por
correo o WhatsApp. El cliente confirma fuera de la plataforma y el vendedor
registra la aceptación.

## Sitio público

- Catálogo responsive con búsqueda, categorías, atributos y filtros.
- Selector de sucursal previo a consultar inventario.
- Existencia disponible exacta de la sucursal seleccionada.
- Precio público visible solo cuando el Administrador global lo active.
- Cuando el precio sea visible, cualquier total se identifica como estimado.
- Solo se agregan productos activos del catálogo; no se admiten partidas libres
  ni archivos adjuntos del visitante.
- El cliente puede pedir una cantidad superior a la disponible; Ventas decide
  si puede completarla mediante transferencias antes de emitir/aceptar la
  versión correspondiente.
- La confirmación en pantalla entrega folio y aclara que la solicitud no aparta
  existencias ni representa una cotización final.

Datos públicos solicitados: nombre completo, empresa opcional, teléfonos,
correo, sucursal, preferencia de contacto y comentario. Dirección y modalidad
de entrega se acuerdan después con Ventas.

## Cotizaciones

- Bandeja compartida por sucursal. Todos los vendedores autorizados pueden
  verla y uno la toma de forma atómica para evitar doble asignación.
- El vendedor puede generar versiones; una versión enviada no se sobrescribe.
- Vigencia elegida obligatoriamente por el vendedor.
- Precios por sucursal y lista; descuento adicional limitado por política.
- Un descuento superior al límite requiere autorización del gerente o
  administrador de sucursal.
- PDF con folio, versión, sucursal, vendedor, partidas, subtotal, descuentos,
  impuestos configurables, entrega, total, vigencia y condiciones.
- Envío por SMTP desde el sistema. WhatsApp abre la aplicación normal con
  número, mensaje y archivo/enlace preparados; no hay API de WhatsApp.
- El vendedor registra manualmente la aceptación confirmada por el cliente.
- Aceptar vuelve a validar existencias y crea el apartado.
- Si no hay existencia en ninguna sucursal, el vendedor informa al cliente,
  quita o sustituye la partida y genera una nueva versión. La cotización no
  genera solicitudes de compra.
- El vendedor puede cancelar una cotización aceptada con motivo obligatorio y
  liberar las cantidades aún no entregadas.
- Cotizaciones y versiones son históricas; se archivan, no se borran.

No hay cuentas, inicio de sesión ni portal para clientes. El vendedor decide si
relaciona el contacto con un cliente frecuente y su lista comercial.

## Inventario por sucursal

- Existencia física, apartada y disponible por variante.
- Entradas, salidas, ajustes, transferencias, conteos, apartados, liberaciones,
  entregas, devoluciones y mermas mediante movimientos auditados.
- Existencia mínima por producto/sucursal y alertas internas.
- Cantidades enteras o decimales según unidad.
- Conversiones entre caja, rollo, paquete, tramo y unidad base.
- Lectura e impresión de códigos de barras en entradas, inventario y entrega.
- Control opcional por lote, caducidad o número de serie.
- Sin control de pasillo/anaquel ni retazos individuales.
- No se permiten saldos disponibles negativos.

Al aceptar una cotización, las unidades dejan de estar disponibles de inmediato
y aparecen como apartadas. Entregar genera la salida física contra ese apartado
sin descontar dos veces. Cancelar libera lo pendiente; una devolución crea un
movimiento nuevo y solo regresa a disponible si el producto está vendible.

## Transferencias

- Ventas puede solicitar mercancía para completar una cotización aceptada.
- Estados: solicitada, aprobada, preparada, en tránsito, recibida o cancelada.
- Aprueba el gerente de la sucursal origen; puede delegarlo a Inventario mediante
  permiso configurable.
- Aprobar aparta en origen; recibir incrementa y aparta en destino para la
  cotización relacionada.

## Entradas por proveedor

- No existe módulo de Compras ni órdenes de compra.
- Se mantienen proveedores básicos y entradas por factura.
- La factura registra folio, fecha, sucursal, partidas, cantidades y costo
  unitario; evita duplicados por proveedor/folio.
- Confirmar genera las entradas de inventario.
- Inventario puede confirmar si tiene el permiso; de lo contrario requiere
  autorización.
- Un cambio de costo genera una alerta. El gerente decide manualmente si cambia
  precios; no se calculan márgenes ni se actualizan precios automáticamente.
- No hay pagos, cuentas por pagar, facturación fiscal ni contabilidad.

## Precios

- Moneda única: MXN.
- Precio público y listas comerciales por variante y sucursal.
- Lista con descuento general y excepciones de precio por producto.
- Cliente frecuente asociado opcionalmente a una lista.
- Gerentes administran precios de sus sucursales; el Administrador global puede
  administrar todas.
- Impuestos y criterio de precio con/sin impuesto son configurables y quedan
  pendientes de confirmación del negocio.
- Todo cambio conserva historial; cotizaciones conservan snapshots.

## Entregas

- Recolección en sucursal y entrega a domicilio.
- Ventas captura modalidad, dirección, ubicación, receptor, costo y fecha.
- Se admiten varias entregas parciales para una cotización.
- Estados operativos: pendiente, preparando, asignada, en tránsito, entregada,
  intento fallido o cancelada.
- Vehículos propios con ficha básica y disponibilidad; sin mantenimiento,
  kilometraje ni seguros.
- Repartidores sin cuenta de usuario. Logística actualiza rutas, estados y
  evidencia desde el panel.
- Evidencia opcional: fotografía, receptor y observaciones.

## Catálogo

- Categorías jerárquicas, marcas, productos y variantes vendibles.
- SKU propio por variante/presentación y múltiples códigos de barras.
- Unidad base y conversiones de presentación.
- Atributos configurables por categoría con tipo, unidad, obligatoriedad,
  visibilidad y uso como filtro.
- Galería de imágenes y ficha técnica opcional.
- Importación Excel con vista previa, validación, transacción, reporte de errores
  e historial.
- Excel crea y actualiza por SKU. Nunca elimina ni desactiva por ausencia; esas
  operaciones son explícitas y conservan historial.

## Sucursales y mapas

- Alta ilimitada desde formularios: clave, nombre, dirección, coordenadas,
  teléfonos, correo, WhatsApp, horarios, excepciones, fotos, servicios y estado.
- Mapa público dinámico, marcadores, buscador y enlace de indicaciones.
- Carga diferida del mapa para proteger rendimiento.
- Coordenadas independientes del proveedor de mapas.
- La sucursal seleccionada controla catálogo, precios, inventario y destino de
  la cotización.

## CMS

Todo el contenido público se administra mediante formularios estructurados, no
mediante constructor libre: navegación, Home, banners, promociones, eventos,
testimonios, páginas, sucursales, textos institucionales, SEO y condiciones.

- Estados borrador, programado, publicado y archivado.
- Imágenes con formato real, peso, dimensión y proporción validados; versiones
  responsive, optimización y texto alternativo.
- Promociones solo publicitarias; no modifican precios.
- Administrador de la página como perfil dedicado. No accede a precios,
  inventario, usuarios ni cotizaciones.
- Aviso de privacidad versionado, publicado desde formulario y vinculado al
  consentimiento de cada solicitud.
- Plantillas de condiciones comerciales editables por sucursal.

## Acceso, módulos y auditoría

- Administrador global con alcance completo.
- Módulos habilitables globalmente y por sucursal sin borrar datos.
- Gerentes pueden crear usuarios de su sucursal, pero no elevar permisos por
  encima de los propios ni crear administradores globales.
- Roles y permisos por acción; usuarios con acceso a una o varias sucursales.
- Sin segundo factor obligatorio en la primera versión.
- Contraseñas con hash seguro, sesiones protegidas, CSRF, límites de intentos,
  recuperación por SMTP y cierre de sesiones al desactivar un usuario.
- Auditoría de accesos sensibles, permisos, precios, inventario, cotizaciones,
  contenido, importaciones y exportaciones.

## Notificaciones y reportes

Las notificaciones de empleados son internas; no se envían por correo. Cubren
cotizaciones nuevas, asignaciones, autorizaciones, mínimos, transferencias,
costos y entregas.

Reportes en pantalla y exportables a Excel/PDF:

- Existencia física, apartada y disponible.
- Kardex y movimientos.
- Mínimos y agotados.
- Transferencias y entradas por factura.
- Cotizaciones por estado, sucursal y vendedor; tiempos de atención.
- Entregas pendientes, parciales y terminadas.
- Importaciones, errores y auditoría según permiso.

Todos usan paginación y filtros del servidor y respetan el alcance del usuario.

## Operación y calidad

- PHP puro 8.2+, MVC propio, MySQL/PDO y assets locales compilados.
- Responsive en computadora, tableta y teléfono.
- Preparado para miles de productos y crecimiento de usuarios/sucursales:
  índices, paginación server-side, importaciones por lotes y consultas acotadas.
- Rate limiting y controles antispam en el formulario público.
- Ambientes separados: local, minilab de pruebas y cPanel de producción.
- Respaldos diarios por 30 días y mensuales por 12 meses; copia previa a
  despliegues/migraciones y pruebas de restauración.

## Fuera de alcance

- Carrito de compra o pago en línea.
- Punto de venta y registro financiero de ventas.
- Facturación fiscal, contabilidad, pagos o cuentas por cobrar/pagar.
- Órdenes de compra.
- Cuentas o portal para clientes.
- WhatsApp Business API y rastreo GPS continuo.
- Mantenimiento de flotilla y control de ubicaciones internas del almacén.
- Cálculo de margen o rentabilidad.

## Pendientes de información, no de diseño

- Excel real de productos, existencias y precios.
- Datos oficiales y coordenadas de sucursales.
- Logo, fotografías y manual de identidad autorizados.
- Texto legal del aviso de privacidad y condiciones comerciales.
- Impuestos, precios con/sin impuesto y límites concretos de descuento.
- Credenciales SMTP, dominio definitivo y proveedor de mapas.

Ningún secreto debe compartirse en documentos o confirmarse dentro del código.
