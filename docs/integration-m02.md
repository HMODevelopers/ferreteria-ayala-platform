# Punto de control MariaDB para M02

Estado al 25 de octubre de 2026: integración completada contra MariaDB 10.11.
Existe una cuenta exclusiva M02 con privilegios exactos sobre
`ferreteria_ayala_test`, sin privilegios globales distintos de `USAGE`, sin
`GRANT OPTION` y con credencial aleatoria privada en el `.env` local. La suite
M02 y la integración HTTP autenticada terminaron en `PASS`; ambas confirmaron
inventario final vacío. Las ocho migraciones M02 están aplicadas en desarrollo
como lote 2, para un total de quince, y existe un Administrador global activo.

## Destino y barreras

- Base exclusiva cuyo nombre termina en `_test`, vacía antes de comenzar.
- Cuenta M02 propia; no reutilizar las cuentas M00, M01, desarrollo ni `root`.
- `M02_TEST_AUTHORIZATION` debe coincidir exactamente con `TEST_DB_DATABASE`.
- Privilegios de esquema exactos: `SELECT`, `INSERT`, `UPDATE`, `DELETE`,
  `CREATE`, `DROP`, `TRIGGER`, `EVENT` y `EXECUTE`.
- Solo `USAGE` global, sin `GRANT OPTION` ni acceso a otras bases.

El ejecutor valida identidad efectiva, privilegios e inventario de tablas,
vistas, triggers, eventos y rutinas antes de escribir. No crea ni elimina la
base. Adquiere un bloqueo asesor exclusivo y solo limpia cuando la ejecución
completa puede demostrar propiedad y estado esperado. Ante un fallo conserva
el estado para inspección y no reintenta a ciegas.

## Configuración privada

```dotenv
TEST_DB_HOST=192.168.1.12
TEST_DB_PORT=3306
TEST_DB_DATABASE=ferreteria_ayala_test
TEST_DB_USERNAME_M02=CUENTA_LIMITADA_M02
TEST_DB_PASSWORD_M02=VALOR_PRIVADO
TEST_DB_CHARSET=utf8mb4
M02_TEST_AUTHORIZATION=ferreteria_ayala_test
```

La autorización debe exportarse solo para la ejecución aprobada. Ningún valor
real se copia a documentación, mensajes, Git o archivos públicos.

## Ejecución

```bash
npm run test:mysql:m02
```

La suite aplica las quince migraciones M00–M02 sobre el destino vacío, verifica
las 16 tablas esperadas y prueba:

- creación única del Administrador global sin contraseña conocida;
- contraseña incorrecta y autenticación correcta;
- sesión persistente y revocable;
- permisos globales y permisos limitados a una sucursal;
- rechazo de acceso a otra sucursal;
- protección del último administrador activo;
- revocación al cambiar contraseña;
- auditoría de operaciones sin contraseña ni token.

Si todo pasa, elimina exclusivamente sus fixtures, revierte las quince
migraciones, retira `schema_migrations` y confirma inventario vacío.

La prueba adicional del panel usa el mismo aislamiento y sus propias barreras:

```bash
npm run test:mysql:m02:http
```

Aplica el esquema desde cero, crea identidades sintéticas aleatorias, inicia el
servidor sólo en `testing` y prueba con Chrome login, dashboard, usuarios,
roles, sucursales, horarios/servicios vacíos, auditoría, CSRF, alcance, cambio de
contraseña, revocación y logout. Revisa 360, 768, 1024 y 1440 px. Al terminar
elimina los fixtures, revierte las quince migraciones y exige inventario vacío.

## Aplicación en desarrollo

La aplicación incremental ya se completó. La comprobación final devolvió
`No migration files were applied` y el estado enumeró quince migraciones:

```bash
php bin/migrations.php status
php bin/migrations.php migrate
php bin/migrations.php status
```

El comando normal usa `DB_*`; las ocho migraciones no insertaron permisos,
roles ni usuarios. El primer administrador se creó después mediante:

```bash
php bin/create-initial-admin.php
```

El comando solicita datos de forma interactiva, oculta la contraseña, exige
`CREAR ADMINISTRADOR`, crea el catálogo aprobado y se niega a continuar cuando
ya existe un Administrador global. La verificación final encontró exactamente
un Administrador global activo, 17 permisos, 17 asociaciones a su rol, hash
unidireccional aprobado y el evento de auditoría inicial, sin mostrar identidad
ni credenciales.
