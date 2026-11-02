# Punto de control MariaDB para M05

Estado al 1 de noviembre de 2026: integración MariaDB y HTTP autenticada en
PASS. La base aislada terminó vacía y seis migraciones M05 están aplicadas en
desarrollo como lote 5, para 41 acumuladas.

## Aislamiento

El ejecutor consume `TEST_DB_HOST_M05`, `TEST_DB_PORT_M05`,
`TEST_DB_DATABASE_M05`, `TEST_DB_USERNAME_M05`, `TEST_DB_PASSWORD_M05`,
`TEST_DB_CHARSET_M05` y `M05_TEST_AUTHORIZATION`. Rechaza `root`, bases sin
sufijo `_test`, identidad distinta, inventario previo, privilegios adicionales
o `GRANT OPTION`. En esta ejecución la cuenta limitada disponible se mapeó solo
al proceso M05; su secreto no se copió ni mostró.

## Pruebas

```bash
npm run test:mysql:m05
npm run test:mysql:m05:http
```

La suite comprueba esquema, FKs restrictivas, `DECIMAL(18,6)`, conversión M03,
inicial, ajustes, saldo insuficiente, idempotencia, mínimos, historial y
permisos. Dos procesos con conexiones independientes ajustan el mismo saldo.
Otra conexión fuerza timeout y demuestra rollback sin escritura parcial.

La prueba HTTP valida sesión, CSRF, permisos, inicial, ajuste, mínimo,
historial, auditoría y 360, 768, 1024 y 1440 px. Ambas suites eliminan sus
fixtures, revierten el esquema conocido y exigen inventario final vacío.

## Desarrollo

Antes de migrar se verificaron 35 migraciones y se creó la instantánea privada
`m05-pre-migration-20261101-215204.json`, con manifiesto y SHA-256. El migrador
aplicó seis archivos en lote 5. Las siete tablas M05 quedaron vacías; solo se
registraron siete permisos y su asociación a `GLOBAL_ADMIN`. No se insertaron
productos, cantidades, movimientos, mínimos, lotes, series ni conteos.

M05 no implementa recepciones M06, apartados M08, transferencias M09, entregas
M10 ni movimientos automáticos originados por esos módulos.
