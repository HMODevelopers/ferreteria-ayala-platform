# Despliegue seguro en cPanel / Apache

Esta guía prepara una publicación posterior; el proyecto no se ha publicado.

## Requisitos

PHP 8.2+ seleccionado para el dominio en MultiPHP Manager; Apache 2.4 con mod_rewrite y AllowOverride. PDO y `pdo_mysql` son obligatorios para los comandos de persistencia. Node solo se utiliza en el equipo de compilación. Confirmar que el proveedor permite servir un document root personalizado.

## Compilar y revisar localmente

```bash
npm ci
npm run build
npm test
```

Los assets compilados están en `public/assets/`. Conservar fuentes, fotografías, JS, CSS y licencias. No subir node_modules ni depender de una compilación del servidor.

## Publicar con un document root correcto

1. Crear carpeta privada para el proyecto, por ejemplo `/home/USUARIO/ayala-platform` fuera de cualquier raíz pública compartida.
2. Subir app, bootstrap, config, public y storage. Se puede conservar documentación fuera del document root; no es necesaria para servir. No subir `.git`, attachments, node_modules ni tests/artifacts.
3. Configurar dominio o subdominio para apuntar exactamente a `/home/USUARIO/ayala-platform/public`. Un subdominio sirve en `/`, no es instalación bajo `/ayala`.
4. Confirmar que `.htaccess` fue transferido como archivo oculto y mod_rewrite funciona. `Options -Indexes -MultiViews` debe estar autorizado; si el proveedor lo prohíbe, pedir configuración equivalente, no quitar protección sin comprobar directorios.
5. Permisos usuales: carpetas 755, archivos 644; solo `storage/logs` requiere escritura por PHP. No usar 777. Ajustar propietario/grupo según el handler PHP del hosting.
6. Configurar las variables del entorno de producción en Apache/cPanel. El proyecto usa `getenv()`; `.env.example` documenta el contrato y no es un loader. `.env` debe permanecer fuera de Git y nunca debe quedar bajo el document root.
7. Activar certificado TLS y redirección HTTPS desde el panel del hosting. No usar el servidor PHP integrado en producción.
8. Verificar `/` (200), ruta inexistente (404), assets y rutas privadas. `/.env`, `/config/app.php`, `/app/Data/DemoHomeData.php` y `/storage/logs/app.log` no deben devolver contenido privado. `/router.php` debe estar denegado o no encontrado.
9. Revisar logs privados, los diálogos, las imágenes y CSP. Probar en teléfono real.
10. Antes de publicación comercial, aprobar contenido real, privacidad, SEO, logo y contactos. Mantener noindex para el prototipo; después definir canonical y Open Graph con dominio absoluto.

## Variables de entorno

Para la revisión previa a producción usar como mínimo:

```dotenv
APP_NAME="Ferretería Ayala"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://DOMINIO-CONFIRMADO
APP_TIMEZONE=America/Hermosillo
APP_LOCALE=es_MX
APP_INDEXING=false
SESSION_SECURE_COOKIE=true
```

Las variables `DB_*` y el resto de `SESSION_*` están descritas en
`.env.example`. La Home no usa la conexión, pero el CLI de migraciones sí.
Las credenciales reales de MySQL se obtendrán en cPanel y no se copiarán a
documentación, Git ni archivos públicos. `APP_INDEXING` debe seguir
en `false` mientras el sitio sea un prototipo; su activación requerirá la
revisión de SEO y contenido comercial.

## Migraciones por ambiente

Usar bases y usuarios independientes, nunca `root`: desarrollo puede apuntar a
`ferreteria_ayala_dev`, pruebas a una base exclusiva terminada en `_test` y
producción a `ferreteria_ayala`. Los nombres son ejemplos de configuración, no
instrucciones para crear bases. Antes de cada migración de producción realiza y
verifica un respaldo, revisa pendientes y programa una ventana de mantenimiento:

```bash
php bin/migrations.php status
php bin/migrations.php migrate --force
```

`rollback --force` solo revierte el último lote si todas sus migraciones tienen
reversión segura. El flag confirma el ambiente; no omite validación ni elige una
base automáticamente. Ante un fallo DDL no reintentes a ciegas: MySQL/MariaDB
puede haber confirmado cambios parciales.

En local se exportan las variables desde la terminal. Para el minilab actual,
`DB_HOST=192.168.1.12` solo es válido cuando la laptop puede alcanzar ese host.
En un despliegue Docker futuro, `DB_HOST=rc_mysql` requiere que aplicación y
MariaDB compartan una red Docker con resolución de ese nombre. No se debe usar
`localhost` para llegar a otro contenedor. Este proyecto no crea ni modifica
contenedores, redes o bases.

En cPanel define `DB_*` mediante el mecanismo de entorno del proveedor y ejecuta
el CLI desde la raíz privada del proyecto, nunca desde `public/`. Confirma antes
la versión de PHP CLI y `php -m | grep -i pdo_mysql`, porque puede diferir de la
versión seleccionada para Apache.

La integración se ejecuta únicamente con `APP_ENV=testing` y `TEST_DB_*`
explícitas mediante `npm run test:mysql`. Rechaza una base sin sufijo `_test` o
que ya contenga `schema_migrations`; no usa `DB_*`, no crea bases y retira solo
la tabla técnica que creó. En servidores compartidos requiere autorización
previa y una cuenta limitada a la base de pruebas.

No se soporta hoy despliegue bajo subcarpeta URL (`/ayala`). Los assets y enlaces parten de `/`; si se necesita, implementar base path en helpers, navegación y router y probarlo antes de desplegar.

## Bloqueo si no se puede cambiar document root

**No copiar todo el repositorio a public_html.** Solicitar al proveedor que el dominio/subdominio apunte a `public/`, o usar otro plan/proveedor que lo permita. Como alternativa supervisada, mantener todo app/config/storage fuera de public_html y desplegar únicamente el contenido de public con rutas de arranque ajustadas y verificadas. Esa alternativa requiere adaptación explícita; la configuración actual espera directorios hermanos y no debe copiarse a ciegas.

## Actualizaciones

Guardar copia del despliegue anterior. Compilar en local y transferir los assets y vistas de una misma versión; usar ventana de mantenimiento si FTP no permite publicación atómica. Probar rutas y revisar logs después. Rollback: restaurar la versión anterior completa. Nunca almacenar backups, secretos o logs en la carpeta pública.
