# Cierre integral de auditoría — Ferretería Ayala

Actualizado: 14 de noviembre de 2026. Documento vivo; el detalle por hallazgo está en `CONTROL_CIERRE_AUDITORIA_FERRETERIA_AYALA.md`.

## A. Resumen ejecutivo

El sistema conserva una base técnica amplia y la regresión local está estable en 1609 controles. La base principal tiene 75 migraciones y la estructura AY-04 está aplicada, comprobado sin escrituras. En esta etapa se corrigieron AY-15, AY-18, AY-19, AY-21, AY-22, AY-24 y AY-25. Solo AY-24 cuenta actualmente con evidencia suficiente para clasificarse cerrado; los defectos con persistencia requieren SQL aislado, AY-22 requiere un recorrido HTTP inducido y AY-25 validación en cPanel real.

La consolidación no está terminada. La base aislada M05 no conecta y no existe configuración completa para el flujo central M10. Además, AY-01 depende de datos reales que no pueden inventarse.

## B. Correcciones completadas

- AY-15 obtiene la unidad base directamente por identificador y ya no depende del listado de 500 variantes.
- AY-18 reemplaza sumas de magnitudes incompatibles por conteos no aditivos de saldos.
- AY-19 centraliza el máximo de 10,000 filas y elimina la discrepancia PDF/XLSX.
- AY-21 ejecuta la auditoría de horarios dentro de la transacción comercial.
- AY-22 entrega una referencia correlacionable y registra diagnóstico privado con redacción de secretos y correo.
- AY-24 calcula el contador con el número real de campañas.
- AY-25 alinea AGENTS, README y despliegue cPanel con la carga explícita de `.env`, el CMS y el estado real de migraciones/módulos.

## C. Integración de módulos

La cobertura M08–M10 de AY-04 permanece implementada. La migración 75 real contiene las tablas, columna y relaciones esperadas. No se pudo ejecutar el flujo SQL central porque no hay una identidad aislada M10 completa y autorizada. No se utilizó la base principal para suplir esa carencia.

## D. Seguridad

Se mantuvieron permisos, CSRF, alcance por sucursal e idempotencia existentes. Las consultas a la base principal se realizaron dentro de transacciones `READ ONLY` y no extrajeron secretos ni datos personales. AY-05 y AY-06 conservan sus correcciones previas, pero sus casos autenticados SQL siguen pendientes.

## E. Base de datos

- Principal: 75 migraciones, 106 tablas, migración 75 registrada.
- AY-04: tres tablas de cobertura, columna de liberación decimal y once claves foráneas verificadas.
- Nuevas migraciones generadas en esta intervención: ninguna.
- Migraciones ejecutadas en la principal: ninguna.
- Base aislada M05: conexión rechazada antes de DDL o fixtures.

## F. Pruebas

- `php tests/run.php`: PASS, 1609 controles.
- `npm run build`: PASS.
- Sintaxis PHP de archivos cambiados: PASS.
- `git diff --check`: PASS.
- AY-19: 9,999 y 10,000 aceptados; 10,001 y 10,002 rechazados por la misma política antes de elegir formato.
- AY-24: total inicial correcto para 1, 2, 3 y 4 campañas.
- SQL aislado M05: no ejecutado; la conexión configurada fue rechazada.

## G. Pendientes que requieren al cliente

- Aviso de privacidad oficial aprobado y publicado.
- Registro/habilitación de módulos M08–M11 según operación real.
- Catálogo y variantes activos autorizados.
- Sucursales receptoras, horarios, servicios y ubicaciones oficiales.
- Credenciales funcionales de bases aisladas, nunca la identidad comercial.
- Formato XLSX de importación y reglas de actualización por SKU.
- Matriz de destinatarios para notificaciones internas.
- Evidencia de respaldos del hosting y destino aislado para restauración.
- Formato físico de etiquetas/códigos cuando se requiera impresión.

## H. Funcionalidades aplazadas

SMTP comercial y recuperación autónoma de contraseña permanecen aplazados conforme a la prioridad comunicada. El sistema registra entrega manual; no envía mensajes externos.

## I. Preparación para producción

No se acredita todavía. Faltan la regresión SQL/concurrencia central, restauración independiente, configuración operativa real y cierre de los defectos pendientes en importaciones/reportes/observabilidad/costos.

## J. Estado de la página web principal

No se realizó rediseño. La siguiente etapa deberá trabajar composición, imágenes, navegación, responsive y presentación comercial. Antes de esa etapa siguen faltando contenido oficial, catálogo publicable, privacidad y sucursales receptoras. El contador funcional de banners AY-24 ya no está fijado a tres.

## Dictamen provisional

**DICTAMEN 1: ¿ESTÁN RESUELTOS LOS HALLAZGOS TÉCNICOS DE LA AUDITORÍA? PARCIALMENTE.**

**DICTAMEN 2: ¿EL SISTEMA ADMINISTRATIVO ESTÁ PREPARADO PARA SU VALIDACIÓN FINAL CON EL CLIENTE? NO.**

La causa principal no es la regresión local, sino la falta de evidencia SQL aislada completa y la existencia de hallazgos funcionales aún pendientes.
