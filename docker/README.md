# Desarrollo local con Docker

Requiere Docker Compose y el contenedor existente `mysql-local` conectado a
`mysql_default`. Este Compose administra únicamente PHP; MySQL y sus volúmenes
pertenecen a su entorno original. La conexión entre contenedores usa
`DB_HOST=mysql-local` y `DB_PORT=3306` en el `.env` local, que no se versiona.

Los comandos usan `--env-file .env.example` para la configuración de Compose.
El bootstrap común de PHP carga el `.env` real con el parser del proyecto, sin
interpolar sus secretos mediante Compose. Ninguna credencial se incorpora a las
imágenes.

```powershell
docker compose --env-file .env.example up -d --build
docker compose --env-file .env.example exec php php bin/migrations.php status
docker compose --env-file .env.example exec php php bin/migrations.php migrate
docker compose --env-file .env.example exec php php tests/run.php
```

Abre http://127.0.0.1:8080. El document root es `public/`. El bootstrap intenta
cargar `.env` en todos los entornos y conserva cualquier variable que Apache,
cPanel o Docker ya haya definido en el proceso.

Para crear la primera cuenta administradora, ejecuta en una terminal interactiva:

```powershell
docker compose --env-file .env.example exec php php bin/create-initial-admin.php
```

El comando solicita los datos y la contraseña de forma interactiva. Las
migraciones crean el esquema y los registros técnicos definidos en ellas;
los datos comerciales de otra máquina requieren un respaldo independiente.

Los assets se pueden generar con Node 22 en Docker:

```powershell
docker compose --env-file .env.example run --rm assets npm ci
docker compose --env-file .env.example run --rm assets npm run build
```

Si la red local devuelve `SELF_SIGNED_CERT_IN_CHAIN`, usa un archivo PEM con
las autoridades de confianza autorizadas en esta máquina. En este entorno se
exportaron los certificados públicos de los almacenes Root de Windows a
`storage/logs/docker-trusted-roots.pem` (ignorado por Git):

```powershell
docker compose --env-file .env.example run --rm -e NODE_EXTRA_CA_CERTS=/workspace/storage/logs/docker-trusted-roots.pem assets npm ci
```

No desactives la validación TLS. El archivo de certificados es local a esta
máquina y no contiene claves privadas.

No hace falta instalar PHP ni Node en Windows. Node no se ejecuta en producción.
`docker compose stop`
detiene PHP; `docker compose down` retira este servicio, sin administrar MySQL.

La cuenta de aplicación debe tener permisos únicamente sobre su base. Las
pruebas MySQL requieren configurar aparte las cuentas `TEST_DB_*` y una base
exclusiva de pruebas; nunca deben apuntar a la base de desarrollo.
