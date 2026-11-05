# Clientes: directorio y modales

El directorio utiliza todo el ancho disponible, filtros adaptables a la pantalla, encabezado de resultados y estados vacíos distintos para un directorio sin registros y para una búsqueda sin coincidencias. Se retira M07 de la interfaz y se explica el propósito del registro de clientes sin referencias a fixtures o a la implementación.

Alta, edición, consulta y cambio de estado usan el dialog lateral compartido. La consulta es una ficha legible con identificación, comunicación, contacto, dirección y actividad, en lugar de un formulario deshabilitado. El formulario agrupa datos básicos, comunicación e información comercial; contacto y dirección son opcionales. Activar o desactivar abre una confirmación con el cliente seleccionado y conserva el POST, CSRF y versión existente. Su nuevo GET se registra en config/routes.php y comprueba permiso y alcance de sucursal.

Los errores controlados devuelven 422 y conservan lo capturado dentro del modal. Se preservan las advertencias de posibles coincidencias, auditoría y validaciones del servicio. La versión enviada no se sustituye por la versión actual al devolver un error de concurrencia. Los formularios de clientes se vuelven a consultar al abrirlos para evitar datos de edición obsoletos. El guardado correcto actualiza la lista con la respuesta del servidor y conserva su confirmación. El acceso directo a las rutas sigue funcionando sin JavaScript.

Validación: 1427 comprobaciones locales, 403 archivos PHP con sintaxis válida, JS y build. Chrome de presentación: 25 comprobaciones en 360/768/1440/2560 px, altas, edición desde detalle, confirmación de estado, filtros, valores preservados y permisos visibles; capturas de escritorio/móvil revisadas. Regresiones del modal compartido: 128 comprobaciones del contenido y 38 de biblioteca.

La regresión HTTP/Chrome M11 con --customers aprobó 92 aserciones y limpieza completa del esquema aislado. Incluye 11 comprobaciones autenticadas de Clientes: CSRF, validación con datos preservados, alta, edición, contacto/dirección persistidos, conflicto de versiones, desactivación, filtros y auditoría. Se reutiliza el recorrido de Clientes desde su ejecutor M07 y desde la base aislada moderna M11; no se crean cuentas ni clientes en desarrollo.

Ejecutores:

    docker exec ferreteria-ayala-php-1 php tests/m11-lint.php
    docker exec ferreteria-ayala-php-1 php tests/run.php
    docker exec ferreteria-ayala-php-1 php tests/customers-design-fixtures.php
    docker compose --env-file .env.example run --rm assets npm run build
    node tests/customers-design-browser.mjs
    node tests/m11-http-integration.mjs --customers

Las rutas reales de directorio y confirmación de estado responden 303 sin sesión. Evidencias de presentación en tests/artifacts/customers-design/ y de la regresión autenticada en tests/artifacts/m11-http-pass.json, ignoradas por Git. Sin cambios de esquema, datos de desarrollo, servicios comerciales ni .env.
