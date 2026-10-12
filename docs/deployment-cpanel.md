# Despliegue seguro en cPanel / Apache

Esta guía prepara una publicación posterior; el proyecto no se ha publicado.

## Requisitos

PHP 8.2+ seleccionado para el dominio en MultiPHP Manager; Apache 2.4 con mod_rewrite y AllowOverride. Esta fase usa PHP estándar; habilitar PDO/pdo_mysql cuando se apruebe MySQL. Node solo se utiliza en el equipo de compilación. Confirmar que el proveedor permite servir un document root personalizado.

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
6. Configurar APP_ENV=production en el hosting. El proyecto usa getenv; `.env.example` es documentación, no loader. No hay secretos ni credenciales en esta fase.
7. Activar certificado TLS y redirección HTTPS desde el panel del hosting. No usar el servidor PHP integrado en producción.
8. Verificar `/` (200), ruta inexistente (404), assets y rutas privadas. `/.env`, `/config/app.php`, `/app/Data/DemoHomeData.php` y `/storage/logs/app.log` no deben devolver contenido privado. `/router.php` debe estar denegado o no encontrado.
9. Revisar logs privados, los diálogos, las imágenes y CSP. Probar en teléfono real.
10. Antes de publicación comercial, aprobar contenido real, privacidad, SEO, logo y contactos. Mantener noindex para el prototipo; después definir canonical y Open Graph con dominio absoluto.

No se soporta hoy despliegue bajo subcarpeta URL (`/ayala`). Los assets y enlaces parten de `/`; si se necesita, implementar base path en helpers, navegación y router y probarlo antes de desplegar.

## Bloqueo si no se puede cambiar document root

**No copiar todo el repositorio a public_html.** Solicitar al proveedor que el dominio/subdominio apunte a `public/`, o usar otro plan/proveedor que lo permita. Como alternativa supervisada, mantener todo app/config/storage fuera de public_html y desplegar únicamente el contenido de public con rutas de arranque ajustadas y verificadas. Esa alternativa requiere adaptación explícita; la configuración actual espera directorios hermanos y no debe copiarse a ciegas.

## Actualizaciones

Guardar copia del despliegue anterior. Compilar en local y transferir los assets y vistas de una misma versión; usar ventana de mantenimiento si FTP no permite publicación atómica. Probar rutas y revisar logs después. Rollback: restaurar la versión anterior completa. Nunca almacenar backups, secretos o logs en la carpeta pública.
