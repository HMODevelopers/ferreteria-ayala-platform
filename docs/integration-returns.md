# Integración de devoluciones operativas

Estado al 10 de noviembre de 2026: implementación completa en código y validación PHP/build/Chrome aprobada. La validación MariaDB aislada queda pendiente porque la identidad configurada para la base de pruebas es rechazada por el servidor antes de ejecutar una migración. La suite M05 preexistente reproduce el mismo error. No se migró desarrollo.

## Objetivo y límites

El módulo registra devoluciones físicas sustentadas por documentos existentes. No genera reembolsos, pagos, notas de crédito, facturación ni asientos contables y no modifica importes o impuestos históricos.

- De cliente: la fuente es una `delivery_item` cuya recepción fue confirmada por el cliente. La cantidad base es `received_quantity`.
- A proveedor: la fuente es una `goods_receipt_item` de una recepción confirmada. La cantidad base es `input_quantity`.
- Una cotización aceptada por sí sola nunca acredita una devolución.

## Ciclo operativo

Estados: `registered`, `authorized`, `rejected`, `completed` y `cancelled`, presentados como Registrada, Autorizada, Rechazada, Finalizada y Cancelada.

1. Registrar crea documento, partidas y evento; no cambia inventario.
2. Autorizar o rechazar registra la decisión; no cambia inventario.
3. Confirmar recepción de cliente o salida a proveedor registra el evento físico y finaliza.
4. Cancelar sólo se permite antes del movimiento físico.

En cliente, `sellable` genera entrada M05; `damaged` e `inspection` conservan trazabilidad sin aumentar existencia disponible. No se inventó almacén de cuarentena. En proveedor, la salida M05 exige existencia disponible y no consume apartados.

## Elegibilidad e integridad

El máximo devolvible es la cantidad físicamente recibida menos partidas de devoluciones Registradas, Autorizadas o Finalizadas. Rechazadas y Canceladas liberan la cantidad. El cálculo usa `DECIMAL(18,6)` y `Decimal`, nunca `float`.

La creación y confirmación bloquean primero el documento de origen y después la devolución y sus partidas. La elegibilidad se relee dentro de la transacción. `operation_key` única, hash de solicitud, `lock_version` y unicidad de movimientos evitan dobles efectos. Estado, evento, movimiento, saldo, vínculo y auditoría confirman o revierten juntos.

## Modelo

- `operational_returns`: tipo, fuente exclusiva, contraparte, sucursal, estado, responsables, fechas y versión.
- `return_items`: partida fuente exclusiva, variante, unidad, cantidad, condición y movimiento opcional.
- `return_events` y `return_event_items`: historial inmutable, idempotencia y movimiento asociado.
- Migraciones aditivas `20261110140000` y `20261110140100`; los rollbacks se rehúsan si existe historial o permisos asignados a roles configurables.

## Seguridad

Permisos por sucursal: `returns.view`, `returns.create`, `returns.authorize`, `returns.receive`, `returns.dispatch`, `returns.cancel` y `returns.history`. Cada lectura o acción intersecta el permiso de devoluciones con `deliveries.view` o `purchases.view` según la fuente. Las escrituras exigen sesión, CSRF, sucursal, estado, versión, datos válidos y confirmación física. `GLOBAL_ADMIN` recibe los permisos durante la migración; ningún otro rol los obtiene automáticamente.

## Interfaz

Rutas: `/admin/returns`, `/admin/returns/create`, `/admin/returns/detail` y `/admin/returns/action`. Hay búsqueda, filtros por tipo/estado/sucursal/fechas, selección segura de documento, cantidades elegibles, condición, detalle, acciones e historial. Todos los textos visibles están en español y no exponen nombres de módulos internos.

## Pruebas y activación

Ejecutado y aprobado:

```bash
php tests/run.php                       # 1576 comprobaciones
npm run build                           # Tailwind 4 + JS
npm run test:browser:returns            # 12 escenarios, 360/768/1440/2560 px
```

Preparado, pero bloqueado antes del esquema por autenticación de la cuenta aislada:

```bash
npm run test:mysql:returns
```

La suite SQL está diseñada para aplicar las 75 migraciones desde cero y verificar ambos flujos, parciales, sucesivas, exceso, material dañado, apartados, reenvío, rollback y dos procesos sobre la última unidad. No usa `DB_*` ni la base habitual. El incremento de 74 a 75 corresponde a la migración aditiva AY-04.

Cuando la identidad aislada vuelva a autenticar, ejecutar la suite SQL y sólo después activar desarrollo:

```bash
php bin/migrations.php status
php bin/migrations.php migrate
php bin/migrations.php status
```

No ejecutar `rollback` si ya existe historial comercial.

## Limitaciones

- No hay inventario separado de cuarentena/no vendible; dañado e inspección quedan fuera del disponible.
- No hay compensación automática después de un movimiento físico; una Finalizada no se cancela.
- Lotes y series continúan bajo las restricciones del servicio M05 existente.
- M13 no se modificó; una futura consulta puede incorporar devoluciones sin reescribir reportes históricos.
- M14 no se desarrolló. No debe iniciarse el cierre de despliegue hasta aprobar SQL aislado y migrar desarrollo de forma controlada.
