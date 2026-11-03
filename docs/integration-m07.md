# Integración M07 · Clientes internos

## Alcance implementado

M07 incorpora `customers`, `customer_contacts` y `customer_addresses`, sin
semillas. Cada cliente pertenece a una sucursal autorizada y puede asociarse de
forma opcional con una lista comercial M04 activa. El identificador numérico es
estable; código y RFC declarado no nulo son únicos. Nombre, teléfono o correo no
provocan fusiones automáticas.

Las rutas administrativas son `/admin/customers`, `create`, `detail`, `edit`,
`update` y `status`. Toda escritura exige sesión, permiso, alcance de sucursal y
CSRF. Los eventos `customer.create`, `customer.update` y `customer.status` se
registran sin copiar datos personales al contexto de auditoría. Las ediciones y
cambios de estado usan `lock_version` para impedir actualizaciones perdidas.

## Privacidad y continuidad

Los datos opcionales se almacenan como `NULL`; un RFC es dato declarado, no
validación ante el SAT. La desactivación no elimina filas. No hay autenticación
de clientes, rutas públicas, documentos, mensajes ni cotizaciones. M08 podrá
buscar clientes activos autorizados y conservar la FK estable; cualquier
snapshot documental pertenecerá a M08.

## Cierre ejecutado el 2 de noviembre de 2026

Se usó el acceso SSH y Docker ya autorizado del minilab para crear la identidad
exclusiva `ayala_m07_test@192.168.1.9`. Tiene únicamente `SELECT`, `INSERT`,
`UPDATE`, `DELETE`, `CREATE`, `DROP`, `TRIGGER`, `EVENT` y `EXECUTE` sobre
`ferreteria_ayala_test`; globalmente sólo `USAGE`, sin `GRANT OPTION` ni acceso
a desarrollo. La contraseña aleatoria permanece sólo en el `.env` local, que
conserva modo `600`. El ejecutor obtiene la autorización real de
`M07_TEST_AUTHORIZATION=ferreteria_ayala_test`.

La primera integración real detectó que el INSERT reutilizaba un marcador PDO
nombrado con prepares nativos. El estado sintético se preservó, inventarió y
retiró mediante las 48 reversiones antes de repetir. La suite HTTP descubrió y
permitió corregir el alcance global `null` y la captura de sucursal en la
auditoría transaccional. También se corrigió la aserción de detalle para leer el
valor de un control deshabilitado en lugar de su `textContent`.

Resultados finales:

- `npm test`: PASS, 966 comprobaciones acumuladas M00–M07.
- Sintaxis PHP completa, validación JavaScript, `npm run build` y
  `git diff --check`: PASS.
- `npm run test:mysql:m07`: PASS con 48 migraciones, restricciones, clientes
  persona/empresa, contactos, direcciones, duplicados, alcance por sucursal,
  CRUD, versión optimista, estado lógico, auditoría, rollback y limpieza.
- `npm run test:mysql:m07:http`: PASS con alta, edición, detalle, búsqueda,
  filtros, contacto, dirección, rechazo de versión obsoleta, activación lógica,
  CSRF, acceso sin sesión, auditoría y viewports 360, 768, 1024 y 1440 px.
- Después de cada suite, `ferreteria_ayala_test` quedó con cero objetos.
- Antes de migrar desarrollo se generó el respaldo privado
  `storage/backups/ferreteria_ayala-pre-m07-20261102.sql`, modo `600`, 102752
  bytes y SHA-256
  `3705d10c86898a1ebc6be4d124e4cdde791930ea51699842497bd27946f34445`.
- Desarrollo quedó en 48 migraciones, lote 7. Existen las tres tablas M07, los
  cuatro permisos están asignados al Administrador global y `customers` tiene
  cero filas; no se insertaron clientes ficticios.

La identidad M06 independiente sigue siendo un pendiente técnico no bloqueante
para una futura repetición M06; M07 no reutiliza esa identidad.
