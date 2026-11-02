# Punto de control MariaDB para M04

Estado al 1 de noviembre de 2026: integración MariaDB y HTTP autenticada en
PASS. La base aislada terminó vacía y las ocho migraciones M04 están aplicadas
en desarrollo como lote 4, para 35 acumuladas.

## Aislamiento obligatorio

Configurar `TEST_DB_HOST_M04`, `TEST_DB_PORT_M04`, `TEST_DB_DATABASE_M04`,
`TEST_DB_USERNAME_M04`, `TEST_DB_PASSWORD_M04`, `TEST_DB_CHARSET_M04` y
`M04_TEST_AUTHORIZATION`. La base debe terminar en `_test`, estar vacía y la
cuenta nunca puede ser la de desarrollo ni `root`. Esta ejecución reutilizó
temporalmente la cuenta limitada disponible, por instrucción del propietario,
mapeándola solo al proceso M04 y sin copiar o mostrar su secreto.

Privilegios exactos sobre la base: `SELECT`, `INSERT`, `UPDATE`, `DELETE`,
`CREATE`, `DROP`, `TRIGGER`, `EVENT` y `EXECUTE`; globalmente solo `USAGE`, sin
`GRANT OPTION`. Un administrador del minilab debe crear esta cuenta fuera de la
aplicación. No se escriben credenciales en este documento.

## Ejecución

```bash
npm run test:mysql:m04
```

El ejecutor verifica endpoint, identidad, versión MariaDB 10.11, privilegios e
inventario vacío; aplica las 35 migraciones; prueba `DECIMAL`, FKs, precedencia,
tasas, historial, solapamientos, versión optimista y ausencia de precio. Después
elimina exclusivamente sus fixtures, revierte las 35 migraciones y exige
inventario final vacío. Ante un fallo preserva el estado para inspección.

Las dos primeras ejecuciones preservaron estado sintético al detectar un
marcador PDO repetido y después una expectativa incorrecta de vigencias. Cada
estado fue inspeccionado y recuperado antes de repetir. El tercer intento pasó.
La suite HTTP autenticada también pasó después de corregir un selector de
Playwright y confirmó limpieza final.

Después del PASS se creó y verificó el respaldo privado M04, se ejecutó
`php bin/migrations.php migrate` y se confirmó el lote 4. Las migraciones no
insertaron precios, tasas, listas, políticas ni catálogo comercial.
