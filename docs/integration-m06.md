# Punto de control MariaDB para M06

Estado al 2 de noviembre de 2026: integración MariaDB y HTTP autenticada en
PASS. La base aislada terminó vacía y cinco migraciones M06 están aplicadas en
desarrollo como lote 6, para 46 acumuladas.

## Aislamiento y credenciales

Los ejecutores consumen `TEST_DB_HOST_M06`, `TEST_DB_PORT_M06`,
`TEST_DB_DATABASE_M06`, `TEST_DB_USERNAME_M06`, `TEST_DB_PASSWORD_M06`,
`TEST_DB_CHARSET_M06` y `M06_TEST_AUTHORIZATION`. Rechazan `root`, bases sin
sufijo `_test`, identidad distinta, inventario previo, privilegios adicionales
o `GRANT OPTION`. Para esta ejecución se mapearon solo al proceso M06 las
credenciales limitadas existentes en `.env`; ningún secreto se copió al código,
documentación o salida.

La cuenta conserva exactamente `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`,
`DROP`, `TRIGGER`, `EVENT` y `EXECUTE` sobre la base de pruebas, y `USAGE`
global. La primera versión intentó ampliar el `CHECK` de movimientos y el
preflight real demostró que eso exigiría `ALTER`. El estado propio se preservó,
inspeccionó y recuperó. La solución final reutiliza `adjustment_in` de M05 con
origen `goods_receipt`, sin elevar privilegios ni debilitar el aislamiento.

## Modelo y transacción

- `suppliers` y `supplier_contacts` conservan identidad, contacto y estado.
- `supplier_invoices` separa borrador/confirmación de la recepción física.
- `supplier_invoice_items` conserva variante, unidad, costo, facturado y
  recibido acumulado con `DECIMAL`.
- `goods_receipts` usa clave idempotente única y hash de la solicitud.
- `goods_receipt_items` vincula cada cantidad con un movimiento M05 único.

La recepción bloquea factura y partidas, relee el pendiente y después compone el
adaptador M05 dentro de la misma transacción. Recepción, progreso, movimiento,
saldo y auditoría se confirman juntos. Los reenvíos equivalentes devuelven la
operación existente; una clave reutilizada con datos distintos se rechaza.

## Pruebas

```bash
npm run test:mysql:m06
npm run test:mysql:m06:http
```

La suite MariaDB aplica 46 migraciones desde cero y cubre duplicados,
recepciones parciales/completas, sobre-recepción, idempotencia, conversión y
saldo M05, rollback de una recepción multilínea y dos procesos independientes
intentando recibir el mismo pendiente. Solo uno confirma. La suite HTTP prueba
login, CSRF, proveedor, factura, partida, confirmación, recepción, inventario,
movimiento, auditoría y 360/768/1024/1440 px. Ambas revierten 46 migraciones y
exigen inventario final vacío.

## Desarrollo

La instantánea privada previa
`m06-pre-migration-20261102-132706.json` se decodificó y comparó contra 41
tablas; su SHA-256 es
`fedc236c71adf65292c98754fdb38ee8d4e998c9b3025223ee0da395aecb1d67`.
Después se aplicó el lote 6 y se verificaron 46 historiales, siete permisos M06
y siete asociaciones a `GLOBAL_ADMIN`. Las seis tablas comerciales nuevas
permanecen vacías y los conteos previos no cambiaron.


## Continuidad M11 · 4 de noviembre de 2026

Los pendientes de medios y contenido relacionados con este módulo se atienden en [integración M11](integration-m11.md). Ese informe conserva las decisiones autorizadas, los límites de formatos, la recepción pública con consentimiento y los resultados reales; los estados anteriores de este documento permanecen como historia del incremento original.

## Integración posterior con devoluciones

Las devoluciones a proveedor se vinculan con `goods_receipt_items`, no con la
factura por sí sola. El egreso no modifica cantidades o importes históricos de
compra. Detalle en [integración de devoluciones](integration-returns.md).
