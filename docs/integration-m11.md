# Integración M11 · CMS público

Inicio: 3 de noviembre de 2026. Cierre: 4 de noviembre de 2026 (America/Phoenix). Implementación, validación y activación administrativa en desarrollo completadas. Publicación pública desactivada hasta disponer de contenido y datos reales.

## Alcance oficial y decisiones autorizadas

Fuentes: docs/modules.md (M11), docs/requirements.md (CMS, sitio público y entregas), docs/data-model.md (contenido/archivos), cierres M03, M06, M08 y M10. M11 es CMS público: Home, banners, promociones publicitarias, eventos, testimonios, páginas institucionales, navegación, SEO, medios, aviso de privacidad versionado y condiciones comerciales por sucursal. Formularios estructurados; sin constructor libre ni HTML comercial ejecutable. Depende de M01/M02 e integra los medios diferidos M03/M06/M10.

Decisiones del propietario durante este encargo:

- Fotografías anexas a eventos de confirmación o incidencia de M10, incluidos históricos; no sustituir ni borrar las anteriores.
- La segunda elección automática inicial se retiró. Tras volver a explicar la pregunta, el propietario confirmó abrir la recepción pública real de solicitudes, con consentimiento y antispam; SMTP permanece pendiente.
- Se autorizó un administrador sintético temporal exclusivamente en la base aislada para las regresiones existentes. No se autorizó crear administradores en desarrollo ni ampliar privilegios de MySQL.

No hay requisito aprobado de captura de firmas: no se incorpora ni se le atribuye validez jurídica. Tampoco se implementan devoluciones, pagos, contabilidad, SMTP, GPS o M12.

## Diagnóstico real

El inicio confirmó Windows/Docker, PHP 8.2.35, MySQL 8.4.10 en mysql-local, desarrollo ferreteria_ayala, 61 migraciones exactas y 72 tablas. Sucursales, productos, clientes, cotizaciones, entregas y facturas estaban vacíos. La base ferreteria_ayala_test estaba vacía. Git main estaba limpio; M10 sí constaba en el commit f304756, a diferencia del estado reportado. La suite previa ejecutó 1168 comprobaciones PASS.

El ejecutor de comandos sin elevación falló al crear procesos en Windows. Se continuó con las lecturas mediante Node y después con los comandos locales aprobados. No se mostraron secretos ni se modificó .env.

## Arquitectura y persistencia

CmsRules valida tipos, identificadores, texto UTF-8 acotado, destinos locales, fechas, estados y campos específicos de cada formulario. PdoCmsRepository usa SQL preparado, listas permitidas de tablas, límites y orden estable. CmsManagementService comprueba permisos vigentes y coordina transacciones, bloqueo, revisión optimista, operaciones idempotentes y auditoría. Los controladores coordinan peticiones y arrays explícitos; las vistas escapan contenido con e().

Las revisiones contienen instantáneas inmutables y actor. Guardar una modificación requiere lock_version vigente. Las claves de operación conservan hash del pedido y actor: el reenvío equivalente recupera el resultado y una clave reutilizada con datos distintos se rechaza. La creación se serializa con el bloqueo del actor y los índices únicos protegen la persistencia. No hay reintentos automáticos de deadlocks/timeouts.

### Migraciones aditivas

Se conservan intactas las 61 migraciones previas. Se incorporan siete, para 68 acumuladas y 21 tablas nuevas:

| Migración | Tablas / efecto |
| --- | --- |
| 20261103110000_create_media | media, media_variants |
| 20261103110100_create_cms_content | banners, promotions, events, reviews, pages, site_settings, navigation_items, page_revisions, cms_operations |
| 20261103110200_create_cms_legal | legal_documents, legal_document_versions, quote_term_templates |
| 20261103110300_create_media_links | product_images, supplier_invoice_files, delivery_evidence |
| 20261103110400_create_public_requests | public_quote_requests, public_quote_request_items, legal_acceptances, public_request_limits |
| 20261103110500_register_cms_permissions | Permisos M11 y asignación al GLOBAL_ADMIN existente |
| 20261103110600_create_page_admin_role | Perfil PAGE_ADMIN sin asignaciones de usuarios |

InnoDB/utf8mb4, FKs RESTRICT, unicidad y CHECK explícitos. Las claves de contenido/medios son ASCII con comparación binaria. Las cantidades públicas conservan DECIMAL(18,6) y precisión de unidad M03. Los medios no se guardan como binarios SQL ni rutas suministradas por el usuario. La unicidad de imagen principal usa una columna generada nullable por producto.

Los rollbacks rechazan historia o permisos delegados. El preflight del lote contempla que PAGE_ADMIN se retira dentro de ese mismo lote; no admite asignaciones a usuarios ni ampliaciones del perfil. MySQL confirma DDL implícitamente: una migración fallida requiere inspección; no se promete rollback atómico de DDL.

### Permisos y perfil

Trece permisos nuevos: cms.configure, cms.view, cms.edit, cms.publish, cms.legal, cms.media, cms.terms, media.products, media.invoices, media.evidence.view, media.evidence.create, quote_requests.view y quote_requests.assign.

CMS, publicación global y medios del catálogo tienen alcance global. Condiciones, comprobantes, evidencias y solicitudes respetan sucursal. La preparación de una solicitud también exige quotes.create en esa sucursal. PAGE_ADMIN recibe únicamente cms.view/edit/publish/legal/media; no recibe configuración global, usuarios, cotizaciones, precios, inventario ni comprobantes privados. Su acceso inicial dirige al CMS. No se crean usuarios mediante migraciones.

## Publicación y contenido

Borrador, programado, publicado y archivado. Los tiempos comerciales se capturan en America/Hermosillo y se convierten a UTC. La consulta pública solo devuelve publicado/programado con inicio alcanzado y fin no alcanzado; la programación se evalúa al leer y no necesita un proceso Node ni cron. La sucursal asociada debe seguir activa. Promociones no escriben precios ni inventario.

Home usa el identificador home de site_settings para su bloque principal; otros bloques son institucionales. Los tipos tienen campos explícitos: enlace/texto de banner o promoción, fecha/lugar de evento, autor/calificación autorizada de testimonio, título/cuerpo/SEO de página y destino de navegación. Los destinos de navegación son locales.

La publicación se configura desde /admin/cms/settings con cms.configure. Reutiliza Module y PdoModuleRepository de M01: M11 global, M08 global y habilitación M08 de cada sucursal se controlan explícitamente y por separado. Desactivar conserva contenido, solicitudes e historia. No se habilita ninguna sucursal ajena como efecto de cambiar otra. Las migraciones no registran ni activan módulos automáticamente.

Sin M11 habilitado, la Home conserva DemoHomeData y su identificación demostrativa. El CMS público muestra únicamente datos publicados, no semillas. La recepción exige M11 global, M08 global, sucursal activa/habilitada y aviso de privacidad vigente. Los datos comerciales y textos legales reales siguen siendo insumos del negocio.

SEO incluye título/descripción, Open Graph básico, canonical desde APP_URL y sitemap.xml condicionado a APP_INDEXING y URL válida. El formulario de solicitud lleva noindex. El mapa local representa las coordenadas M01 y no transmite datos a proveedores externos; el proveedor de cartografía comercial sigue sin definirse.

## Medios e integraciones

JPEG, PNG y WebP reales, máximo 10 MiB, dimensiones de 64 a 8192 px por lado, máximo 12 megapíxeles y proporción entre 1:8 y 8:1. Se comprueban MIME con fileinfo, cabecera/dimensiones y decodificación GD. También se rechaza el trabajo que excedería la memoria disponible. No se conservan originales ni metadatos EXIF. Cada imagen se vuelve a codificar como WebP calidad 82, con ancho principal máximo 1920 y variantes 320/640/1280 cuando corresponda; nunca se amplía una imagen pequeña. Alt obligatorio y hashes SHA-256 de los archivos almacenados.

El Dockerfile añade GD JPEG/PNG/WebP y configura cargas 10M/post 12M. cPanel debe disponer de PHP 8.2+, pdo_mysql, fileinfo, mbstring y GD con WebP. No se instala ni ejecuta Node en producción.

Los archivos están fuera de public/, en storage/media. /media solo sirve medios públicos con referencia publicada o imágenes de productos activos, mediante token aleatorio de 192 bits. Las variantes usan la misma comprobación. Los archivos privados requieren sesión, permiso y sucursal en /admin/media/file; no se sirven por una URL pública. Todas las lecturas verifican tamaño/hash y usan no-store.

Las cargas incompletas se rechazan; tras un fallo transaccional se retiran únicamente los archivos creados por esa operación. Un reenvío idéntico no duplica filas ni archivos. Una caída abrupta puede dejar archivos huérfanos privados sin referencia, nunca publicados: su conciliación operacional pertenece a M14, sin limpieza automática en este incremento.

- M03: galería, imagen principal única, orden con rechazo de orden obsoleto y ficha técnica en formato de imagen.
- M06: comprobantes privados relacionados con la factura y su sucursal, sin alterar recepción, costos ni inventario.
- M10: fotografías append-only relacionadas con eventos confirm/incident históricos, receptor/observaciones, actor y fecha. No se cambia estado, movimiento ni cantidades.

No se admiten PDF, SVG, HTML, ejecutables ni documentos Office como subida. El alcance de archivos se implementa con imágenes raster validadas; las fichas/comprobantes PDF requieren una canalización de validación adicional antes de ampliar los formatos. No se ofrece una comprobación superficial de PDF como garantía de seguridad. Las imágenes nuevas de QA son generadas por GD exclusivamente en la base aislada; no se incorporan imágenes comerciales ni licencias externas nuevas.

## Avisos, solicitudes y M08

Cada guardado legal crea una versión nueva. Las publicadas conservan título, cuerpo, responsable, fecha y SHA-256. Un borrador no sustituye el aviso vigente. Las versiones publicadas siguen consultables y no se borran ni sobrescriben. Las condiciones por sucursal se copian explícitamente al borrador; los documentos M08 previos conservan sus instantáneas.

El esquema M08 exige cliente interno y creador autenticado. No se debilitan sus FKs ni se crean clientes/vendedores ficticios: la entrada pública usa tablas de solicitud vinculadas a sucursal/variantes/unidades y consentimiento. Se admite sobrepasar existencias en la solicitud; no hay apartado, precio final ni envío SMTP.

CSRF de M02, formulario con antigüedad mínima de dos segundos y máxima de dos horas, honeypot y contador persistente de cinco intentos por quince minutos. La huella HMAC de IP usa una clave aleatoria privada de runtime bajo storage/private, no conserva IP en claro y no se expone en logs. Los errores de validación también consumen intento.

Nombre, teléfono, correo, preferencia, sucursal y cantidades se validan en servidor. Solo variantes, productos y unidades activos; sin partidas libres ni adjuntos del visitante. La versión de privacidad se vuelve a validar bajo bloqueo al recibir. Si cambió, exige leer/aceptar la nueva versión. Solicitud, partidas, consentimiento y auditoría se confirman juntos. Reintentar devuelve el mismo folio aleatorio SOL-.

Ventas toma la solicitud atómicamente. Otro vendedor no puede tomarla simultáneamente. Para preparar el documento selecciona un cliente interno real y las condiciones. Se reutiliza QuoteManagementService y el motor M04. El enlace a la solicitud se agrega dentro del callback transaccional del guardado M08: un fallo también revierte el borrador. La clave determinista por solicitud evita documentos duplicados.

## Rutas principales

- GET /admin/cms; POST /admin/cms/save.
- GET/POST /admin/cms/legal, /admin/cms/terms y /admin/cms/settings.
- GET /admin/media; POST /admin/media/upload y /admin/media/arrange; GET /admin/media/file.
- GET /admin/quote-requests; POST /admin/quote-requests/action.
- GET /pagina, /legal, /media y /sitemap.xml.
- GET/POST /solicitar-cotizacion.

Las escrituras administrativas exigen sesión, CSRF y autorización; los servicios vuelven a comprobar el alcance. Las vistas reciben arrays explícitos, conservan escape y componentes/estilos administrativos. La carga por cámara usa el input estándar del navegador.

## Pruebas y evidencias

Ejecutores:

    docker exec ferreteria-ayala-php-1 php tests/run.php
    docker exec ferreteria-ayala-php-1 php tests/mysql-m11-integration.php
    node tests/m11-http-integration.mjs
    docker compose --env-file .env.example run --rm assets npm run build

Se reutiliza la identidad TEST_DB_*_M10, limitada a ferreteria_ayala_test, por tener el alcance/privilegios ya adecuados. No hay aprovisionamiento, GRANT ni nueva cuenta MySQL. Se mantiene la exclusión asesora de las suites M10 para impedir pruebas/restauraciones simultáneas sobre el mismo esquema. Preflight confirma identidad, motor, privilegios exactos, inventario vacío y separación de desarrollo.

La suite SQL comprueba publicación, revisiones, rol de página, módulos M01, optimización, integridad, idempotencia, galería, privacidad, consentimiento, conversión M08, evidencia histórica M10, rollback de auditoría, FKs, rate limiting y carreras reales. Los procesos independientes se sincronizan después de conectar: edición con un ganador, asignación con un vendedor y carga duplicada con un solo medio. Otra conexión provoca timeout real y se comprueba que no deja revisión parcial.

Chrome usa sesiones global, editor, visitante y sucursal ajena; recepción/reintento, CSRF, preparación M08, publicación, archivos privados, MIME falso, permisos, consola, red y responsive. Capturas y evidencias con hashes del código se guardan en tests/artifacts, ignorado por Git. Los hashes deben permanecer iguales desde el inicio hasta el final de cada suite; se rechaza la evidencia si hubo cambios de fuente. Al activar, SQL exige vigencia de dominio, servicios, persistencia, esquema, runtime y ejecutores SQL; HTTP y verificación local exigen todas las fuentes y assets actuales. Un ajuste exclusivo de selectores Chrome no invalida una prueba SQL que no los utiliza.

Resultados ejecutados sobre las fuentes actuales:

| Comprobación | Resultado |
| --- | --- |
| Dominio y regresión local M00–M11 | PASS: 1235 comprobaciones |
| Sintaxis PHP | PASS: 375 archivos |
| Sintaxis JavaScript/MJS | PASS: 26 archivos |
| Build Tailwind/esbuild local | PASS |
| M11 SQL MySQL 8.4.10 | PASS: 71 aserciones, 68 migraciones, carreras independientes y limpieza |
| M11 HTTP y Chrome | PASS: 80 aserciones, sesiones autenticadas, legales, plantillas M08, galería y controles privados |
| Responsive | PASS: Home, formulario, bandeja, configuración, evidencias y editor a 360/768/1024/1440 px |
| Inspección visual | Revisadas capturas móviles y escritorio; corregidos contraste del botón público y corte de folios largos |
| Regresiones SQL/HTTP M08–M10 | PASS: las seis suites ejecutadas secuencialmente y sus esquemas aislados limpios |

No hubo errores inesperados de consola ni solicitudes fallidas en los recorridos finales M11. La suite confirmó exactamente una solicitud/consentimiento tras reenvío, conversión enlazada al borrador, copia explícita de condiciones, galería/ficha con una principal, evidencia histórica y comprobante privado. En el fixture aislado, el saldo físico permaneció en 98 y apartado en cero después de la entrega; la entrada pública y los archivos no alteraron inventario. Ambas suites M11 terminaron con el esquema y sus archivos temporales vacíos. No se declara cPanel/Apache real, dispositivos físicos o lectores de pantalla probados.

## Respaldo, restauración y activación

El primer respaldo de desarrollo m11-pre-migration-20261104-024137.json se verificó por JSON/SHA-256 y restauración completa de 72 tablas en la base aislada. SHA-256: 1f01f535d85b36d0b6e679aa43d59a88118b925d3bee084ff6abb96cd5a8e9f0. La base aislada quedó vacía después de comparar las filas. El nombre usa UTC; la fecha local corresponde al 3 de noviembre.

El activador exige SQL y Chrome recientes con hashes vigentes, generar un nuevo respaldo, repetir su restauración, verificar los 61 historiales anteriores y aplicar solo las siete adiciones. Debe comparar todos los registros anteriores, permitir únicamente los permisos/rol/historial técnicos previstos y confirmar cero fixtures en las 21 tablas nuevas. No se activa con una prueba fallida o pendiente.

Estado final de activación: **PASS** en MySQL 8.4.10. Se aplicaron únicamente las siete migraciones M11: 68 acumuladas y 93 tablas. Las 72 tablas previas conservaron sus registros; las 21 nuevas quedaron vacías. Se verificaron 53 FKs nuevas restrictivas y 45 índices únicos nuevos, incluidos PRIMARY. GLOBAL_ADMIN recibió los trece permisos nuevos; PAGE_ADMIN tiene cinco permisos y ninguna asignación de usuario. Desarrollo conserva un solo usuario, el anterior; no se creó ninguna cuenta administrativa ni comercial.

Respaldo final privado: storage/backups/m11-pre-migration-20261104-173307.json. SHA-256: 4f48f47ab00871d0dd13db273b8fa0a54aa9062566e7ced73a5949a8298fd198. JSON/filas comparados y restauración completa verificada en la base aislada antes de migrar; cero objetos aislados después. Evidencias: tests/artifacts/m11-activation.json y m11-final-state.json.

Verificación HTTP final local: Home y login 200; CMS, legales, plantillas, publicación, biblioteca y solicitudes administrativas sin sesión 303; /.env 404. /solicitar-cotizacion responde 404 porque M11 público no está habilitado. La Home sigue siendo la demo identificada. La recepción real queda preparada y probada; para abrirla hay que cargar sucursales/contenido/aviso aprobado y habilitar explícitamente M11, M08 global y su sucursal desde /admin/cms/settings. No se publica contenido ficticio. Los recorridos autenticados se comprobaron en la base aislada; no se utilizó la cuenta personal del propietario en desarrollo.

## Incidencias de QA preservadas y recuperadas

Las ejecuciones fallidas no cuentan como PASS. Cada recuperación inspecciona inventario/historial, compara el manifiesto de la ejecución, guarda un respaldo privado verificable y retira únicamente el grafo sintético mediante rollbacks oficiales. Nunca se usa TRUNCATE, DROP DATABASE ni se desactivan FKs.

1. Primer SQL: llamadas globales a hasPermission sin el tercer argumento exigido. Corregidas con alcance null explícito. La primera recuperación detectó que el preflight del lote debía contemplar el perfil PAGE_ADMIN que se retiraba en el mismo lote; se corrigió antes de continuar.
2. Segundo SQL: el orden esperado de galería no estaba capturado en el closure transaccional. Se corrigió. La recuperación detectó una FK que impedía retirar media antes de su contenido publicado; el orden de limpieza se corrigió a referencias primero, medios después.

3. Se corrigió una referencia incorrecta a la columna de habilitación de branch_modules y se acortó la clave idempotente de conversión al límite de 64 caracteres de M08.
4. El servidor no permite crear triggers con su configuración binlog y la identidad limitada. No se concedieron privilegios ni se alteraron variables globales. La prueba de rollback usa el contrato AuditRepository e induce una FK SQL inválida al registrar auditoría. También se ajustaron las aserciones al mapeo de errores central: OperationConflict para FK y PersistenceException tras timeout.

5. Chrome ampliado detectó un folio largo sin corte a 360 px. Se corrigió el elemento y se añadieron espera de fuentes/layout y captura de diagnóstico sin datos sensibles. Se repitió y aprobó el recorrido completo. La primera reejecución tras la pausa quedó bloqueada por el límite de uso del revisor automático; el comando no se ejecutó. La ejecución posterior del 4 de noviembre fue aprobada y pudo continuar.

Respaldos de recuperaciones verificados: m11-failed-isolated-20261104-025241.json (43534f7c3f7a10f5de6eed41b26549d550bcadef1785f520e7a1146bf0d9293f) y m11-failed-isolated-20261104-025935.json (b2b20a7fa4125caa90dad29acbe82e09d243e74cb1a7bffb08becdc0766d8e25). Las copias permanecen privadas en storage/backups. Las recuperaciones posteriores incluyen bytes/base64 y SHA-256 de los archivos sintéticos dentro del respaldo privado, no solo metadatos SQL. La recuperación HTTP del 4 de noviembre quedó respaldada en m11-failed-isolated-20261104-171349.json, SHA-256 f071339a470256708e38f88dbc6712734fd2cfd678f4d97e172a5d98df631946.

## Entrega y continuidad

Todo el trabajo queda sin staging, commit ni push. No se cambió de rama ni se sobrescribieron modificaciones previas; el punto de partida estaba limpio. El inventario de archivos se detalla a continuación. Las fuentes y assets de la entrega corresponden a las evidencias de prueba; documentación y respaldos no se incorporan a sus hashes de ejecución.

Siguiente módulo oficial: M12 · Notificaciones internas. No se inicia.


## Inventario final de Git

Rama main, HEAD f304756 (el mismo commit M10 inicial). 47 archivos nuevos y 36 modificados; todos disponibles para revisión, sin staging/commit/push. git diff --check PASS. .env sigue fuera de Git y no fue sobrescrito. Los directorios privados son escribibles por PHP y conservan la ACL restringida del respaldo.

Archivos creados:

- `app/Application/Services/CmsManagementService.php`
- `app/Application/Services/MediaManagementService.php`
- `app/Application/Services/PublicQuoteRequestService.php`
- `app/Controllers/Admin/CmsController.php`
- `app/Controllers/Admin/MediaController.php`
- `app/Controllers/Admin/QuoteRequestsController.php`
- `app/Controllers/Public/CmsController.php`
- `app/Domain/Cms/CmsRules.php`
- `app/Infrastructure/Media/ImageStorage.php`
- `app/Infrastructure/Media/RequestFingerprint.php`
- `app/Infrastructure/Persistence/PdoCmsRepository.php`
- `app/Views/admin/cms/index.php`
- `app/Views/admin/cms/legal.php`
- `app/Views/admin/cms/media.php`
- `app/Views/admin/cms/requests.php`
- `app/Views/admin/cms/settings.php`
- `app/Views/admin/cms/terms.php`
- `app/Views/components/public/cms-cards.php`
- `app/Views/layouts/cms.php`
- `app/Views/public/cms/home.php`
- `app/Views/public/cms/legal.php`
- `app/Views/public/cms/page.php`
- `app/Views/public/cms/request.php`
- `bin/activate-m11-development.php`
- `bin/backup-m11-development.php`
- `bin/recover-m11-isolated.php`
- `bin/restore-m11-backup.php`
- `database/migrations/20261103110000_create_media.php`
- `database/migrations/20261103110100_create_cms_content.php`
- `database/migrations/20261103110200_create_cms_legal.php`
- `database/migrations/20261103110300_create_media_links.php`
- `database/migrations/20261103110400_create_public_requests.php`
- `database/migrations/20261103110500_register_cms_permissions.php`
- `database/migrations/20261103110600_create_page_admin_role.php`
- `docker/uploads.ini`
- `docs/integration-m11.md`
- `resources/js/modules/cms.js`
- `scripts/verify-m11.mjs`
- `tests/m11-admin-browser.mjs`
- `tests/m11-cms.php`
- `tests/m11-concurrency.php`
- `tests/m11-http-integration.mjs`
- `tests/m11-lint.php`
- `tests/m11-support.php`
- `tests/m11-worker.php`
- `tests/mysql-m11-http-integration.php`
- `tests/mysql-m11-integration.php`

Archivos modificados:

- `.gitignore`
- `README.md`
- `app/Application/Security/PermissionCatalog.php`
- `app/Controllers/Admin/AdminController.php`
- `app/Controllers/Admin/DeliveriesController.php`
- `app/Controllers/Admin/LoginController.php`
- `app/Controllers/Admin/ProductsController.php`
- `app/Controllers/Admin/PurchasesController.php`
- `app/Controllers/Admin/QuotesController.php`
- `app/Controllers/Public/HomeController.php`
- `app/Core/AppKernel.php`
- `app/Views/admin/catalog/product-detail.php`
- `app/Views/admin/deliveries/detail.php`
- `app/Views/admin/purchasing/invoice-detail.php`
- `app/Views/admin/quotes/form.php`
- `app/Views/components/admin/sidebar.php`
- `config/routes.php`
- `docker/Dockerfile`
- `docs/architecture.md`
- `docs/assets.md`
- `docs/data-model.md`
- `docs/deployment-cpanel.md`
- `docs/integration-m03.md`
- `docs/integration-m06.md`
- `docs/integration-m08.md`
- `docs/integration-m10.md`
- `docs/modules.md`
- `docs/qa.md`
- `docs/requirements.md`
- `docs/roadmap.md`
- `package.json`
- `public/assets/css/app.css`
- `public/assets/js/app.js`
- `resources/css/app.css`
- `resources/js/app.js`
- `tests/run.php`
