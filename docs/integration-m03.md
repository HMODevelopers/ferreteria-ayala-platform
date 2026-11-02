# Punto de control MariaDB para M03

Estado al 1 de noviembre de 2026: integración MariaDB y HTTP autenticada
aprobadas. La cuenta M03 es exclusiva, las doce migraciones están aplicadas en
desarrollo como lote 3 y la base aislada queda vacía después de cada suite.

## Destino y barreras

- MariaDB 10.11 y base exclusiva cuyo nombre termina en `_test`.
- Endpoint, base, cuenta y charset propios en `TEST_DB_HOST_M03`,
  `TEST_DB_PORT_M03`, `TEST_DB_DATABASE_M03`, `TEST_DB_USERNAME_M03`,
  `TEST_DB_PASSWORD_M03` y `TEST_DB_CHARSET_M03`. M03 no hereda valores de
  otra suite ni de `DB_*`.
- Privilegios exactos sobre esa base: `SELECT`, `INSERT`, `UPDATE`, `DELETE`,
  `CREATE`, `DROP`, `TRIGGER`, `EVENT` y `EXECUTE`; globalmente solo `USAGE`,
  sin `GRANT OPTION`.
- `M03_TEST_AUTHORIZATION` debe coincidir exactamente con la base.
- Inventario vacío de tablas, vistas, triggers, eventos y rutinas.

El ejecutor no crea ni elimina bases, no usa `root`, no ejecuta `TRUNCATE` y no
desactiva claves foráneas. Conserva el estado para inspección ante un fallo y
solo limpia después de completar todas las comprobaciones.

## Ejecución

```bash
npm run test:mysql:m03
npm run test:mysql:m03:http
```

La suite aplica M00–M03, comprueba las 27 migraciones y el inventario, crea
fixtures aleatorios identificables y valida jerarquías sin ciclos, unicidad,
FKs, repositorios, filtros, transacciones, rollback DML, permisos y auditoría.
Después elimina solo sus fixtures en orden de dependencias, revierte las 27
migraciones y exige inventario final vacío.

La suite HTTP aplica el mismo esquema aislado, crea un administrador sintético,
revisa las cinco vistas M03 a 360, 768, 1024 y 1440 px, CSRF, altas inactivas,
búsqueda, activaciones, auditoría y errores de consola. Después elimina sus
fixtures, revierte las 27 migraciones y vuelve a exigir inventario vacío.

La relación jerárquica conserva una FK autorreferente restrictiva. Los ciclos se
rechazan en dominio y servicio antes de escribir: MariaDB 10.11 no admite un
`CHECK` que compare una FK con el `AUTO_INCREMENT` de la misma fila (error
1901), por lo que esa regla no se duplica como `CHECK` incompatible.

## Activación posterior

Después de un `PASS`, revisar respaldo e historial de desarrollo y ejecutar:

```bash
php bin/migrations.php status
php bin/migrations.php migrate
php bin/migrations.php status
```

La aplicación normal usa `DB_*`; el punto de control nunca los reutiliza. Las
migraciones M03 son aditivas y no insertan catálogo comercial. La última agrega
los nueve permisos técnicos M03 y los asigna únicamente al rol
`GLOBAL_ADMIN` existente.
