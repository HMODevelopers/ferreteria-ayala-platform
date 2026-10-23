# Plan de integración MariaDB para M00 y M01

Estado al 23 de octubre de 2026: la ejecución M01 fallida que creó ocho tablas y
16 registros sintéticos fue inspeccionada, respaldada y restaurada en una base
temporal. El recuperador eliminó únicamente esos fixtures, revirtió las siete
migraciones y confirmó inventario vacío. Después pasaron M00 y M01 completas;
la base de pruebas volvió a quedar vacía.

El diagnóstico identificó que los trabajadores creados con `pcntl_fork()`
heredaban la conexión PDO del padre, incluida la sesión propietaria del bloqueo
asesor exterior. La salida del primer hijo podía cerrar esa sesión y dejar al
padre sin conexión, lo que concuerda con el fallo genérico y la liberación del
bloqueo no confirmada. El ejecutor corregido inicia un proceso PHP aislado antes
de abrir PDO y mantiene por separado sus conexiones de concurrencia. Una nueva
repetición pasó, incluida la concurrencia real.

## Correcciones aplicadas tras la revisión

1. **Invariante de mayúsculas.** Los `CHECK` de `branches.code`, `modules.code`
   y `services.code` comparan ahora `code` y `UPPER(code)` mediante
   `CAST(... AS BINARY)`. La comparación distingue mayúsculas sin modificar la
   colación `ascii_general_ci`, los índices únicos ni la normalización de
   dominio y repositorios. La suite conserva una sonda transaccional para
   demostrarlo en el motor real.
2. **Rollback DDL por lote.** Toda migración reversible declara un preflight.
   `Migrator` valida primero el último lote completo, en orden de reversión, y
   no ejecuta ningún `down()` si una precondición falla. Cada `down()` conserva
   y repite su guarda contra tablas con filas inmediatamente antes de ejecutar
   `DROP TABLE`.
3. **Dependencias (sin hallazgo).** La Home sigue usando `DemoHomeData`; M01 no
   registra rutas ni depende de usuarios, permisos o tablas de M02.

Estas correcciones están verificadas de forma aislada y en MariaDB 10.11. M00
pasó primero; M01 pasó después con su autorización separada.

## Alcance e identidad del destino

- Servidor: `192.168.1.12`, puerto `3306`.
- Contenedor informado: `rc_mysql`; los ejecutores no lo inspeccionan ni lo
  modifican.
- Motor exigido por M01: MariaDB `10.11.x`.
- Base única: `ferreteria_ayala_test`. Debe existir previamente, estar vacía y
  estar dedicada exclusivamente a esta integración.
- Usuarios: cuentas no administrativas separadas. M00 usa
  `TEST_DB_USERNAME_M00`/`TEST_DB_PASSWORD_M00`; M01 y recuperación usan
  `TEST_DB_USERNAME_M01`/`TEST_DB_PASSWORD_M01`. No se documentan valores reales.
- Ambos ejecutores rechazan `root`, bases sin sufijo `_test`, una identidad
  efectiva distinta, objetos preexistentes, privilegios diferentes de los
  exactos y cualquier uso implícito de `DB_*`.

`M01_TEST_AUTHORIZATION` es una barrera adicional y debe coincidir exactamente
con `TEST_DB_DATABASE`. No se debe dejar persistente en una configuración
compartida; se exporta sólo después de la autorización expresa de la ejecución.

## Privilegios mínimos exactos

M00 necesita sobre `ferreteria_ayala_test`:

```sql
SELECT, INSERT, DELETE, CREATE, DROP, TRIGGER, EVENT, EXECUTE
```

M01 necesita los mismos privilegios y además `UPDATE`, porque los repositorios
M01.4 prueban activación lógica y actualización de datos:

```sql
SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, TRIGGER, EVENT, EXECUTE
```

`TRIGGER`, `EVENT` y `EXECUTE` permiten que la cuenta vea esos objetos en
`information_schema` y pueda demostrar que la base está realmente vacía. No se
crean triggers, eventos ni rutinas. La cuenta sólo debe tener `USAGE` global,
sin `GRANT OPTION`, `ALTER`, `CREATE USER`, `GRANT`, `FILE`, `PROCESS`, `SUPER`
ni privilegios sobre otras bases. La creación de la base y del usuario queda
fuera de los ejecutores.

## Variables y comandos propuestos

En una terminal privada, sin cargar `.env`, se suministrarán estas variables:

```bash
export APP_ENV=testing
export TEST_DB_HOST=192.168.1.12
export TEST_DB_PORT=3306
export TEST_DB_DATABASE=ferreteria_ayala_test
export TEST_DB_USERNAME_M00='USUARIO_M00'
export TEST_DB_PASSWORD_M00='VALOR_PRIVADO_M00'
export TEST_DB_USERNAME_M01='USUARIO_M01'
export TEST_DB_PASSWORD_M01='VALOR_PRIVADO_M01'
export TEST_DB_CHARSET=utf8mb4
```

Después de verificar por un canal administrativo independiente que la base es
exclusiva y está vacía, la fase M00 propuesta es:

```bash
npm run test:mysql
```

Sólo si M00 termina en `PASS` y vuelve a dejar la base vacía, la fase M01 se
autoriza de forma separada:

```bash
export M01_TEST_AUTHORIZATION=ferreteria_ayala_test
npm run test:mysql:m01
```

No se ejecuta `bin/migrations.php` ni se exportan `DB_*`. No se crea ni elimina
la base. Las dos fases deben ejecutarse una a la vez.

## Fase A: objetos y operaciones M00

El ejecutor `tests/mysql-integration.php`:

1. valida configuración, identidad efectiva, privilegios e inventario vacío;
2. adquiere un bloqueo asesor exterior exclusivo;
3. crea `schema_migrations` sin `IF NOT EXISTS` para establecer propiedad;
4. registra una migración técnica sintética, consulta su estado y demuestra
   que no se aplica dos veces;
5. revierte el registro técnico;
6. revalida propiedad, historial e inventario;
7. elimina únicamente `schema_migrations` y confirma inventario vacío.

No crea tablas comerciales ni filas fuera de `schema_migrations`. Si la
propiedad o el inventario dejan de ser demostrables, conserva el objeto para
revisión y termina con error.

## Fase B: objetos y operaciones M01

El ejecutor independiente `tests/mysql-m01-integration.php` aplica, en un solo
lote, `schema_migrations` y estas siete tablas:

1. `branches`
2. `modules`
3. `branch_modules`
4. `branch_hours`
5. `branch_special_hours`
6. `services`
7. `branch_services`

No crea semillas. Inserta únicamente registros sintéticos con códigos
aleatorios identificables dentro de la base exclusiva. Comprueba:

- MariaDB 10.11, InnoDB, `utf8mb4`, precisión de coordenadas, restricciones,
  índices, FKs, `CHECK`, unicidad, mayúsculas y valores predeterminados;
- inserciones, actualizaciones, consultas preparadas, páginas, JOIN,
  rehidratación, activación/desactivación y errores seguros de los repositorios;
- horarios divididos, nocturnos y excepcionales, y rechazo de solapamientos;
- commit y rollback de DML;
- protección del rollback de migraciones mientras existen filas;
- una carrera real con dos procesos/conexiones independientes sobre la misma
  sucursal: el segundo escritor espera, relee y rechaza el solapamiento;
- timeout de bloqueo con rollback y un deadlock real con exactamente una
  transacción víctima.

La concurrencia requiere PHP CLI de 64 bits con `proc_open`. El trabajador se
inicia antes de que el proceso principal abra PDO y usa un canal JSON acotado;
las credenciales no se envían como argumentos ni se imprimen. Los 64 bits son
necesarios para mapear de forma segura los identificadores `BIGINT UNSIGNED` al
tipo `int` del dominio. Si alguna condición falta, el preflight termina antes
de conectar. No se sustituye por una simulación secuencial.

## Limpieza y verificación

M01 no inicia limpieza si falla una comprobación. En una ejecución completa:

1. vuelve a probar propiedad de `schema_migrations`;
2. exige que el inventario contenga exactamente las ocho tablas previstas y
   que el historial contenga exactamente las siete migraciones conocidas;
3. elimina las filas sintéticas en orden seguro de FKs;
4. verifica que las siete tablas comerciales estén vacías;
5. ejecuta el preflight integral y el rollback del lote en orden inverso;
6. comprueba que sólo quede `schema_migrations` vacía y de propiedad demostrada;
7. elimina esa tabla y exige inventario final vacío.

Nunca usa `DROP DATABASE` ni `TRUNCATE`. No elimina objetos desconocidos. Un
fallo antes de demostrar todas las precondiciones deja los objetos para
inspección. El preflight evita comenzar si alguna tabla ya contiene datos, pero
MariaDB puede confirmar cada `DROP TABLE` de forma implícita; una carrera, caída
o fallo DDL posterior todavía podría dejar un rollback parcial.

## Riesgos residuales y recuperación

- Los bloqueos asesores son cooperativos: otro proceso ajeno a estos ejecutores
  podría ignorarlos. Por eso la base debe estar dedicada y sin otros usuarios.
- `GET_LOCK` pertenece a la sesión. Si la conexión termina, MariaDB debe liberar
  el bloqueo; el ejecutor fallido no pudo confirmarlo mediante `RELEASE_LOCK`.
  Antes de recuperar o repetir, un administrador debe confirmar por separado
  que no existe una sesión de la ejecución anterior.
- Una caída del proceso o de red puede dejar tablas o filas. No se debe
  reintentar automáticamente porque el preflight rechazará una base no vacía.
- El rollback DDL no es atómico. Ante cualquier `ERROR` o `WARNING`, conservar
  la base, revocar temporalmente acceso de otros procesos y obtener inventario,
  conteos e historial por un administrador.
- Comparar los objetos encontrados con las ocho tablas enumeradas y los IDs de
  `database/migrations/`. No borrar nada hasta determinar propiedad y estado.
- Si hay objetos o filas ajenos, preservar todo y escalar. Si todos los objetos
  pertenecen inequívocamente a la ejecución sintética, preparar una limpieza
  manual específica y solicitar una nueva autorización; nunca usar
  `DROP DATABASE`, `TRUNCATE` global ni el rollback normal sobre otra base.
- Después de una limpieza manual autorizada, demostrar inventario vacío antes
  de considerar un nuevo intento.

### Ejecutor de recuperación preparado

`tests/mysql-m01-recovery.php` ofrece dos modos deliberadamente separados. El
modo `inspect` obtiene un inventario nuevo sin modificar filas, objetos,
historial ni bloqueos. Verifica el endpoint exacto, base e identidad efectiva,
MariaDB 10.11, los privilegios mínimos M00 de inspección, las ocho tablas, estructura, restricciones
e índices, las siete migraciones del lote 1 y el grafo completo de los 16
registros sintéticos por IDs, valores y relaciones. Los conteos por sí solos no
constituyen propiedad.

El modo `recover` exige además el permiso M01 `UPDATE` y está deshabilitado sin confirmaciones exactas adicionales,
una constancia administrativa de ausencia de sesiones anteriores y un respaldo
externo al repositorio y a `public/`. La prueba del respaldo incluye tamaño y
SHA-256, estado exacto y una restauración independiente documentada en un
manifiesto; el ejecutor no crea ni sobrescribe respaldos. Después adquiere el
mismo bloqueo asesor exterior de la integración M01, repite toda la inspección,
elimina exclusivamente las
filas identificadas dentro de una transacción y confirma las tablas vacías.
Sólo entonces delega el rollback al `Migrator`, verifica el historial vacío,
elimina `schema_migrations` vacía y exige inventario final vacío.

La cuenta limitada puede ver sus propias sesiones en `PROCESSLIST`, pero sin el
privilegio global peligroso `PROCESS` no puede demostrar sesiones pertenecientes
a otras cuentas. Por eso la confirmación administrativa independiente es una
barrera obligatoria y la base debe continuar dedicada. Los bloqueos asesores
siguen siendo cooperativos. El DML sí puede revertirse atómicamente antes de
confirmar; los `DROP TABLE` posteriores pueden producir commits implícitos. Un
fallo DDL detiene el proceso sin continuar ni intentar una limpieza automática,
y exige inspeccionar otra vez el inventario antes de decidir cualquier acción.

Estos comandos permanecen disponibles para futuras recuperaciones autorizadas:

```bash
npm run inspect:mysql:m01-recovery
npm run recover:mysql:m01-recovery
```

El manifiesto JSON del respaldo debe contener exactamente `database`, `tool`
(`mariadb-dump`), `dump_completed`, `restore_tested`, `verification_method`
(`independent-restore`), `dump_sha256`, `dump_size`, `created_at`, el inventario
de `objects`, los siete `migration_ids` y los `row_counts` documentados. Debe
tener menos de 24 horas. La restauración de comprobación se hace en otro destino
temporal, nunca sobre `ferreteria_ayala_test`, y su creación y eliminación
requieren autorización administrativa independiente. El ejecutor no genera ni
acepta como evidencia un manifiesto incompleto y tampoco recibe contraseñas en
argumentos de línea de comandos.

## Criterio de cierre

M00 terminó en `PASS` y confirmó inventario final vacío. M01.1–M01.4 terminaron
en `PASS`, incluida la concurrencia real y la limpieza final. M00 y M01 están
integrados y listos para revisión final; M02 permanece fuera del alcance y
requiere una autorización de fase independiente.
