# Integración M12 · notificaciones internas

Estado al 10 de noviembre de 2026: **primer incremento vertical implementado y
verificado en MariaDB 10.11.15; pendiente de migrar a desarrollo**. MySQL 8.4,
un lector de pantalla real y dispositivos físicos no se probaron en este host.

## Arquitectura y consistencia

El primer origen es `delivery.incident`, la transición formal M10 de
`in_transit` a `failed`. `DeliveryManagementService` ya ejecutaba evento,
cantidades, estado y auditoría bajo `Connection::transaction()`. El servicio
ahora agrega el evento de notificación y sus destinatarios dentro de esa misma
transacción, después de persistir el estado. Si cualquier escritura falla, la
incidencia y la notificación revierten juntas; si la operación se confirma no
queda una ventana en la que se pierda el aviso.

No se usa outbox ni `operation_jobs` para este origen local: introducir un
worker después del commit agregaría latencia y exigiría otra garantía contra
pérdida sin aportar aislamiento externo. La cola durable permanece disponible
para SMTP o efectos externos futuros. No existe un handler M12 en este
incremento ni una cadena donde un fallo de notificación genere otro aviso.

## Modelo de datos e idempotencia

La migración `20261110090000_create_internal_notifications` agrega:

- `notification_events`: tipo, origen lógico, recurso, sucursal, importancia,
  título/mensaje mínimos, permiso requerido, fecha e idempotency key;
- `notification_deliveries`: relación evento/usuario, fecha de entrega y
  `read_at` individual.

`UNIQUE(event_type, source_type, source_id)` y la clave idempotente impiden dos
eventos lógicos. `UNIQUE(notification_event_id, user_id)` impide dos entregas al
mismo destinatario. El índice `(user_id, read_at, id)` atiende contador,
recientes e historial sin cargar toda la bandeja. Las FKs a sucursal, usuario y
evento son restrictivas. No se copian clientes, direcciones, contactos,
secretos ni el documento completo; se conserva folio, motivo operativo y los
identificadores necesarios.

No hay eliminación ni archivado: el historial se conserva. Una política de
retención futura debe acordar antes sus efectos de auditoría.

## Destinatarios y autorización

Al registrar la incidencia se seleccionan exclusivamente usuarios activos que
tengan simultáneamente `deliveries.view` y `deliveries.incident` para la
sucursal. Una asignación global válida o una asignación de rol y acceso
explícito a esa sucursal siguen exactamente el contrato M02. No se presupone
que todo administrador vea todas las sucursales.

La entrega histórica no basta para leer. Listado, recientes, contador,
consulta individual y marcado reevalúan en SQL que el usuario siga activo y
conserve `required_permission` (`deliveries.view`) sobre la sucursal. Por eso
una revocación desaparece de contador e historial de inmediato. `open` vuelve a
comprobar la entrega autorizada y redirige a M10; el controlador M10 valida de
nuevo el recurso. `read` exige sesión, CSRF, propietario y permiso vigente. Un
identificador ajeno devuelve el mismo 404 no enumerable.

Marcar como leída sólo fija `read_at`; no reanuda, resuelve ni modifica una
entrega.

## Rutas e interfaz

- `GET /admin/notifications`: historial paginado de 25 registros;
- `GET /admin/notifications/status`: contador JSON privado para polling;
- `GET /admin/notifications/open`: navegación indirecta autorizada;
- `POST /admin/notifications/read`: lectura individual con CSRF.

La cabecera administrativa muestra campana, badge real, seis recientes y
enlace al historial. El historial filtra todas/no leídas/leídas y tipo de
evento, distingue importancia y lectura y ofrece estados vacíos. El polling es
de 60 segundos, no se ejecuta con la pestaña oculta, conserva el último valor
si la red falla y no abre servicios persistentes. Todas las cadenas dinámicas
se escapan con `e()`.

## Pruebas reproducibles

```bash
npm test
npm run build
npm run test:mysql:m12:notifications
npm run test:mysql:m12:jobs
npm run test:browser:m12:notifications
```

Resultados reales:

- PHP: 1,513 comprobaciones;
- notificaciones SQL: 27 aserciones, 71 migraciones, una incidencia M10 real,
  cinco productores
  independientes, autorización, lectura, rollback protegido y base final
  vacía;
- núcleo durable: 50 aserciones, 71 migraciones, 60 reclamaciones concurrentes,
  recuperación, fencing y rollback aislado;
- Chrome headless: historial en 360, 768 y 1440 px, campana por teclado,
  contador/polling, lectura con CSRF, vacío y navegación autorizada;
- Tailwind/esbuild: compilación local aprobada.

La suite SQL prueba además duplicado secuencial y concurrente, destinatario por
sucursal, aislamiento de otra sucursal, revocación posterior, identificador
ajeno, filtros, paginación, restricción única, rollback de la operación y
protección del rollback de migración. Sólo usa la base `_test` autorizada. Una
ejecución interrumpida por el límite de la sesión preservó sus fixtures; se
verificaron los marcadores propios, se retiraron únicamente esos datos y se
repitió completa. Las ejecuciones definitivas dejaron cero objetos.

## Límites y continuación

Sólo existe el evento `delivery.incident`; cotizaciones, transferencias y
trabajos en `intervention` requieren reglas de destinatarios aprobadas antes de
agregarse. No hay acción masiva, archivado, WebSocket, Redis, SMTP, SMS o
WhatsApp. La vista de navegador usa fixtures renderizados por las vistas reales
y un servidor aislado; la persistencia/autorización se comprobó por separado
en MariaDB. Para preparar SMTP, el siguiente incremento debe definir
preferencias, direcciones verificadas, plantilla, datos permitidos y un handler
idempotente que use la cola durable sin convertir fallos de correo en bucles de
notificación.
