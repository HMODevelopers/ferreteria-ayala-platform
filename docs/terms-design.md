# Condiciones para cotizaciones

La pantalla /admin/cms/terms administra textos comerciales reutilizables por sucursal. El operador selecciona una plantilla activa en el editor financiero, en Condiciones y entrega acordadas → Plantillas de esta sucursal → Usar. El texto se copia al campo de condiciones comerciales para revisarlo y adaptarlo al cliente.

La pantalla principal presenta una explicación de uso, búsqueda por nombre o contenido, filtros por sucursal y disponibilidad, tarjetas con vista previa y paginación que conserva filtros. Sin sucursales disponibles explica el requisito y no ofrece una captura incompleta. Las plantillas existentes de sucursales inactivas se pueden consultar y editar conservando su sucursal.

Creación y edición usan el modal administrativo compartido. Cada campo explica su propósito y el estado de disponibilidad. Un error de validación devuelve HTTP 422 con los valores capturados; el guardado correcto actualiza la biblioteca y muestra su confirmación. El editor también funciona como página con formulario nativo sin JavaScript.

El permiso cms.terms sigue delimitando las sucursales autorizadas. La lectura de una plantilla fuera de alcance responde 404. Se conservan CSRF, validación del servicio y control de concurrencia por lock_version. No se cambian tablas ni reglas comerciales.

Activar una plantilla la ofrece para futuras selecciones. Desactivarla la conserva y la retira del selector. Editar una plantilla no modifica las condiciones ya copiadas en cotizaciones. No define precios, vigencia ni costos de entrega.
