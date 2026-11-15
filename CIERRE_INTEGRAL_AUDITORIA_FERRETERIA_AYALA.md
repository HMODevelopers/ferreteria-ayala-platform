# Cierre integral de auditoría — Ferretería Ayala

Actualizado: 15 de noviembre de 2026. El detalle y estado de los 26 hallazgos está en `CONTROL_CIERRE_AUDITORIA_FERRETERIA_AYALA.md`.

## A. Resumen ejecutivo

Esta intervención corrigió los cuatro defectos de programación abiertos AY-10, AY-16, AY-17 y AY-23, aplicó la migración aditiva 76 y elevó la regresión local de 1609 a 1627 controles PASS. M00, M02 y M05 aprobaron en MariaDB aislado; M05 validó concurrencia, rollback, conversiones y el caso de más de 500 variantes. La pantalla de reportes aprobó controles responsive en 360, 768, 1440 y 2560 px.

La ejecución también corrigió una deficiencia de la propia infraestructura: M10 solo migraba hasta M10/migración 61, por lo que no podía demostrar AY-04. Ahora exige las 75 migraciones hasta la cobertura de transferencias. La identidad aislada disponible carece del privilegio `ALTER` que requiere la migración 75; por ello el flujo central todavía no puede declararse verificado. Los preflights fallidos se limpiaron únicamente después de comprobar identidad, historial y propiedad, y el esquema aislado terminó vacío.

## B. Defectos corregidos en esta intervención

- AY-10: historial durable de importaciones, confirmación idempotente por token y auditoría dentro de la misma transacción que crea productos. Un fallo posterior del JSON privado ya no puede convertir un commit comercial en un mensaje falso de “sin cambios”.
- AY-16: el reporte parte de la recepción original y reúne solicitudes, seguimientos y documentos sin doble conteo. La primera atención solo se calcula con eventos posteriores o iguales a la recepción.
- AY-17: se agregaron cotizaciones por vendedor, Kardex/movimientos, mínimos y agotados, entregas parciales, importaciones/errores y auditoría. Pantalla, XLSX y PDF usan las mismas filas y columnas; importaciones y auditoría conservan permisos adicionales.
- AY-23: los costos de cada factura se normalizan a la unidad base usando su conversión válida y solo se compara contra documentos realmente anteriores por fecha e identificador. No se actualizan precios de venta.
- Infraestructura M10: migración máxima actualizada de 61 a 75, limpieza ampliada y contrato explícito de privilegio aislado `ALTER`.

## C. Hallazgos cerrados y verificados

- AY-15: cerrado con M05 MariaDB real; el mínimo se configuró para la variante 501, con rollback y limpieza final.
- AY-24: permanece cerrado; el contador usa el número real de banners.

AY-10, AY-16, AY-17 y AY-23 están corregidos, pero conservan validación SQL controlada pendiente. AY-04/05/06/07, AY-18/19/21/22/25 mantienen sus correcciones previas y la evidencia o validación externa pendiente indicada en la matriz.

## D. Pruebas ejecutadas

- `php tests/run.php`: PASS, 1627 controles.
- `npm run build`: PASS con Tailwind 4 local.
- `git diff --check`: PASS.
- Sintaxis PHP de todos los archivos PHP cambiados y de la nueva migración: PASS.
- M00 MariaDB: migración técnica, lock, duplicado, rollback y limpieza PASS.
- M02 MariaDB: esquema, administrador inicial, login, sesiones, permisos, alcance, revocación y auditoría PASS.
- M05 MariaDB: 41 migraciones, DECIMAL, conversiones, inventario inicial, ajustes, idempotencia, insuficiencia, concurrencia independiente, timeout/rollback y mínimos >500 PASS.
- Consultas AY-16/17/23: preparadas y ejecutadas en modo lectura contra MariaDB principal sin errores; el reporte de importaciones también aprobó después de la migración 76.
- Reportes en navegador: 5 controles PASS en 360, 768, 1440 y 2560 px.
- AY-19: 9999/10000 aceptados y 10001/10002 rechazados por la política común antes de elegir PDF o XLSX.
- M10: no aprobado. El preflight actualizado detectó correctamente que falta `ALTER`; no se atribuye como PASS.

## E. Base de datos y migraciones

- Principal: 76 migraciones, 107 tablas.
- Nueva migración: `20261114090000_create_catalog_import_runs`.
- La migración 76 fue aplicada a la principal y crea únicamente historial durable de importaciones; no cargó datos ni fixtures.
- La cuenta comercial es `ayala_app@%` sobre MariaDB 10.11 y solo tiene privilegios sobre `ferreteria_ayala`; no puede crear bases ni usuarios. No se intentó evadir esa limitación.
- Las ejecuciones aisladas M00/M02/M05 terminaron limpias. Los dos estados preservados por intentos M10 fueron identificados y limpiados con verificaciones explícitas; el esquema `_test` quedó vacío.

## F. Intervención mínima requerida del propietario

Un administrador de MariaDB debe crear un esquema M10 nuevo y vacío cuyo nombre termine en `_test`, y una cuenta no administrativa exclusiva, distinta de `ayala_app`. Los privilegios de esquema exactos son:

`ALTER, CREATE, DELETE, DROP, EVENT, EXECUTE, INSERT, SELECT, TRIGGER, UPDATE`

La cuenta solo debe tener `USAGE` global, sin `GRANT OPTION`. Después deben configurarse `TEST_DB_HOST_M10`, `TEST_DB_PORT_M10`, `TEST_DB_DATABASE_M10`, `TEST_DB_USERNAME_M10`, `TEST_DB_PASSWORD_M10`, `TEST_DB_CHARSET_M10` y `M10_TEST_AUTHORIZATION`. La autorización debe coincidir exactamente con el nombre del esquema. Esta identidad permitirá ejecutar migración 75, fixtures, concurrencia y rollback sin tocar la base comercial.

También se requiere un destino aislado autorizado para la prueba de restauración. M03 y M07 conservan credenciales antiguas que no conectan, aunque sus trabajos independientes no bloquean el M05 ya aprobado.

## G. Pendientes de contenido o decisión expresa

- Privacidad oficial, catálogo publicable, módulos y sucursales receptoras para AY-01.
- Formato XLSX real y reglas comerciales ambiguas de actualización para completar AY-09; el historial durable ya existe.
- Políticas de lotes, caducidades y series para completar AY-08.
- Matriz aprobada de eventos/destinatarios internos para AY-11.
- Evidencia del hosting, retención y destino de restauración para AY-13.
- Datos oficiales de sucursales, servicios, horarios y mapas para AY-20.
- Formato físico de etiquetas y hardware para la impresión de AY-26.
- SMTP permanece aplazado; no se habilitaron envíos externos.

## H. Riesgos técnicos abiertos

- AY-04/06/07 y el flujo central de cotizaciones/transferencias/entregas no tienen todavía PASS con migración 75 por falta de la identidad M10 con `ALTER`.
- AY-10 necesita fallos controlados antes y después de commit en una base aislada con migración 76.
- AY-16/17/23 necesitan fixtures SQL con solicitudes convertidas, unidades mixtas, facturas fuera de orden y exportaciones autenticadas con datos.
- Restauración independiente y comportamiento real de cPanel/Apache continúan sin evidencia externa.

## I. Estado de preparación

El sistema administrativo está estable para continuar validaciones técnicas y para una revisión funcional del cliente que no dependa de los flujos pendientes. No se acredita aún como cierre técnico definitivo ni como listo para validación final completa porque falta la integración M10 con migración 75 y las pruebas SQL controladas de los cuatro defectos recién corregidos.

No se inició el rediseño de la Home. El trabajo público posterior queda separado de estos pendientes y deberá usar contenido oficial, privacidad, catálogo y sucursales reales.

## Dictámenes

**A. CIERRE TÉCNICO DE LA AUDITORÍA: PARCIAL.**

Justificación: no quedan los cuatro defectos abiertos sin corrección de código, AY-15 tiene evidencia SQL real y la regresión/build/responsive están en PASS; sin embargo, M10 y los fixtures SQL de AY-10/16/17/23 aún no pueden declararse aprobados.

**B. SISTEMA ADMINISTRATIVO LISTO PARA VALIDACIÓN FINAL CON EL CLIENTE: PARCIAL.**

Justificación: la base administrativa es coherente y probada en gran parte, pero la validación final debe esperar la identidad M10, el flujo SQL central y las conciliaciones de reportes/costos. Los faltantes de contenido oficial se mantienen separados de los defectos técnicos.
