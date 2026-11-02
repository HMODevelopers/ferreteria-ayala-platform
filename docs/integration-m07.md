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

## Punto de control del 2 de noviembre de 2026

- `npm test`: PASS, 963 comprobaciones.
- Sintaxis PHP completa: PASS.
- Desarrollo: MariaDB 10.11.15, 46 migraciones M00–M06 aplicadas; las dos M07
  están pendientes y no se crearon clientes.
- Integración: no ejecutada. `.env` no contiene identidad M07; `ayala_app` no
  tiene privilegios sobre `ferreteria_ayala_test` ni `GRANT OPTION`, y el host
  no dispone de cliente `mysql`/`mariadb` ni acceso local al contenedor. No se
  reutilizó otra cuenta.

Para continuar, un administrador debe crear una identidad exclusiva M07 con
privilegios de esquema mínimos sobre `ferreteria_ayala_test`, configurar
`TEST_DB_*_M07` y `M07_TEST_AUTHORIZATION`, y ejecutar `npm run test:mysql:m07`.
Sólo tras su PASS y limpieza debe ejecutarse la suite HTTP y migrar desarrollo.
La identidad M06 independiente sigue siendo un pendiente técnico no bloqueante
para una futura repetición M06.
