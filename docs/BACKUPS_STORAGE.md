# Almacenamiento local de DIGITALÍSIMO Backups

Desde 1.0.44, los ZIP se guardan en una carpeta privada fuera de la raíz pública del servidor y de WordPress. Al abrir la pantalla Backups, el plugin identifica la raíz web, crea la carpeta privada con permisos `0700` y traslada los archivos del antiguo `wp-content/plugins/digitalisimo-backups-storage`. Los ZIP históricos conservan su nombre y siguen disponibles en el historial. La migración compara SHA-256 antes de retirar una copia anterior y se detiene si encuentra archivos con el mismo nombre y distinto contenido.

Si el hosting no permite crear una carpeta junto a la raíz web, define en `wp-config.php` una ruta absoluta privada y escribible por PHP:

```php
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', '/ruta/privada/digitalisimo-backups' );
```

La ruta debe quedar fuera del directorio público, de `ABSPATH`, de `WP_CONTENT_DIR` y de `WP_PLUGIN_DIR`. No apuntes a `uploads`, `plugins` ni otra carpeta que el servidor pueda servir directamente. Si falta una ruta segura, el plugin muestra el motivo y no crea respaldos nuevos bajo una ruta pública. En WP-CLI o cron sin `DOCUMENT_ROOT`, abre primero la pantalla Backups para registrar la ruta o define la constante anterior.

Después de actualizar, comprueba en Backups que aparecen los archivos históricos y descarga uno mediante la acción autenticada. Si una migración no termina, los archivos originales permanecen en su ubicación anterior y el plugin muestra el error; corrige permisos o configura otra carpeta privada antes de reintentar.
