# Cotizaciones: flujo unificado

## Operación

La bandeja **Cotizaciones** reúne las solicitudes del sitio y las registradas por el equipo desde correo, WhatsApp, teléfono o mostrador. El origen identifica cómo llegó el pedido; no crea un proceso comercial distinto. Las cotizaciones anteriores se identifican como captura interna cuando no existe información confiable del canal.

El operador registra los materiales y cantidades tal como los pidió el cliente. Después relaciona esos renglones con variantes reales del catálogo, elige el cliente comercial y calcula precios, impuestos y total con las reglas existentes. La solicitud original se conserva. Las capturas nuevas mantienen su referencia al generar el documento; los folios históricos permanecen disponibles.

## Seguimiento

1. **Recibida:** pendiente de revisión.
2. **En verificación:** un responsable revisa materiales, disponibilidad y condiciones.
3. **Pendiente de respuesta:** se documentan faltantes o consultas para el cliente. Su respuesta permite continuar.
4. **Verificada:** productos, cantidades, precios y disponibilidad fueron revisados. Los cambios en los materiales solicitados requieren registrar la conformidad del cliente, su canal y destinatario.
5. **Lista para enviar:** se emitió la versión formal con precios y total. La emisión del documento vinculado verifica nuevamente la disponibilidad y la revisión de esa versión dentro de la misma transacción.
6. **Enviada:** el operador confirma que compartió el documento y registra el canal, destinatario y comentario. El historial conserva la versión exacta entregada.

El seguimiento comercial y el estado financiero del documento son datos distintos. Modificar o revisar el documento invalida la verificación anterior. Preparar, verificar o registrar un envío no reserva ni mueve existencias; la aceptación conserva las reglas comerciales existentes.

## Interfaz

Una sola entrada del menú, filtros por origen, etapa, estado documental, sucursal y responsable. El contenido utiliza el ancho disponible y se adapta a pantallas pequeñas. Captura, relación de productos, edición financiera, contactos, verificación y operaciones se abren en el modal compartido. Los errores conservan los datos y las búsquedas de productos/clientes no descartan la captura.

## Acceso y trazabilidad

Los permisos y sucursales existentes siguen delimitando la consulta y las operaciones. Un editor de contenido no puede consultar el seguimiento comercial. El acceso al documento no otorga acceso implícito a los datos originales de recepción web. El registro de consentimiento web y sus controles antispam se conservan. Las operaciones comprueban versión y clave de idempotencia para impedir sobrescrituras o registros duplicados.

## Entrega y actualización

El sistema genera el PDF y registra una entrega realizada por el operador. SMTP y la mensajería automática continúan pendientes: marcar una entrega no envía mensajes.

La migración `20261104200100_create_quote_workflows` agrega dos tablas de seguimiento e historial; no modifica las tablas comerciales anteriores ni crea usuarios. La actualización local comprueba un respaldo privado y que los datos preexistentes se mantengan íntegros.
