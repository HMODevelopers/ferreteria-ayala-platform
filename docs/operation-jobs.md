# Núcleo durable de trabajos operativos

Estado al 10 de noviembre de 2026: **implementación e integración multiproceso comprobadas contra MariaDB 10.11.15 en el minilab**. La regresión posterior a M12 aprobó 50 aserciones SQL, 71 migraciones, 60 reclamaciones concurrentes, recuperación, fencing y rollback; la base aislada terminó vacía. La migración y la suite contemplan MySQL 8.4, pero esa versión no está instalada en este host y por tanto su ejecución real continúa pendiente.

El núcleo es infraestructura disponible para M12, mensajería y M14. El primer evento M12 implementado después de este núcleo usa una escritura transaccional directa porque nace dentro de la misma base y transacción que la incidencia M10; no necesita cola ni handler. El núcleo sigue sin implementar SMTP, devoluciones, reportes o respaldos y no expone rutas HTTP para ejecutar trabajos.

## Arquitectura y ciclo de vida

`operation_jobs` conserva la identidad lógica, payload JSON, origen, disponibilidad, estado, contador de intentos, resultado seguro y lease vigente. `operation_job_runs` es un historial inmutable por número de intento. La llave foránea usa `ON DELETE RESTRICT`.

| Estado | Entrada válida | Salida |
|---|---|---|
| `pending` | encolado nuevo | `running` |
| `running` | reclamación transaccional | `succeeded`, `retry_wait` o `intervention` |
| `retry_wait` | fallo recuperable o recuperación de worker | `running` cuando vence `available_at` |
| `succeeded` | terminación correcta | terminal |
| `intervention` | fallo permanente o intentos agotados | terminal, salvo `retry <id>` autorizado |

Las restricciones `CHECK` impiden leases incompletos, estados terminales sin `finished_at` e intentos por encima del límite. Una intervención manual puede ampliar el máximo sólo hasta 20 intentos.

## Concurrencia, propiedad y recuperación

La reclamación ejecuta `SELECT ... FOR UPDATE SKIP LOCKED` y el cambio de estado dentro de una transacción. Cada reclamación genera además un token aleatorio de 256 bits. `worker_id` identifica al proceso para observabilidad, pero no es una credencial: heartbeat y finalización requieren simultáneamente trabajo, worker y token vigentes. El token no aparece en `history` ni en `operation_job_runs`.

Un handler largo debe llamar `JobExecutionContext::heartbeat()` entre bloques. `recoverStale()` recupera un máximo de 100 trabajos cuyo heartbeat venció; cierra el intento anterior como `WORKER_LOST` y mueve el trabajo a `retry_wait` o `intervention`. Un ejecutor antiguo no puede finalizar después de una nueva reclamación porque su token ya no coincide.

La garantía es **ejecución al menos una vez**, no exactamente una vez. Si un proceso modifica un sistema externo y muere antes de confirmar la cola, el efecto puede repetirse. Cada futuro handler debe proteger sus efectos con una clave idempotente propia y, cuando corresponda, una transacción en el sistema destino.

## Reintentos e idempotencia

El primer reintento espera `JOB_RETRY_BASE_SECONDS`; los siguientes duplican la espera hasta `JOB_RETRY_MAX_SECONDS`. Los fallos permanentes y los recuperables agotados van a `intervention`. Reiniciar workers no pierde el contador ni el historial.

La unicidad `(job_name, idempotency_key)` hace que productores concurrentes obtengan una sola identidad lógica. Una clave nula permite solicitudes independientes. La deduplicación de encolado no equivale a ejecución exactamente una vez.

La única tarea de producción registrada ahora es `operations.database_health`. Acepta exclusivamente un payload vacío, renueva el heartbeat y ejecuta `SELECT 1`; no cambia datos comerciales.

## CLI privado

Desde la raíz privada del proyecto:

```bash
php bin/jobs.php handlers
php bin/jobs.php schedule
php bin/jobs.php enqueue operations.database_health --origin=manual --key=health-manual-001
php bin/jobs.php work --limit=25 --stale-after=900
php bin/jobs.php history --limit=25
php bin/jobs.php retry 123
```

`schedule` materializa el diagnóstico correspondiente a la ventana UTC actual de cinco minutos. Invocarlo más de una vez en la ventana devuelve la misma identidad. `work` es una responsabilidad distinta: recupera leases vencidos y ejecuta lo ya pendiente. Ningún comando se registra en `config/routes.php`.

Los payloads no deben contener contraseñas, tokens ni cadenas de conexión. Los errores esperados deben usar `JobFailure` con código y resumen seguros. Las excepciones inesperadas se reemplazan por `UNEXPECTED` y una referencia hash; el mensaje original no se persiste.

## Programación local y cPanel

El archivo [jobs.cron.example](../deploy/cron/jobs.cron.example) está listo para este checkout. No se instaló en el cron global. Usa locks separados, timeout de 55 segundos y logs separados:

- cada cinco minutos, `schedule` materializa trabajo recurrente;
- cada minuto, `work` procesa hasta 25 pendientes;
- `flock -n` evita solapamiento de una misma responsabilidad;
- cron vuelve a invocar el proceso después de un fallo.

Prueba no destructiva antes de instalar:

```bash
cd /home/carlos-lafarga/Desarrollo/ferreteria-ayala-platform
/usr/bin/flock -n storage/jobs-schedule.lock /usr/bin/timeout 55s /usr/bin/php bin/jobs.php schedule
/usr/bin/flock -n storage/jobs-worker.lock /usr/bin/timeout 55s /usr/bin/php bin/jobs.php work --limit=25
tail -n 50 storage/logs/jobs-schedule.log storage/logs/jobs-worker.log
```

En cPanel se copia cada línea de cron cambiando únicamente la ruta absoluta por la instalación privada. El usuario del sistema necesita lectura del proyecto y escritura en `storage/`; las variables `DB_*` deben existir en el entorno que carga `.env`. El document root permanece en `public/`.

## Configuración

| Variable | Predeterminado | Rango | Propósito |
|---|---:|---:|---|
| `JOB_RETRY_BASE_SECONDS` | 30 | 1–3600 | espera inicial |
| `JOB_RETRY_MAX_SECONDS` | 3600 | 1–86400 | tope exponencial |
| `JOB_STALE_AFTER_SECONDS` | 900 | 60–86400 | lease sin heartbeat |
| `JOB_BATCH_LIMIT` | 25 | 1–200 | máximo por worker |

## Suite SQL aislada

Configurar una identidad M12 limitada a una base vacía cuyo nombre termine en `_test`. La autorización debe repetir exactamente ese nombre:

```dotenv
TEST_DB_HOST_M12=127.0.0.1
TEST_DB_PORT_M12=3306
TEST_DB_DATABASE_M12=ferreteria_ayala_jobs_test
TEST_DB_USERNAME_M12=usuario_m12_limitado
TEST_DB_PASSWORD_M12=secreto_local
TEST_DB_CHARSET_M12=utf8mb4
M12_TEST_AUTHORIZATION=ferreteria_ayala_jobs_test
```

No reutilizar `DB_DATABASE`, `DB_USERNAME`, `ferreteria_ayala` ni una cuenta administrativa. La suite comprueba identidad efectiva e inventario vacío antes de crear algo; si falla después de migrar, preserva el estado para inspección en vez de borrar objetos desconocidos.

```bash
npm run test:mysql:m12:jobs
```

La suite aplica todas las migraciones con `Migrator`, repite `migrate`, inspecciona columnas/índices/FK, ejecuta el diagnóstico, lanza procesos PHP independientes, repite 60 reclamaciones concurrentes, prueba seis productores con la misma clave, mantiene un row lock para validar `SKIP LOCKED`, mata un worker, recupera el lease, prueba fencing, heartbeats, backoff 1/2 segundos, agotamiento, fallos permanentes, sanitización e invariantes SQL. Después demuestra que el rollback se niega con historial y que, una vez eliminados únicamente los registros propios, revierte el batch y deja vacía la base. Si no existen variables M12, el ejecutor reutiliza las variables base de prueba y la identidad limitada M02 ya establecida; una configuración M12 parcial se rechaza.

Resultado real repetido después de integrar notificaciones el 10 de noviembre de 2026 con PHP 8.5.4, `PDO` y `pdo_mysql`: `PASS: M12 jobs SQL, 50 assertions, 71 migrations, 60 concurrent claims, recovery, fencing and isolated rollback on 10.11.15-MariaDB-ubu2204.` Durante el cierre se reprodujo un deadlock de índice pese a `SKIP LOCKED`; la reclamación ahora reintenta hasta cinco veces con jitter y rollback confirmado. El host no dispone del comando Docker; la prueba se conectó directamente al servicio MySQL/MariaDB aislado ya configurado.

## Diagnóstico

- `Unable to connect to the configured database`: verificar servicio existente, ruta de red, puerto y firewall. No cambiar a la base empresarial.
- `M12 test authorization mismatch`: `M12_TEST_AUTHORIZATION` no coincide byte por byte con `TEST_DB_DATABASE_M12`.
- `Integration test refused ... not empty`: no limpiar automáticamente; inspeccionar la base o asignar otra base exclusiva.
- `Another M12 integration process is active`: esperar a que termine el dueño del advisory lock.
- trabajos en `intervention`: consultar `history`, corregir la causa y sólo entonces usar `retry <id>`.
- workers recuperados como stale durante tareas sanas: reducir el tamaño de cada bloque y emitir heartbeats; no bajar producción sólo para acomodar una prueba.

La migración `20261108090000_create_operation_jobs.php` tiene rollback protegido. Nunca se usa rollback como demostración sobre desarrollo o producción.
