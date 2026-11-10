# DIGITALÍSIMO Backups

Objetivo, estado vigente, arquitectura y almacenamiento del plugin `digitalisimo-backups/`. El historial de cada
versión publicada está en [PLUGIN_ROADMAP.md](PLUGIN_ROADMAP.md) y en el historial de Git.

## Objetivo

Un respaldo es **la instalación completa de WordPress, tratada como una unidad indivisible**: archivos del núcleo,
`wp-content`, configuración y base de datos. En Multisite, un respaldo completo incluye la red entera, sus sitios y
tablas. Un archivo parcial no puede llamarse respaldo completo.

El objetivo sigue **abierto** hasta demostrar respaldo, verificación, transferencia, restauración y migración
completos, incluso a otro servidor o dominio, sin depender del hosting de origen. Cada cambio conserva los datos
existentes y las funciones utilizables mientras sustituye los caminos inseguros.

Condiciones de aceptación:

1. Un trabajo persistente avanza por `QUEUED`, `RUNNING`, `VERIFYING` y, si aplica, `TRANSFERRING`, hasta `SUCCESS`,
   `FAILED` o `CANCELLED`, y sólo llega a `SUCCESS` tras verificar todas las partes en todos los destinos exigidos.
2. El paquete lleva un manifiesto versionado, inventario íntegro y sumas criptográficas. Una copia incompleta,
   ilegible, truncada, alterada o sin piezas requeridas se rechaza antes de tocar el sitio de destino.
3. La restauración completa es portable entre servidor, ruta y dominio; conserva datos serializados, cambia URL con
   seguridad y se recupera de fallos. La prueba decisiva es una restauración extremo a extremo en otro entorno.
4. Automatización, incrementales con base válida, respaldo previo a actualizaciones, retención consciente de cadenas,
   varios destinos, reintentos, cifrado, SFTP real e instalaciones grandes y Multisite comparten un mismo motor.
5. Interfaz, REST y WP-CLI muestran el mismo estado real, con registros, alertas y comprobaciones de restaurabilidad.
   WordPress individual y una red funcionan sin otro plugin DIGITALÍSIMO activo.
6. Las condiciones se verifican con pruebas automatizadas, inyección de fallos y ensayos reales; ningún ZIP por sí solo
   prueba que el objetivo terminó.

Prioridad: integridad de datos → restaurabilidad → seguridad → portabilidad → fiabilidad → recuperación ante fallos →
automatización → compatibilidad → rendimiento → experiencia de usuario.

## Estado vigente

**Implementado y cubierto por pruebas** (`tests/backups-*.php`, en la suite de los dos workflows):

| Garantía | Desde | Prueba |
| --- | --- | --- |
| El respaldo manual y las automatizaciones nuevas crean sólo un ZIP integral (instalación o red completa); las políticas parciales antiguas quedan desactivadas y sus archivos se conservan. | 1.0.38 | `backups-policies.php` |
| Manifiesto v3 con tamaño y SHA-256 de SQL y archivos; el ZIP se vuelve a leer antes de registrarlo. Errores de tabla, archivo, cierre o CRC rechazan el respaldo y borran el ZIP fallido. | 1.0.37–1.0.38 | `backups-integrity.php`, `backups-manifest.php` |
| SQL por filas a un temporal e importación por sentencias; instantánea InnoDB o bloqueo de lectura con tablas no transaccionales; paginación por clave primaria con DB drop-ins; SQLite oficial con su transacción. | 1.0.41, 1.0.45 | `backups-streaming.php` |
| La restauración comprueba el SHA-256 registrado, rechaza alcances incompatibles, crea una copia previa bloqueada, escribe cada archivo mediante temporal verificado y, si falla, recupera desde la copia previa y retira los archivos nuevos que escribió. | 1.0.39–1.0.43 | `backups-manifest.php`, `backups-integrity.php` |
| SFTP por SSH2 con huella SHA-1 obligatoria de la clave del servidor, escritura temporal y comparación SHA-256 antes de publicar. | 1.0.36, 1.0.42 | `backups-sftp.php` |
| Google Drive y OneDrive vuelven a descargar la copia subida y comparan tamaño y SHA-256 antes de aceptarla. | 1.0.47 | `backups-cloud-verification.php` |
| Almacén local privado fuera de la raíz web (ver «Almacenamiento local»), con migración verificada del almacén anterior. | 1.0.44 | `backups-storage.php`, `backups-manifest.php` |
| Bloqueo exclusivo del almacén durante respaldos, cron, políticas y restauraciones. | 1.0.46 | `backups-operation-lock.php` |
| Avance del respaldo manual por base de datos, archivos y verificación; comprobación de alcance y archivos esenciales antes de iniciar; `wp-config.php` en la carpeta superior incluido. | 1.0.49 | `backups-manual-progress.php` |
| Actualizador propio por el índice de la Release, en la carpeta real del plugin y conservándola al actualizar. | 1.0.48–1.0.55 | `backups-updater.php`, `backups-updater-manifest.php`, `updater-real-folder.php` |
| **Trabajos persistentes (T1).** Todo respaldo (manual por AJAX o formulario, «Ejecutar ahora», automatizaciones y hooks de cron anteriores) es un trabajo en cola con estado en el almacén privado (`jobs/<uuid>/job.json`, escritura atómica, carpetas `0700` y archivos `0600`), no en la base de datos. Estados `queued → running → verifying → transferring → success \| failed \| cancelled`; sólo llega a `success` después de verificar el ZIP y, con destino remoto, la copia remota. Fases `preflight`, `database`, `pack`, `verify`, `publish`, `transfer` y `prune`, cada una un paso con punto de control: tras una muerte (proceso matado, tiempo agotado, reinicio) el trabajo repite sólo la fase interrumpida y nunca lo confirmado (una base de datos exportada no se exporta otra vez; un ZIP verificado no se reconstruye si falla la copia remota). Todo lo intermedio vive en `jobs/<uuid>/staging/` y el nombre final sólo nace de un `rename()` tras verificar; el SQL ya no pasa por `/tmp`. El latido escribe en el almacén, nunca dentro de la instantánea de la base de datos. Detector: un arrendamiento de este servidor con el cerrojo libre es un proceso muerto (el kernel libera `flock`); uno vivo nunca se toca aunque su latido sea viejo («sin señal»); el de otro servidor sólo se toma tras 30 min sin latido, con fencing. Reintentos con espera de 1, 5 y 15 min ±20 % y tope por fase (2 base de datos, 3 las demás, 5 transferencia); errores permanentes (requisitos, credenciales, huella SFTP) fallan al momento; caducidad a las 24 h. Cancelación desde la tarjeta «Trabajos» (AJAX y formulario sin JavaScript, con capability, nonce propio y contexto de sitio o red): revierte la instantánea, borra lo intermedio y, durante la transferencia, retira la sesión remota y conserva el ZIP local verificado; tras registrar el respaldo ya no se cancela. Disparadores: el navegador mientras la pantalla está abierta, un tick único de WP-Cron mientras quede trabajo y un watchdog horario (colgados, tick perdido tras restaurar la base de datos, restos de versiones anteriores de más de 24 h). La restauración se niega con un trabajo activo y su copia previa usa el mismo motor. La retención, global o de una política, nunca borra el ZIP de un trabajo sin terminar (el verificado que espera el reintento de su copia remota, o el recién creado con la misma fecha que otro); un tick que encuentra el cerrojo ocupado, el detector y la restauración dejan programado el siguiente tick; y el historial se lee de la base de datos (nunca de la caché de la petición) después de calcular el SHA-256 del ZIP, y cada cambio suyo (registro, estado de la copia remota, bloqueo, eliminación y retención) va con el cerrojo corto del almacén, así que un «Bloquear» pulsado durante un proceso largo (WP-CLI o varios trabajos seguidos) no se pierde, la retención no borra ese respaldo y registrar otra vez el mismo ZIP conserva su bloqueo. Una copia de Google Drive u OneDrive que sube completa pero no supera la verificación se retira del destino antes de reintentar, así que ningún intento deja allí una copia sin verificar con el nombre del respaldo. En una red, la cola es de toda la instalación: sólo el sitio principal (o la administración de red) la hace avanzar, pasa el detector o cancela, y en un subsitio la tarjeta «Trabajos» sólo informa. Un área de trabajo que sea un enlace simbólico nunca recibe el SQL ni el ZIP. | 1.0.59 | `backups-job-store.php`, `backups-job-states.php`, `backups-job-heartbeat.php`, `backups-job-resume.php`, `backups-job-cancel.php`, `backups-job-compat.php`, `backups-job-kill.php`, `backups-jobs-ui.mjs` |
| Validación de archivos no confiables antes de la copia previa y de cualquier consulta o escritura: rutas (`..`, absolutas, unidades, NUL, `\` sin firma, raíces desconocidas), enlaces y archivos especiales, entradas cifradas, métodos de compresión, duplicados, colisiones de mayúsculas, número de entradas, esquema estricto del manifiesto v3 y espacio en disco. La extracción nunca escribe más de lo declarado. UpdraftPlus pasa por la misma validación y el mismo escritor seguro. | 1.0.59 | `backups-untrusted-archive.php` |
| Guardián SQL: un solo divisor por fragmentos (idéntico al anterior) y una lista blanca de sentencias, en seco antes de la copia previa y por sentencia al importar. Acepta todo lo que escribió cualquier versión del exportador; en un archivo no confiable rechaza GRANT, LOAD DATA, INTO OUTFILE, funciones o SELECT en INSERT, DATA/INDEX DIRECTORY, CONNECTION, motores remotos (también escritos como texto o entre comillas invertidas: `ENGINE='CONNECT'`), tablas fuera del manifiesto y comentarios ejecutables ajenos. Lee los comentarios como MySQL: «--» seguido de un carácter de control (también DEL) es comentario, y un `/*!` de seis dígitos sólo se salta si también lo es leído con cinco (MySQL puede leer `/*!900000` como 9.0.0). UpdraftPlus usa el dialecto mysqldump, por fragmentos y sin cargar el SQL en memoria. | 1.0.59 | `backups-sql-guard.php` |
| Firma HMAC-SHA512/256 de los bytes exactos del manifiesto con la clave del sitio, verificada al crear y al restaurar. Tres niveles de confianza: firmado, registrado en este sitio (fila del historial con el mismo SHA-256) y sin firma, que pide `RESTAURAR SIN FIRMA` o se bloquea con «Restaurar sólo respaldos firmados». | 1.0.59 | `backups-manifest-auth.php` |
| Clave de los respaldos: constante `DIGITALISIMO_BACKUPS_KEY` o frase del administrador (PBKDF2-SHA256, 600 000 iteraciones) guardada envuelta con AUTH_KEY y SECURE_AUTH_KEY; ni la frase ni la clave vuelven al navegador ni entran en ningún respaldo. Llavero con claves retiradas que siguen sirviendo para restaurar; una restauración (también la de UpdraftPlus) nunca lo pierde ni debilita la protección, y los reconstruye en cuanto termina el SQL, antes de extraer los archivos. Pestaña «Seguridad». | 1.0.59 | `backups-keys.php` |
| Cifrado autenticado opcional (contenedor DGBK v1, `crypto_secretstream_xchacha20poly1305`, libsodium o el sodium_compat de WordPress): el ZIP verificado se cifra al final de la fase «verify» de su trabajo, dentro de su área de trabajo (`jobs/<uuid>/staging/`), se descifra completo para comprobarlo y sólo entonces se publica en el almacén con `rename()`; el ZIP en claro nunca sale del área de trabajo y se borra al publicar. Lo que deja en `.staging/` (el ZIP descifrado de una restauración) o en un `*.part` un proceso interrumpido lo borra la operación siguiente, cifre o no, al tomar el bloqueo, y el área de un trabajo fallido o cancelado se borra con él. Se restaura con la clave del sitio o escribiendo la frase en otro servidor. Falla cerrado: nunca sale en claro un respaldo que debía cifrarse. Sin la clave no se puede restaurar, y la interfaz lo dice antes de intentarlo: la columna «Seguridad» del historial marca «falta la clave» y la lista de Restaurar pide «escribe su frase o su clave», también para un `.dgbk` suelto o renombrado. | 1.0.59 | `backups-encryption.php` |
| Compatibilidad: los respaldos de todas las versiones con manifiesto v3 que conserva el repositorio (19, de 1.0.38 a 1.0.58) se siguen restaurando, con su fila como propios y sin ella como no confiables, y dejan el mismo estado que antes; los de red anteriores a v3 (13 versiones, de 1.0.1 a 1.0.37, sin SHA-256 en su fila), como no confiables; 1.0.58 restaura un respaldo nuevo firmado sin cifrar y su rotación no borra los `.dgbk`. | 1.0.59 | `backups-compat-legacy.php` |
| Restauración interrumpida: antes del SQL se escribe en el almacén un diario sin secretos (historial con la copia previa, seguridad y la parte pública del llavero: huellas y parámetros de cada frase). Si el proceso muere mientras corre el SQL, la operación siguiente (o la pantalla de Backups) lo aplica al tomar el bloqueo: el historial vuelve, la seguridad nunca queda más débil y cada clave vuelve sin su envoltura, así que el cifrado falla cerrado hasta volver a escribir la frase, que abre la misma clave. Sin diario no se restaura. | 1.0.59 | `backups-interrupted-restore.php` |
| **Seguridad dentro de los trabajos.** La especificación inmutable de cada trabajo fija al encolarse si se cifra y la huella de la clave (`security: {encrypt, key_id}`), nunca la clave ni la frase, y el nombre final (`.dgbk` si se cifra). Con el cifrado activo y sin clave no se encola nada. Cada fase vuelve a abrir esa clave por su huella: «pack» firma el manifiesto aunque entretanto se haya activado otra frase, «verify» comprueba la firma y, si el trabajo cifra, sella el DGBK en el área de trabajo, y «publish» lo publica y registra una sola vez aunque el proceso muera tras el `rename()`. Una muerte durante el sellado repite la verificación y el sellado sin volver a empaquetar. Si la clave fijada deja de abrirse (se quitó del llavero o cambiaron las claves de `wp-config.php`), un trabajo cifrado falla cerrado y de forma permanente sin publicar nada; uno sin cifrar vuelve a empaquetarse sin firma. La copia previa a una restauración usa el mismo motor y nunca se cifra: se firma si hay clave. | 1.0.59 | `backups-job-security.php` y, por el motor real, `backups-encryption.php`, `backups-keys.php`, `backups-manifest-auth.php`, `backups-interrupted-restore.php` y `backups-compat-legacy.php` |
| REST (`digitalisimo-backups/v1`) y WP-CLI (`wp digitalisimo-backups`) usan los casos de uso de la pantalla (`Digitalisimo_Backups_Service`) sobre el motor de «Trabajos» y «Seguridad»: estado, historial, crear, verificar, restaurar, automatizaciones y la cola (listar, consultar, avanzar y cancelar). La pantalla («Crear respaldo completo» por AJAX o formulario, «Ejecutar ahora» y «Restaurar») pasa por los mismos casos de uso. Crear y ejecutar una automatización encolan un trabajo y nunca lo duplican: REST responde 202 con el trabajo (409 con el que ya estaba pendiente) y `POST /jobs/tick` lo hace avanzar como el navegador; WP-CLI lo hace avanzar en su proceso hasta terminar (o sólo lo encola con `--async`), y `wp digitalisimo-backups jobs list|cancel|run` opera sobre la misma cola. Restaurar y verificar abren el paquete como la pantalla: aceptan `.dgbk`, piden `RESTAURAR` o `RESTAURAR SIN FIRMA` según su confianza («Restaurar sólo respaldos firmados» responde 403) y `verify` hace las mismas comprobaciones del paquete que la restauración. Un respaldo cifrado cuya clave no tiene el sitio responde, antes de tomar el bloqueo, `secret_required` con la huella corta y dónde va la frase: en REST sólo en el cuerpo (en la URL, 400); en WP-CLI por `--secret-file` o `--secret-stdin` (`--secret` es un error). Ninguna respuesta ni salida lleva la frase, la clave, su envoltura ni la huella completa, tampoco al rechazar una frase equivocada. REST pide la capability de la pantalla con la autenticación de WordPress (401 sin sesión, 403 sin permiso). En una red sólo responde el sitio principal (con varias redes, el de la red principal, porque el respaldo las abarca todas; el motor tampoco exporta desde una red secundaria), y crear, restaurar o ejecutar exigen el alcance de red explícito. Otra operación en curso o un trabajo activo impiden restaurar (409). Los resúmenes no llevan la ruta del almacén ni identificadores remotos (ruta SFTP o identificador de Google Drive y OneDrive); un aviso que impide respaldar nombra la carpeta que hay que corregir (ver «REST y WP-CLI»). Ensayado en Playground: WordPress 7.1.4, sitio individual y red (`tests/playground/backups-coverage.sh`). | 1.0.60 | `backups-service.php`, `backups-rest.php`, `backups-cli.php` |
| Rutas externas. `wp-config.php` en la carpeta superior o enlazado (archivos compartidos de un despliegue) se respalda; la restauración nunca lo escribe ni crea otro en `ABSPATH`. Fuera de `ABSPATH` o tras un enlace simbólico, wp-content, plugins, mu-plugins, medios (en una red, también los de cada sitio con `upload_path` propio, salvo con la constante `UPLOADS`, que hace a WordPress ignorarlo) o un directorio de temas se rechazan antes de exportar nada (al crear o ejecutar desde la pantalla, REST o WP-CLI, antes de encolar el trabajo), con la carpeta, quién la define y qué hacer; antes, plugins, medios y temas externos se omitían sin aviso. La restauración se detiene antes de tocar el sitio, un `ABSPATH` que es enlace de despliegue (`current → releases/N`) funciona y otro enlace dentro de `ABSPATH` hace fallar el trabajo al momento (error permanente, sin reintentos) con su destino, sin dejar ZIP. | 1.0.60 | `backups-layout.php`, `backups-external-config.php` |
| **Credenciales de los destinos cifradas que viajan en el respaldo (S4).** La contraseña de SFTP y el Client secret y el Refresh token de Google Drive y OneDrive viven en `digitalisimo_backups_credentials` (de la red en Multisite), cada uno cifrado con AEAD XChaCha20-Poly1305 (libsodium o el sodium_compat de WordPress) con una clave que sale de `AUTH_KEY` y `SECURE_AUTH_KEY` con su propia separación de dominio, y un dato adicional que lo ata a su campo. `digitalisimo_backups_settings` ya no guarda ninguno en claro: guardar «Servidores», la conexión OAuth (refresh token) y la migración de los sitios que vienen de 1.0.60 o anteriores (al cargar el escritorio, el cron o WP-CLI, sin ninguna consulta cuando no queda nada en claro, y después de cada restauración) pasan por el almacén, que comprueba cada valor antes de retirar el texto en claro; el navegador nunca recibe un valor. El almacén sí entra en `database.sql`: restaurar deja los destinos funcionando con las credenciales del respaldo, que mandan sobre las que tenía el servidor. En otro servidor (otras salts) se vuelven a cifrar con las de éste usando las del `wp-config.php` que va dentro del propio respaldo (sólo `define()` con literales de cadena, leídos con el tokenizador de PHP; el archivo nunca se ejecuta ni se incluye). Si no se pueden leer (`getenv()`, otro archivo) o cambiaron las salts de este servidor, el destino queda en «vuelve a escribir la contraseña / conéctalo de nuevo» en la pantalla, en `status` de REST y WP-CLI, en el aviso de la restauración y en el error permanente del trabajo con destino remoto; nunca se cae al texto en claro. Sin `AUTH_KEY` y `SECURE_AUTH_KEY` propias se guardan como hasta ahora, con aviso. Una restauración que muere entre el SQL y el nuevo cifrado lo termina con el diario. El dato adicional ata cada secreto también a su conexión (host, puerto, usuario y huella SSH; Client ID), así que una escritura directa que cambie el host no lo desvía. Ningún respaldo lleva un secreto en claro si hay salts propias (se migra antes del volcado y cada fila de ajustes sale sin ellos, también la de un subsitio); los ajustes van con autoload, así que la comprobación al cargar no cuesta consultas y detecta lo que 1.0.60 reescriba tras volver desde `rollback/`. En una red sólo el sitio principal lee y escribe el almacén; un sitio convertido en red lo adopta; «Olvidar las credenciales guardadas» retira las de un destino que ya no se usa; un almacén con datos que no son texto pide volver a escribir sin romper nada, y un `wp-config.php` de más de 64 KiB no se analiza. Cada secreto (del formulario o del retorno de OAuth) se guarda exactamente como se escribió, sin las barras de `$_POST` y sin saneado ni recorte: antes, comillas y barras invertidas ganaban barras, y `<…>`, `%xx` y los espacios al borde se perdían; sólo se rechazan, con un mensaje que no repite el valor y sin cambiar nada, NUL, los caracteres de control y más de 8192 bytes. | 1.0.61 | `backups-credentials.php`, `backups-credentials-surfaces.php`, `backups-credentials-nokeys.php`, `backups-credentials-exact.php`, `backups-credentials-hardening.php` y, en Playground, S9 de `tests/playground/backups-security.sh` |
| **Restauración por pasos (H1–H4 del ensayo real).** La restauración es un trabajo del motor de trabajos (cola, fases con punto de control, arrendamiento, latido y reanudación), como un respaldo: verificación, copia previa, mantenimiento, base de datos, archivos, cambio, direcciones y limpieza (ver «Restauración por pasos»). El SQL se importa por tramos a tablas temporales con el prefijo del sitio (`<prefijo>dgbkr_<marca>_<n>`) y las tablas pasan de viejas a nuevas de una vez (MySQL: un solo `RENAME TABLE`, atómico; el SQLite de WordPress: una transacción); hasta ese cambio el sitio se sirve de sus tablas, intactas. Los archivos que cambian se preparan en un área de trabajo y se mueven con `rename()` (cada carpeta de código —wp-admin, wp-includes, cada plugin y tema, mu-plugins— de una vez; este plugin y los `.htaccess`, al final). Tras una muerte (corte de la petición, recarga del servidor web, memoria, `kill -9`), la operación siguiente (pantalla, WP-Cron, `POST /jobs/tick` o WP-CLI) la reanuda desde el último punto de control; si falla después del cambio, encola la vuelta a la copia previa, que la restaura entera con el mismo motor. La pantalla y `status` avisan mientras no termina, y el modo de mantenimiento deja pasar sólo los ticks con el testigo de la restauración. Lo que no estaba en el respaldo no sobrevive dentro de las raíces que gestiona (núcleo, plugins, temas, mu-plugins y medios): queda en la copia previa y en el informe. El manifiesto se lee por partes a un índice en disco y cada fase avanza por tramos con el presupuesto de T2: un sitio de 40 008 entradas se verifica y se restaura con `memory_limit=64M` y 38 MB ya ocupados. Las tablas de otra aplicación que traiga el respaldo no se tocan; la red y las direcciones se fijan en las tablas temporales antes del cambio; las sentencias más grandes pasan por la reubicación antes de la copia previa; `.user.ini`, `php.ini` y `.htaccess` con rutas o el dominio del origen se corrigen, y un `auto_prepend_file` que aquí no existiría se desactiva. Tras una revisión adversaria: que el cambio de tablas se hizo lo decide la base de datos (no el error del `RENAME`) y nunca se borran las tablas de antes de una restauración sin confirmar; una vuelta a la copia previa que falla deja el sitio en mantenimiento con «Volver a la copia previa» (pantalla) y `wp digitalisimo-backups jobs rollback`; el mantenimiento deja pasar `wp-cron.php` y el tick con WordPress mínimo (sólo este plugin), WP-Cron sólo corre lo suyo y las visitas despiertan la restauración; las tablas y todo el código cambian en un paso y este plugin, al final; una carpeta que no llega a su sitio vuelve; lo que carga un `.user.ini` se queda; lo creado después de empezar se aparta en el almacén; `cache/`, `node_modules/` y los ejecutables de la raíz también se retiran; sin SHA-256 se reescribe siempre. | @NEXT_BACKUPS@ | `backups-restore-steps.php`, `backups-restore-memory.php` y, en Playground, `tests/playground/backups-restore.sh` |
| **Destinos remotos: todos los de Backuply.** FTP y FTPS (explícito e implícito, pasivo o activo), SFTP (también con clave privada y huella SHA-256), WebDAV, Google Drive, OneDrive, Dropbox (PKCE y sesiones de subida), Amazon S3, S3 compatibles (DigitalOcean Spaces, Wasabi, Cloudflare R2, Backblaze B2, Akamai/Linode, Vultr y otros como MinIO) y pCloud, sobre un contrato común (`put`/`get`/`size`/`delete`/`list`/`verify`/`test`): subida a un nombre temporal, verificación (descarga y SHA-256, o el `content_hash` de Dropbox y el `checksumfile` de pCloud) y publicación sólo después; TLS verificado (CA propia admitida), S3 sólo con SigV4 y carga firmada, sin cabeceras ni URL firmadas en ningún mensaje. Credenciales de todos por el almacén cifrado de S4, atadas a todo lo que decide adónde van los bytes (servidor, bucket, estilo, endpoint, carpeta, TLS); recibos del historial confinados a la carpeta de su conexión; nada al servicio de metadatos de la nube; varias conexiones con nombre del mismo tipo; pantalla con campos guiados y «Probar conexión»; reanudación de S3 multipart, Dropbox y FTP en el área del trabajo y abandono (multipart abortado, temporal retirado) al fallar o cancelar, fuera del cerrojo del registro de trabajos; «Traer al almacén» y descarga verificadas. Ver «Destinos remotos». | 1.0.62 | `backups-destinations.php`, `backups-destinations-protocols.php`, `backups-destinations-screen.php`, `backups-destinations-live.php`, `backups-sftp.php`, `backups-cloud-verification.php` |
| **Restaurar en otro dominio, otra ruta y otro prefijo (E1).** Un respaldo de otra dirección, de otra carpeta del servidor o con otro prefijo de tablas ya no se rechaza ni deja el sitio apuntando al origen: por defecto el sitio conserva **sus** direcciones (las de este servidor) y las del respaldo se reescriben al restaurar; «Conservar las direcciones del respaldo» es una opción explícita (pantalla, `addresses=backup` en REST, `--addresses=backup` en WP-CLI). El motor, `Digitalisimo_Backups_Relocate`, cambia URL y rutas del servidor dentro de PHP serializado sin llamar nunca a `unserialize()` (recorre el formato y recalcula cada longitud en bytes, también anidado y con multibyte; ningún objeto del respaldo se instancia), JSON con barras escapadas (el `_elementor_data` de Elements), URL codificadas, `http` y `https`, `//dominio`, con y sin `www`; un dominio sólo coincide completo (`origen.com` no toca `origen.com.mx`, `miorigen.com` ni un correo). Se aplica fila por fila durante la importación, con memoria constante, sobre los literales que comprobó el guardián SQL. Otro prefijo renombra las tablas (también en `REFERENCES`) y las claves que lo llevan (`{prefijo}user_roles`, `{prefijo}capabilities`…), y los permisos de otra instalación que traiga el respaldo no se aplican; las tablas sin el prefijo del origen no se restauran y las de este servidor no se tocan. En una red (subdominios o subdirectorios) cambian `site`, `sitemeta`, `blogs` y las direcciones de cada sitio, durante la importación (cortar el proceso después del SQL deja la red accesible); una red de otro tipo o un `wp-config.php` que declara otro dominio se rechazan antes de tocar nada diciendo qué cambiar. Después se vacían transitorios, reglas de reescritura, el CSS generado de Elements/Elementor, las cachés de página y la caché de objetos, se pide a LiteSpeed que purgue la suya, y al extraer `.htaccess` y `.user.ini` toman las rutas de este servidor (un `auto_prepend_file` que aquí no existe queda comentado); una comprobación final cuenta lo que quedó del origen (0 salvo `guid` y lo declarado). La pantalla dice «Origen → este sitio» antes de confirmar y guarda el informe por tabla. Ver «Restaurar en otro dominio». | 1.0.63 | `backups-relocate.php`, `backups-relocate-interrupted.php` y, en Playground, `tests/playground/backups-relocate.sh` |
| **Extraer un sitio de la red como WordPress individual.** En la administración de la red (sólo un superadministrador, alcance de red explícito), «Extraer como WordPress individual» encola un trabajo del mismo motor que los respaldos completos (cola, fases, reanudación y verificación) que deja un paquete con el alcance explícito `extracted-site` en el manifiesto v3, cifrado y firmado sólo con una frase propia de esa extracción (nunca con la clave de la red; obligatoria si la red cifra sus respaldos). Lleva las tablas del sitio con su prefijo de la red (`wp_N_`) y `wp_N_users`/`wp_N_usermeta` sólo con sus usuarios: los miembros con todos sus datos y el hash de su contraseña; los superadministradores que no son miembros, ninguno por defecto (`managing`: sólo quien tiene el correo de administración del sitio; escribir o comentar no cuenta); los usuarios bloqueados en la red (`spam`/`deleted`) nunca como miembros; los autores sin cuenta, sólo con su nombre público. Sus roles (`wp_N_user_roles`), sus medios (`uploads/sites/N/` → `uploads/`), los plugins activos en el sitio y en la red, su tema y padre, mu-plugins (con aviso), idiomas y el núcleo por lista blanca: ningún archivo de configuración de la raíz ni drop-ins. Los ajustes de red de los plugins DIGITALÍSIMO activos en el sitio se materializan por lista blanca (las claves y cuentas de la red, sólo si se pide); el paquete sólo cuenta lo que no viaja y nombra los `sitemeta` de otros plugins, mu-plugins y drop-ins. Se restaura sólo en un WordPress individual, con la copia previa, la recuperación y el motor de reubicación (E1), que pasa el prefijo, las URL (también las de los medios en la red y `/files/`) y las rutas a las del destino; la configuración de Backups del destino, con sus credenciales, se conserva. El alcance `site` de la vía antigua `create_site()` sigue rechazado. REST `POST /sites/<id>/extract` y `wp digitalisimo-backups extract <id> --network`. | 1.0.63 | `backups-site-extract.php` y, en Playground, `tests/playground/backups-site-extract.sh` |
| **Un respaldo copiado a un destino remoto sigue restaurable desde su ZIP local (B1, defecto).** La fase «transfer» cambiaba el `destination` de la fila al proveedor: el respaldo desaparecía de «Restaurar» (y tampoco salía como «sin registro»), REST y WP-CLI lo daban por no restaurable aunque su ZIP siguiera en el almacén, «Descargar» iba al remoto y «Eliminar» borraba sólo la copia remota y dejaba el ZIP local huérfano. Ahora la fila es la del ZIP local (`destination` = `local`) y la copia remota va en `transfer_destination`, `transfer` y `remote`; las filas de 1.0.59 a 1.0.61 (proveedor en `destination`) y las anteriores (sin `transfer`) se leen igual. Mientras el ZIP local exista se restaura desde él (con la confianza de un respaldo registrado aquí), en la pantalla, en REST y en WP-CLI (`restorable`, y `copies` con la copia local y la remota y su estado); «Descargar» sirve el ZIP local si existe; «Eliminar» retira la copia remota verificada, el ZIP local y la fila, y si el destino no confirma no cambia nada. `copies` sólo lista las copias que existen (la local y la remota verificada o anterior sin identificador): una copia pendiente, fallida o cancelada va sólo en `transfer`, y `destination` de REST nombra el destino remoto sólo si esa copia existe. Una fila sin ZIP local y sin copia remota que exista (la copia falló y la retención de su automatización borró el ZIP) se rotula «Sin copias: …» y conserva «Eliminar», que retira la fila sin pedir nada al destino. Volver a 1.0.61 o antes desde `rollback/` lee las filas nuevas como locales (restaurables). | 1.0.63 | `backups-remote-copy.php` |
| **Avisos por correo (B7).** Pestaña «Avisos»: fallo de un respaldo, de su copia remota o de una restauración (activo por defecto) y éxito (opcional), al correo configurado o al de administración del sitio (en una red, el de la red; los ajustes van en `sitemeta`, y un subsitio no los cambia). Sólo avisa el fallo definitivo: un reintento con espera nunca envía correo. Un trabajo terminado queda marcado en su estado del almacén (`notice: pending`) y el aviso se reclama con el cerrojo de los trabajos antes de enviarse fuera de él (al final de cada tick y en el watchdog), así que ni dos procesos ni un proceso que muere después de terminar lo repiten o lo pierden; la copia previa de una restauración nunca avisa. La restauración avisa al terminar, al rechazar el paquete o al fallar (no por un error de quien la pide), con los ajustes de antes del SQL; una restauración interrumpida avisa cuando la operación siguiente aplica el diario. Ningún correo lleva una credencial, una clave de `wp-config.php` ni una ruta del servidor (`Digitalisimo_Backups_Notify::clean()`: también rutas con espacios, de Windows, con esquema como `file:` o `phar://` y listas de `open_basedir`); el detalle de una sentencia SQL fallida, que cita valores de las filas, no va, y lo que va entre comillas se tapa. `status` de REST y WP-CLI dice si están activos y si el correo es propio (nunca la dirección). Botón «Guardar y enviar un correo de prueba». | 1.0.63 | `backups-notify.php`, `backups-maintenance.php` |
| **Subir un respaldo desde el navegador o por REST, por fragmentos (B3).** Tarjeta «Subir un respaldo» y `POST /uploads`: cada sesión vive en el almacén privado (`incoming/<uuid>/`, `0700`/`0600`), nunca en `uploads` ni en una ruta servida. Los fragmentos son de como mucho 8 MiB y por debajo de `upload_max_filesize` y `post_max_size` (136 KB con un límite de 200 KB; con los 20 MB del hosting del propietario, 8 MiB); cada uno se anexa sólo en el byte que la sesión espera (si no, 409 con lo recibido y el cliente sigue desde ahí), nunca pasa del tamaño declarado y una escritura a medias se deshace. El navegador reintenta con espera y, tras recargar, elegir el mismo archivo continúa donde se quedó. Al terminar, el archivo pasa dentro de su sesión toda la validación de archivos no confiables de «Seguridad» (la de `verify`, con el plan «Origen → este sitio» y el informe de `wp-config.php`) y sólo entonces entra en el almacén con `rename()`, como «sin registro» (pide `RESTAURAR SIN FIRMA` salvo firma válida de una clave de este sitio); sólo un archivo hostil, alterado o con otro SHA-256 declarado se descarta: si el motivo no es el archivo (falta sitio para descifrarlo, la frase es equivocada, el servidor falla) la subida se conserva con su motivo en el estado, para volver a cerrarla o descartarla. Un `.dgbk` reserva al empezar, además de su tamaño, el sitio para descifrarlo. El cierre es idempotente y reanudable: repetirlo devuelve el mismo resultado y nunca duplica el archivo; mientras otro proceso la comprueba responde 202 y el navegador consulta el estado (la comprobación sigue aunque el proxy corte la conexión); si el proceso muere comprobándola, se reanuda al cerrarla otra vez, y tras tres cortes responde 504 y pide `wp digitalisimo-backups upload finish <id>`, que no tiene ese límite. Nunca toma el nombre de un archivo ni de una fila del historial. Un `.dgbk` cifrado con una clave que este sitio no tiene se acepta tras comprobar su cabecera (se comprueba entero al restaurarlo con su frase, o al subirlo si se escribe; una frase equivocada no descarta la subida). Capability y nonce propio (pantalla) o la autenticación de REST; sólo la cuenta que la empezó continúa o cierra una sesión desde la pantalla o REST (`wp … upload finish` cierra cualquiera), pero cualquier cuenta con la capability las ve y las descarta («Subidas sin terminar» en la tarjeta, `GET /uploads`, `wp digitalisimo-backups upload list\|cancel`); en una red, desde el sitio principal; tres subidas sin terminar por cuenta y diez en el sitio; caducan a las 24 h sin actividad (watchdog). La retención global nunca cuenta ni borra un archivo «sin registro» (ver «Almacenamiento local»). | 1.0.63 | `backups-upload.php`, `backups-upload-ui.mjs`, `backups-upload-cli.php`, `backups-upload-retention.php` |
| **Hora fija en las automatizaciones.** Diaria, semanal y mensual pueden llevar una hora del sitio (`HH:MM`, en su zona horaria): la primera ejecución es la próxima vez que el reloj del sitio marca esa hora y, antes de cada ejecución, se realinea (WP-Cron suma el intervalo en UTC; el cambio de horario de verano o de zona horaria la desplazaría una hora). Si WP-Cron corre tarde (un sitio con poco tráfico), la siguiente sigue a la hora elegida del día que le toca: nunca se salta un día. Sin hora, como antes. Guardar una automatización ya no adelanta las demás (se conserva el evento de las que no cambiaron). Una automatización activa sin evento de WP-Cron (la «Completo mensual» que viene activa por defecto en un sitio donde nunca se guardó la pantalla, H8 del laboratorio) se programa al cargar WordPress, a su hora o a las 03:00 del sitio, sólo en el sitio principal de la red principal (en otra red de la misma instalación el respaldo completo fallaría cada vez) y sólo cuando el almacén privado se puede resolver (con WP-CLI o el cron del sistema sin `DOCUMENT_ROOT`, antes de abrir la pantalla o definir `DIGITALISIMO_BACKUPS_STORAGE_DIR`, espera en vez de fallar y avisar cada mes). `time` en `GET /automations` y en `wp … automation list`. | 1.0.63 | `backups-schedule-time.php`, `backups-default-schedule.php` |
| **Modo de mantenimiento durante la restauración (I8).** Desde el SQL hasta el final (también mientras se recupera la copia previa), `ABSPATH/.maintenance` hace que el sitio, o la red entera, responda «en mantenimiento». Se renueva cada minuto mientras avanzan el SQL, los archivos y, al revertir, la verificación de la copia previa (WordPress lo ignora a los 10 minutos), así que si el proceso muere el sitio vuelve solo; la operación siguiente lo retira con el diario y avisa por correo. Se retira siempre, también si la restauración falla. El `.maintenance` que traiga un respaldo nunca se restaura y uno que sea un enlace simbólico nunca se sigue. También en la restauración de UpdraftPlus. | 1.0.63 | `backups-maintenance.php` |
| **Informe de diferencias de `wp-config.php` (I10).** La restauración nunca escribe `wp-config.php`. `verify` (REST y WP-CLI, antes de confirmar), la subida, el resultado y el aviso de la restauración y su correo dicen qué constantes define el respaldo y este servidor no, cuáles sólo define este servidor y cuáles tienen otro valor (`wp_config`). Los dos archivos se leen con el tokenizador (sólo `define()`; nunca se ejecutan): un valor que no es un literal se informa como «expresión». Sólo se muestra el valor de las constantes conocidas de WordPress (límites, depuración, caché, cron, actualizaciones, direcciones, red y edición) que no son secretas ni rutas del servidor; de las demás (`DB_*` salvo `DB_CHARSET` y `DB_COLLATE`, cualquier nombre con `PASS`, `KEY`, `SALT`, `SECRET`, `TOKEN`, `AUTH`, `NONCE`, `LICENSE`…, y las de los plugins, que suelen llevar licencias o tokens con nombres cualesquiera), sólo que difieren; el correo sólo lleva los nombres. `wp … verify` muestra «Origen → este sitio» y las diferencias juntas (un respaldo de otro servidor suele traer las dos) y, con un respaldo incompatible, el motivo y nunca la reubicación. | 1.0.63 | `backups-config-diff.php`, `backups-cli-verify.php` |
| **Hallazgos del laboratorio Webuzo (H5 a H7).** H5: todo archivo del almacén es `0600` (antes `0644`; php-wasm, `0666`): el respaldo publicado (ZIP o DGBK, que llevan `wp-config.php` y la base de datos), el SQL y el ZIP del área de trabajo mientras se escriben, el DGBK sellado, el ZIP descifrado para restaurar, la copia previa, la verificación de una copia en la nube, los cerrojos y la protección del almacén; uno de una versión anterior se corrige en la operación siguiente. H6: Backups no reescribe un archivo que ya tiene el contenido correcto (ver «Archivos que Backups escribe fuera del almacén»). H7: la ayuda de `wp help digitalisimo-backups automation` y `… jobs` ya no muestra las marcas del comentario. | 1.0.63 | `backups-private-files.php`, `backups-no-rewrite.php`, `backups-cli-help.php` |
| **Pasos cortos dentro de la exportación, el empaquetado y la verificación (T2).** Para la pila del propietario (OpenLiteSpeed con 30 s y 64 MB, detrás de Cloudflare) sin WP-CLI ni cron del sistema. «database», «pack» y «verify» avanzan por tramos: cada uno trabaja hasta el fin del presupuesto del tick (la mitad de `max_execution_time`, entre 5 y 20 s, en la web), nunca más allá del límite real de la petición menos un margen (el `max_execution_time` leído antes de que el motor llame a `set_time_limit(0)`; `digitalisimo_backups_request_limit` para un proxy), se detiene antes si quedan menos de max(4 MB, 1/16 de `memory_limit`) libres (la exportación, sólo si ya casi no queda) y guarda su punto de control en el trabajo (`checkpoint.<fase>.resume`; el empaquetado y la verificación, al menos cada 5 s). Tras una muerte (tiempo o memoria agotados, proceso matado, recarga del servidor web) el tramo siguiente trunca lo escrito después del punto de control y sigue desde ahí; trabaja la mitad y vuelve a crecer al avanzar, y los intentos de una fase cuentan fallos seguidos sin avanzar, no tramos; las vueltas de «verify» a «pack» cuentan aparte y agotan sus 3 intentos. **Exportación:** cada tabla sale **entera de una sola instantánea** (InnoDB, bloqueo de lectura de las tablas no transaccionales o la transacción de SQLite), por lotes en el orden de la clave primaria: paginación por clave para enteros, decimales, binarios y texto, y por desplazamiento (exacto dentro de la instantánea) para ENUM, SET, FLOAT, DOUBLE, TIMESTAMP y otros tipos que no se comparan como se ordenan; el lote se adapta al tamaño de las filas y a la memoria libre. Entre tablas el tramo se cierra al vencer el presupuesto del tick; una tabla empezada sigue hasta el límite real de la petición y, si no termina, se descarta (el SQL se trunca a su comienzo) y se repite entera en el tramo siguiente; si no cabe en una petición entera tres veces, la fase falla nombrando la tabla y pidiendo WP-CLI o el cron del sistema. Así una tabla nunca mezcla dos instantáneas: una fila borrada y vuelta a crear entre tramos (un transitorio que caduca, con el mismo `option_name`) o un cambio de esquema a mitad (`dbDelta`) ya no dejan un SQL que no se importa. Coherencia: si toda la exportación cabe en un tramo (siempre en WP-CLI y en el cron del sistema por PHP CLI, que no tienen plazo) es la instantánea única de siempre; si no, entre tablas puede haber diferencias de segundos o minutos, el manifiesto lo declara (`database_consistency`: `transaction-per-table` o `lock-per-table`) y el respaldo queda «Completo con avisos». **Antes de empaquetarlo, el SQL se comprueba** por tablas y por tramos: sólo sentencias del exportador, cada INSERT con exactamente las columnas de su CREATE y un valor por cada una, la clave primaria entera estrictamente creciente y ningún valor repetido en una clave única (hasta 200 000 por índice y tabla, o lo que quepa en la reserva de memoria). Uno que no se importaría hace empezar la exportación de nuevo (a la tercera, falla con el motivo), y la verificación exige que el `database.sql` del ZIP sea ese SQL comprobado (su SHA-256). El SQL es idéntico byte a byte al del exportador de un paso, con las defensas de S4 (lo que quede en claro se cifra antes de cada tramo y cada fila pasa por `export_row()`). No se respaldan las tablas temporales de una restauración que murió a mitad (`<prefijo>dgbkr_*` y `<prefijo>dgbko_*`). La extracción de un sitio de la red (1.0.63) sigue exportando y empaquetando en un paso: su exportador elige las tablas del sitio y deja el informe que lee el empaquetado. **Empaquetado:** escritor ZIP propio de sólo añadir (`Digitalisimo_Backups_Zip_Writer`) en lugar de `ZipArchive::close()`, que escribe todo el archivo de una vez y no se puede partir: inventario por carpetas, con su cola y su lista de archivos en el área de trabajo (con `fsync`); cada entrada terminada se anota con su posición, CRC, tamaños y SHA-256; una entrada grande queda a medias entre tramos (deflate termina el tramo con un vaciado completo y el siguiente sigue con un compresor nuevo; el estado de CRC-32 y SHA-256 se guarda serializado o, en PHP 7.4, se reconstruye leyendo lo escrito), y lo ya escrito no se reescribe (sólo los 12 bytes de CRC y tamaños de la cabecera de la entrada abierta, al terminarla). Lo que ya viene comprimido (JPEG, PNG, WebP, MP4, ZIP, fuentes…) y lo de 2 GiB o más se guarda sin comprimir; Zip64 sólo cuando hace falta. Las carpetas de código (raíz, `wp-admin`, `wp-includes`, `plugins`, `themes` y `mu-plugins`) anotan su fecha al inventariarse: si alguna cambió al terminar la escritura (el actualizador de WordPress crea `.maintenance` en la raíz y reemplaza la carpeta del plugin) o le falta un archivo, se empaqueta otra vez desde el inventario; si sigue cambiando, el respaldo queda «Completo con avisos» y nombra las carpetas. Un archivo que desaparece fuera de ellas (uploads, cachés) se omite con un aviso visible; uno obligatorio hace fallar la fase. Una entrada a medias cuyo archivo cambió (tamaño, fechas de modificación y de cambio, o inodo) vuelve a empezar; tras dos vueltas (un `debug.log` al que escribe cada petición) se copia de corrido en un tramo, y si dos peticiones mueren copiándola la fase falla nombrando el archivo. El manifiesto v3 se escribe por partes desde la lista de entradas (los mismos bytes que `wp_json_encode( …, JSON_PRETTY_PRINT )`, con `packer: digitalisimo-zip/1` y, si los hay, los avisos en `notices`) y su firma se calcula a la vez (la misma que `sodium_crypto_auth`). **Verificación por tramos:** estructura con ZipArchive (CHECKCONS), número de entradas y firma; después cada entrada contra su CRC, sus tamaños y su SHA-256 (las de 256 MB o más, por partes y retomables, descomprimiendo de 4 KB comprimidos en 4 KB para que la memoria quede acotada aunque los datos se compriman mucho). El manifiesto se comprueba siempre como lo hará la restauración por pasos (firma y esquema estricto, leídos por partes con memoria constante), así que «verificado» prueba que la restauración podrá leerlo en ese servidor, sea cual sea su tamaño (hasta la integración con la restauración por pasos se decodificaba entero si cabía y, si no, el respaldo quedaba «Completo con avisos» con la memoria que necesitaría). Al registrar el respaldo, un manifiesto de más de 1 MB se lee por partes. El sellado DGBK sigue al final de «verify». **Avisos visibles:** lo que cambia lo que es el respaldo (varias instantáneas, archivos desaparecidos, código que cambió) deja el trabajo en «Completo con avisos» con el texto en la tarjeta de trabajos y en la barra de progreso; la fila del historial guarda la coherencia de la base (`consistency`) y los avisos (`notices`), que se ven en el historial (insignia «Con avisos»), en la lista de restauración, en REST y en WP-CLI (`create` los imprime como `Warning:`, también con `--porcelain`, y `list` tiene la columna `notices`). Mismo motor para la pantalla, WP-Cron, `POST /jobs/tick` y WP-CLI. El formato es el de siempre: lo restauran sin cambios el código actual y el publicado de 1.0.58, 1.0.60, 1.0.61 y 1.0.62, también con Zip64, cifrado y avisos en el manifiesto. | @NEXT_BACKUPS@ | `backups-steps.php`, `backups-steps-review.php`, `backups-steps-compat.php` y, en Playground, `tests/playground/backups-steps.sh` |
| **PHP 7.4, el mínimo que declara el plugin.** Dos fallos que sólo aparecían en PHP 7.4 (publicados en 1.0.62 y 1.0.63). (1) `strpos()` con una aguja vacía devuelve `false` en PHP 7.4 y `0` en PHP 8: en una conexión de Amazon S3 o S3 compatible sin carpeta (prefijo `''`, la raíz del bucket) `own_key()` rechazaba todo recibo («La copia remota no está en el bucket y la carpeta de esta conexión…»), así que cada subida fallaba de forma permanente al verificarse y las copias no se podían descargar, consultar ni borrar. El mismo patrón con `$table_prefix = ''` (WordPress lo admite): el respaldo de un sitio no cambiaba el prefijo de origen por `wp_` y la extracción del sitio principal de una red no llevaba tablas (`site_tables()`, `global_plugin_tables()`). Los prefijos se comparan ahora con `strncmp()`, que acepta el prefijo vacío en cualquier versión sin depender de los polyfills de WordPress. (2) La prueba del modo de mantenimiento simula la muerte con `exit` y ejecuta «la operación siguiente» en una función de cierre de la misma petición: PHP 8 destruye las variables locales al ejecutar `exit`, PHP 7.4 no, y el bloqueo de operaciones de `service_restore()` seguía tomado. En producción esa operación es otra petición, cuando PHP o el sistema ya cerraron los descriptores de la que murió: el producto no cambia y la prueba cierra ese descriptor como lo haría el final de la petición. Revisado el resto del plugin en PHP 7.4: ninguna función, clase ni constante de PHP 8 sin protección (sólo `fsync()`, tras `function_exists()`), sin errores de sintaxis ni de compilación, comparaciones e `in_array()` estrictos; `parse_url()` sólo difiere con una consulta o un fragmento vacíos (`https://h/dav?`), que los validadores de WebDAV y S3 descartan al reconstruir la URL. La batería de Backups del workflow pasa en PHP 7.4, 8.2 y 8.5 (php-wasm). | @NEXT_BACKUPS@ | `backups-destinations.php`, `backups-maintenance.php`, `backups-integrity.php`, `backups-site-extract.php` |

Ensayos en WordPress Playground 7.1.2 (SQLite): respaldo y restauración de un sitio (ZIP de 37 MB, 3792 archivos) y
de una red con un segundo sitio; en ambos un cambio posterior al respaldo volvió a su valor anterior y el historial
conservó el ZIP original y la copia previa. No sustituyen ensayos con MySQL, Multisite en un servidor convencional,
migración a otro servidor ni destinos remotos reales.

Compatibilidad de los trabajos (1.0.59), probada con el código real de 1.0.58 tomado de su ZIP publicado: sus
respaldos se verifican y restauran con el motor nuevo, y los del motor nuevo (manifiesto v3 con las mismas claves
obligatorias más `job_id`, `packer`, `database_snapshot_at`, `files_started_at` y `files_completed_at`) pasan su
verificador y su restauración, así que volver a 1.0.58 sigue restaurando. Las filas antiguas del historial se leen igual
(las nuevas añaden `job`, `transfer`, `transfer_destination` y `transfer_error`); `digitalisimo_backups_full` y
`digitalisimo_backups_policy` conservan nombre y argumentos y ahora encolan; el AJAX de 1.0.58 (una pestaña abierta
durante la actualización) sigue funcionando con su forma de respuesta, y la opción `digitalisimo_backups_manual_job_<uid>`
ya no bloquea y se retira al abrir la pantalla. La muerte real con `SIGKILL` se ensayó con dos procesos de php-wasm
(trabajador bloqueado dentro de la exportación, `kill -9` y reanudación) y la CI la repite con PHP del sistema; igual se
ensayó un error fatal real dentro de la instantánea (memoria agotada), que el apagado de PHP anota y devuelve a la cola
antes de que pase el detector.

Ensayo de seguridad (1.0.59, `tests/playground/backups-security.sh`) en WordPress 7.1.4 sobre Playground (SQLite,
PHP 8.5 sin libsodium nativo, así que con el sodium_compat de WordPress), por los handlers `admin-post` reales con
nonce y capacidad. En un sitio individual, la frase (con comillas, barra, `<` y `&`) se guardó sin alterarse, un nonce
falso no cambió nada y ni la pestaña ni la redirección la devolvieron; un respaldo firmado de 36 MB se restauró y
revirtió un cambio con el llavero conservado y fuera de `database.sql`; una copia con un byte del manifiesto cambiado
se rechazó sin copia previa; un archivo suelto sin firma pidió `RESTAURAR SIN FIRMA` y «sólo firmados» lo bloqueó; con
la envoltura del llavero de otras claves de `wp-config.php`, el respaldo cifrado falló cerrado sin dejar archivos hasta
volver a escribir la frase. Un respaldo cifrado de 36 MB tardó unos 11 minutos en crearse, cifrarse y comprobarse, sin ZIP en claro ni restos y sin nombres de archivo legibles; en «otro servidor» (llavero vacío) la restauración sin frase y con una frase equivocada se rechazó sin cambios, y con la frase restauró el sitio en unos 6 minutos. En una red, el llavero quedó en `sitemeta`, la restauración lo conservó y un subsitio
no pudo cambiar la seguridad. Con `DIGITALISIMO_BACKUPS_KEY`, el cifrado fue obligatorio, la pestaña mostró la huella
y nunca el valor, rechazó una frase y la copia previa quedó en claro y firmada con la constante. Tras la revisión de
1.0.59 (guardián SQL, UpdraftPlus, huérfanos y diario de la restauración) se repitieron los tres modos: 247 s, todo correcto.

Ensayo conjunto de trabajos y seguridad (1.0.59, `tests/playground/backups-security.sh` con `ENCRYPT=1`: 1273 s,
todo correcto), con cada respaldo creado por el handler `admin-post` real de «Crear respaldo», que lo encola, y la cola
avanzada por ticks como WP-Cron. En un sitio individual, el respaldo firmado de 36 MB se creó en 17 s y se restauró; el
respaldo cifrado de 36 MB recorrió las fases del trabajo (firma en «pack», comprobación y sellado del DGBK en «verify»,
unos 11 minutos con sodium_compat sin perder el arrendamiento, y publicación con `rename()`) sin ZIP en claro ni restos,
y en «otro servidor» (llavero vacío) se restauró escribiendo la frase en unos 6 minutos. S8: un respaldo creado y
registrado por la clase de 1.0.58 (el código publicado antes de esta entrega, tomado de su ZIP) se restauró con el código
actual, revirtió el cambio y conservó el llavero, en el sitio individual y en una red con un subsitio. En la red, el
llavero quedó en `sitemeta` y un subsitio no pudo cambiar la seguridad; con `DIGITALISIMO_BACKUPS_KEY`, la copia previa
quedó en claro y firmada con la constante. Con el código unido se repitieron además, con procesos de php-wasm (PHP 8.2),
la muerte real con `SIGKILL` y el error fatal de `backups-job-kill.php`.

Ensayo de las credenciales de los destinos (1.0.61, S9 de `tests/playground/backups-security.sh`: 471 s junto con
S1, S3, S4, S6, S7 y S8) en WordPress 7.1.4 sobre Playground (SQLite, PHP 8.5 con el sodium_compat de WordPress), por los
handlers `admin-post` reales. En un sitio individual y en una red (almacén en `sitemeta`): las cinco credenciales
guardadas por «Servidores» no aparecen en claro en ninguna fila de `options` ni de `sitemeta`, y `status` las da por
cifradas con los tres destinos conectados; el `database.sql` de un respaldo de 36 MB lleva el almacén cifrado y ningún
secreto; tras cambiar la contraseña, restaurar devuelve las del respaldo, cifradas con las salts del servidor, y Google
renueva el acceso con ellas (la petición se intercepta; Playground no tiene SSH2); y un respaldo de «otro servidor»
(su `wp-config.php` con otras salts y su almacén cifrado con ellas) se restaura con las credenciales vueltas a cifrar
con las salts de éste, sin tocar su `wp-config.php`. Los ajustes quedan con autoload; reescritos en claro y sin autoload
(como lo haría 1.0.60 tras volver desde `rollback/`), la siguiente carga del escritorio los cifra y les devuelve el
autoload, también con `wp_set_option_autoload()` cuando no queda nada en claro. S8 identifica ahora el respaldo de la clase anterior por lo que
sólo escribe el motor de trabajos (`job_id`, `packer`): esa clase escribe en `plugin_version` la versión del plugin
cargado, así que compararla con 1.0.58 fallaba desde que el repositorio pasó de esa versión.

Ensayo de los tramos cortos (@NEXT_BACKUPS@, T2, `tests/playground/backups-steps.sh`) en WordPress 7.1.4 sobre
Playground (SQLite, PHP 8.5), con 1.0.63 fusionada y la pila del propietario simulada: `max_execution_time=30` y
`memory_limit=64M` en cada petición (también admin y WP-Cron) y sin WP-CLI. Un sitio de 300,1 MB (6 950 entradas: JPEG y
MP4 aleatorios, textos comprimibles y miles de archivos pequeños; base de 29 MB con 9 169 filas) se encoló desde la
pantalla y avanzó sólo con 29 peticiones del navegador (AJAX) y de `wp-cron.php` (el loopback de WP-Cron cortado), en
222 s. La más larga duró 11,5 s (10,8 s después de cargar WordPress) y ninguna pasó de 58,5 MB, con WordPress ocupando
ya 54,5. Con ticks de 0,1 s mientras se exporta (el ensayo los acorta para que una base que aquí sale en menos de 1 s se
reparta), la base salió en 4 instantáneas, cada tabla entera en una (`transaction-per-table`), y se comprobó antes de
empaquetarla; el empaquetado avanzó en 7 tramos y la verificación en 4. Se mataron tres peticiones a mitad de un tramo
retomado (exportación, con la tabla a medias repetida entera; empaquetado y verificación) y el trabajo siguió sin gastar
más de un intento. Terminó «Completo con avisos», con los dos textos en la pantalla: la base no salió de una instantánea
común y, con 64 MB, la verificación no pudo leer entero el manifiesto (1,4 MB; la restauración de 1.0.63 lo decodificaba
entero). Desde la integración con la restauración por pasos, la verificación y la restauración lo leen por partes y ese
segundo aviso ya no existe.
Con ticks normales la base cabe en un tramo y sale en una sola instantánea. `unzip -t` y el `zipfile` de Python
aceptaron el ZIP (cada SHA-256 igual al del manifiesto). Una corrida anterior, con la reserva de memoria de 1/8 que había
antes de 1.0.63, cerró la verificación tras cada entrada (3 731 tramos): con 1.0.63 WordPress ocupa más y la reserva pasó
a 1/16. Con procesos de php-wasm se repitió además la muerte real con `SIGKILL` a mitad de una tabla
(`backups-job-kill.php`): el tramo siguiente exportó la tabla entera desde su primera fila y el SQL salió idéntico al de
una exportación sin cortes.
**Pendiente:**

| Bloque | Brecha principal |
| --- | --- |
| Trabajos | Hecho en 1.0.59 con reanudación por fase (T1) y en @NEXT_BACKUPS@ con tramos cortos dentro de la exportación, el empaquetado y la verificación (T2). Queda de T2 el sellado DGBK, que sigue siendo un paso por respaldo (con libsodium nativo cifra y comprueba unos 200 MB en pocos segundos; con sodium_compat, unos 11 s por MiB en php-wasm, no cabe en 30 s), repetir en un hosting con MySQL (webuzo-lab) el ensayo de Playground con 30 s y 64 MB, y un relanzamiento propio por loopback (como el de Backuply) para que un respaldo programado avance sin visitas: hoy, sin cron del sistema, cada tick de WP-Cron llega con una visita. **T3**, en parte desde 1.0.62: S3 multipart, Dropbox y FTP reanudan por fragmentos con el estado en el área del trabajo, y un trabajo que falla o se cancela en «transfer» retira lo que dejó en el destino (multipart abortado, temporal de SFTP, FTP, WebDAV o Dropbox). Falta reanudar SFTP por desplazamiento y las sesiones de Google Drive y OneDrive (hoy repiten la subida entera), y la cancelación durante una subida SFTP o WebDAV sólo llega cuando esa subida termina (S3, Dropbox, FTP, pCloud y WebDAV con cURL la comprueban entre fragmentos o durante la subida). Desde 1.0.60, REST y WP-CLI consultan, hacen avanzar y cancelan la misma cola (`wp digitalisimo-backups jobs cancel`, `POST /jobs/<id>/cancel`). Ensayo real pendiente: `kill -9` en un hosting con MySQL (en Playground, T2 ya mata peticiones a mitad de tramo con 30 s y 64 MB). Una restauración interrumpida ya no pierde el historial, la seguridad ni el llavero (diario del almacén), pero deja el sitio a medias: hay que restaurar la copia previa a mano, escribiendo la frase si el llavero volvió sin su envoltura. Desde T2 el empaquetado ya no depende del aviso de avance de `ZipArchive::close()`: late y guarda su punto de control al menos cada 5 s, así que otro servidor sin `flock` común ya no lo toma por muerto mientras avanza; un tramo que otro servidor retomó empieza su fase de nuevo (sólo se retoma un punto de control escrito en el mismo servidor) y, a la tercera vez, la fase falla. |
| Restauración | Desde @NEXT_BACKUPS@, trabajo por pasos con tablas temporales y un cambio atómico, archivos preparados y movidos, mantenimiento, reanudación, vuelta automática a la copia previa y, si ésa falla, «Volver a la copia previa» en la pantalla y `wp digitalisimo-backups jobs rollback` (ver «Restauración por pasos»). Desde E1 la base de datos se reubica (dominio, ruta, prefijo, red) con datos serializados seguros, también por pasos. **Restaurar desde un destino remoto** (B1b: descargar en streaming, comprobar el SHA-256 y restaurar como un archivo local), **sincronizar** los respaldos de un destino (B2) y la descarga en streaming de Google Drive y OneDrive (I12). Falta el ensayo con MySQL real y el corte del laboratorio (Webuzo con OpenLiteSpeed, 30 s, 64 MB y Cloudflare), la prueba extremo a extremo a otro servidor real (E2), la migración en un clic y restaurar UpdraftPlus por pasos. |
| Seguridad | Argon2id opcional para la frase. Botón «Inspeccionar» con el informe de un archivo antes de restaurarlo (hoy la inspección completa ocurre al restaurar, antes de la copia previa). Ensayo del cifrado en un hosting real con libsodium nativo y MySQL. Sin la extensión, sodium_compat es lento: en php-wasm, unos 11 s por MiB (36 MB tardaron unos 11 minutos en cifrarse y comprobarse y unos 6 en descifrarse y restaurarse); la pestaña mide la velocidad del servidor y estima el tiempo del último respaldo. |
| Restauración (UpdraftPlus) | La ruta de UpdraftPlus ya valida todo antes de cambiar nada, pero aún no crea copia previa ni revierte si falla a medias. Su restauración y el diario de una restauración interrumpida sólo se han probado en php-wasm, sobre la base de datos simulada; falta ensayarlos en WordPress real. |
| Automatización | Respaldo previo a actualizaciones, incrementales con borrados y cadena íntegra, retención que preserve dependencias y varios destinos por trabajo. |
| Escala | Bases y archivos grandes reales, BLOBs, objetos SQL especiales y consistencia entre archivos y base de datos (WooCommerce). |
| Cobertura | Hecho en 1.0.60: REST y WP-CLI sobre los casos de uso de la pantalla, con la cola de «Trabajos» (crear y ejecutar encolan; WP-CLI hace avanzar la cola hasta terminar; listar, consultar, avanzar y cancelar trabajos) y la confianza y el cifrado de «Seguridad» (`.dgbk`, `RESTAURAR SIN FIRMA`, la frase sólo en el cuerpo de REST o por `--secret-file` o STDIN en WP-CLI, y un error claro sin la clave), y rutas externas que funcionan (`wp-config.php`) o fallan antes de exportar con un mensaje claro (carpetas). Falta respaldar de verdad wp-content, plugins, medios o temas fuera de `ABSPATH`, que necesita una raíz nueva del ZIP aceptada por el validador estricto de «Seguridad» y por el empaquetador de «Trabajos». Faltan en REST y WP-CLI bloquear, eliminar y descargar, y los ajustes de «Seguridad» (frase, cifrado y «sólo firmados»), que siguen sólo en la pantalla; también que la pantalla avise al abrirse de una estructura no respaldable y repetir en un hosting con MySQL el ensayo de REST y WP-CLI que ya pasa en Playground. |
| Ensayos reales | MySQL, Multisite convencional, almacén privado en hosting real, servidor SFTP real y cuentas reales de Google Drive, OneDrive, Dropbox, pCloud, Amazon S3, Backblaze B2, Cloudflare R2, Wasabi, DigitalOcean Spaces, Akamai/Linode y Vultr (hechos: MinIO, FTPS explícito y WebDAV reales, también en Playground). FTPS implícito real. |
| Destinos | Varios destinos por trabajo; verificación de S3 por la suma SHA-256 compuesta del servicio en vez de descargar (ahorra tráfico de salida); restaurar, sincronizar y retener directamente en el remoto sobre `list`/`delete` del contrato; la lista de la pantalla de restauración con las copias traídas de un remoto. |

Siguiente bloque: cerrar seguridad y restauración en entornos reales (MySQL, Multisite,
almacén privado y SFTP en un hosting real) y preparar la restauración transaccional; después, rutas externas, varios
destinos por trabajo y restaurar directamente desde un destino remoto.

## Arquitectura

| Componente | Función |
| --- | --- |
| `digitalisimo-backups.php` | Registra el módulo, el menú compartido y el actualizador propio. |
| `includes/destinations/` | Destinos remotos: contrato `Digitalisimo_Backups_Destination` y estado reanudable (`class-destination.php`), registro de tipos y conexiones (`class-destinations.php`), HTTP con TLS verificado (`class-http.php`), un archivo por proveedor (SFTP, FTP/FTPS con su cliente, WebDAV, Google Drive y OneDrive, Dropbox, S3 y compatibles, pCloud) y la pantalla «Servidores», el OAuth, «Probar conexión» y «Traer al almacén» (`trait-backups-destinations.php`). |
| `assets/destinations.js` | Campos de cada tipo y condicionales en «Servidores» y «Probar conexión» por AJAX. |
| `includes/class-backups.php` | Interfaz, ajustes, cron, ZIP/SQL, historial, retención y restauración (los destinos, en `includes/destinations/`); expone al motor de trabajos sus piezas (`job_preflight`, `job_export_database`, `job_build_package`, `job_verify_package`, `job_seal_package`, `job_register`, `job_transfer`, `job_prune`, `history_update`). Carga las clases de `includes/security/`. |
| `includes/class-backups-job-store.php` | `Digitalisimo_Backups_Job_Store`: estado de cada trabajo en `<almacén>/jobs/<uuid>/` (escritura atómica, `.jobs.lock`, lectura validada, registro de eventos acotado, marca de cancelación y poda). |
| `includes/class-backups-jobs.php` | `Digitalisimo_Backups_Jobs`: cola, máquina de estados, arrendamiento, latido, detector, reintentos, caducidad, cancelación, disparadores (tick único, watchdog horario, AJAX y admin-post) y vista pública. API `enqueue`, `tick`, `cancel`, `get`, `all` y `views`, que usan también REST y WP-CLI por `Digitalisimo_Backups_Service`. |
| `includes/class-backups-backup-job.php` | `Digitalisimo_Backups_Backup_Job`: fases del respaldo con su punto de control (`database`, `pack` y `verify` por tramos, con `checkpoint.<fase>.resume`) y `run_inline()` para la copia previa de una restauración. |
| `includes/steps/class-step-budget.php` | `Digitalisimo_Backups_Step_Budget` (T2): plazo de cada tramo (presupuesto del tick, mínimo para avanzar y límite real de la petición con margen), memoria libre frente a `memory_limit`, escala adaptativa tras una muerte y medida de cada tramo. |
| `includes/steps/class-sql-export.php` | `Digitalisimo_Backups_Sql_Export` (T2): fase «database» con cada tabla entera en una instantánea, por lotes con paginación por clave primaria o por desplazamiento; una tabla cortada en el límite de la petición se repite entera; comprobación del SQL (columnas, valores y claves) antes de empaquetarlo, y el modo de coherencia que declara el manifiesto. |
| `includes/steps/class-zip-writer.php` | `Digitalisimo_Backups_Zip_Writer` (T2): escritor ZIP de sólo añadir; entradas que se suspenden y retoman (vaciado completo de deflate y estado del hash), Zip64 cuando hace falta y directorio central desde la lista de entradas. |
| `includes/steps/class-packer.php` | `Digitalisimo_Backups_Packer` (T2): fase «pack» (inventario por carpetas, escritura, otra pasada si cambia el código, entradas que cambian copiadas de corrido y cierre con el manifiesto, sus avisos y su firma por partes) y verificación por tramos de «verify», con los avisos de lo que no pudo probar. |
| `includes/security/class-sodium.php` | libsodium nativo o el `sodium_compat` de `wp-includes`; qué funciones faltan. |
| `includes/security/class-keys.php` | Constante, PBKDF2, huella y subclaves (BLAKE2b con separación de dominio), llavero envuelto, clave y descriptor por huella, unión tras restaurar. |
| `includes/security/class-credentials.php` | `Digitalisimo_Backups_Credentials`: almacén cifrado de las credenciales de los destinos (`digitalisimo_backups_credentials`), clave desde AUTH_KEY y SECURE_AUTH_KEY (también desde valores dados), lectura de las salts literales del `wp-config.php` de un respaldo (hasta 64 KiB), contexto de conexión del dato adicional, nuevo cifrado tras restaurar, claves del origen para el diario, «Olvidar», adopción al convertir en red y estado público sin valores. |
| `includes/security/class-manifest-auth.php` | Bloque `auth` del manifiesto, comentario `DIGITALISIMO-AUTH/1` y estados de la firma. |
| `includes/security/class-encrypted-file.php` | Contenedor DGBK v1: cabecera, cifrado y descifrado por bloques con memoria constante. |
| `includes/security/class-archive-inspector.php` | Reglas de entradas, esquema estricto del manifiesto v3 y espacio en disco. |
| `includes/security/class-sql-guard.php` | Divisor de sentencias por fragmentos y lista blanca de los dialectos `exporter` y `mysqldump`. Para la reubicación, `rename_tables()` (cambia el identificador exacto que leyó, también en `REFERENCES`) y `on_insert()` (posición de cada literal de un INSERT comprobado). |
| `includes/security/class-security.php` | Costuras `seal()` (la llama la fase «verify» de un trabajo cifrado) y `open()`; `job_settings()` y `job_plan()`, el cifrado y la huella que fija la especificación de un trabajo y la clave que cada fase abre por esa huella; nivel de confianza y confirmación, pestaña «Seguridad» y sus acciones `admin-post`. |
| `assets/manual-progress.js` | Encola, hace avanzar la cola con la pantalla abierta, pinta su estado como texto y cancela. |
| `includes/class-digitalisimo-core.php` | Integra el menú DIGITALÍSIMO sin exigir otro plugin. |
| `includes/class-digitalisimo-updater.php` | Actualizador por `Update URI` e índice de la Release, independiente de los demás plugins. |
| `includes/trait-backups-service.php` | `Digitalisimo_Backups_Service`: casos de uso compartidos por la pantalla, REST y WP-CLI (estado, historial, crear y ejecutar en cola, trabajos, verificar y restaurar con «Seguridad», y automatizaciones), con el alcance de sitio o red y el bloqueo del almacén. |
| `includes/class-backups-rest.php` | `Digitalisimo_Backups_Rest`: rutas `digitalisimo-backups/v1` con la capability de la pantalla; la frase de un respaldo, sólo en el cuerpo de la petición. |
| `includes/class-backups-cli.php` | `wp digitalisimo-backups`, `wp digitalisimo-backups automation` y `wp digitalisimo-backups jobs`; hace avanzar la cola en su proceso y lee la frase de un archivo o de STDIN. |
| `includes/class-backups-layout.php` | `Digitalisimo_Backups_Layout`: carpetas de WordPress que un respaldo completo debe contener; rechaza las externas o enlazadas antes de exportar. |
| `includes/class-backups-restore-job.php` | `Digitalisimo_Backups_Restore_Job`: fases de la restauración por pasos, etiquetas, tramos de la barra y qué fases se pueden cancelar; las fases las ejecuta el motor de `includes/restore/trait-backups-restore-engine.php`. |
| `includes/restore/trait-backups-restore-engine.php` | `Digitalisimo_Backups_Restore_Engine` (dentro de `Digitalisimo_Backups`): encolar y conducir la restauración, cada fase con su punto de control, tablas temporales y cambio, preparación y movimiento de archivos, archivos ajenos, vuelta a la copia previa y estado público (`restore_status()`). |
| `includes/restore/class-manifest-stream.php`, `class-manifest-index.php` | Lectura de `manifest.json` por partes con la semántica de `json_decode()` y su índice en disco por posición del ZIP (memoria constante). La usan la firma, el esquema estricto, la verificación del contenido y la restauración. |
| `includes/restore/class-restore-maintenance.php` | `.maintenance` de la restauración por pasos: deja pasar sólo los ticks con el testigo de la restauración; renovado en cada tick y sin caducar desde el cambio. |
| `includes/steps/class-step-budget.php` | Presupuesto de cada tramo (copiado tal cual de T2): plazo del tick, límite real de la petición, escala adaptativa tras una muerte y cupo de memoria. |
| `includes/class-backups-relocate.php` | `Digitalisimo_Backups_Relocate`: reescritor de valores (URL y rutas en serializado, JSON y URL codificadas, sin `unserialize()`), prefijo de tablas y claves, plan de origen a destino (sitio o red) con sus comprobaciones previas, aplicación en la importación (`attach()`) o sobre una base ya importada (`rewrite_tables()`), red, direcciones, cachés y comprobación final (`finish()`). `Digitalisimo_Backups_Relocate_Probe` lee del SQL en seco las direcciones y la red del origen. |
| `includes/class-backups-site-extract.php` | `Digitalisimo_Backups_Site_Extract`: «Extraer como WordPress individual» (alcance `extracted-site`): qué tablas, usuarios, metas, opciones y archivos de un sitio de la red viajan, los ajustes de red DIGITALÍSIMO que se materializan, el bloque `origin` y los pares de URL de los medios que añade al plan de reubicación, y la configuración de Backups del destino que se conserva al restaurar. `Digitalisimo_Backups_Site_Extract_Export` decide fila a fila dentro de la instantánea del exportador. |
| `assets/backups-admin.css` | Presentación propia sobre el diseño compartido `digitalisimo-ui/`. |

La configuración y el historial viven en opciones del sitio o de la red; el estado de los trabajos, en el almacén
privado. Hay acciones `admin-post`, eventos WP-Cron, creación manual y políticas automáticas; destinos local, FTP/FTPS,
SFTP, WebDAV, Google Drive, OneDrive, Dropbox, Amazon S3 y compatibles y pCloud; e importación de ZIP propio y de conjuntos Updraft. Los formatos heredados (copia de base de
datos, copia por sitio, Updraft) siguen inventariados, pero no satisfacen el requisito de respaldo completo y no se
presentan como verificados.

Riesgos conocidos del flujo actual:

1. Desde T2 la exportación, el empaquetado y la verificación avanzan por tramos cortos, pero una base que no cabe en
   un tramo se exporta en varias instantáneas, cada tabla entera en una (declarado en el manifiesto y «Completo con
   avisos»): para una sola instantánea hay que usar WP-CLI o el cron del sistema por PHP CLI. Una tabla que no cabe en
   una petición (con 30 s, unos 20 s útiles) no se puede respaldar desde la web: la fase falla pidiendo WP-CLI. La
   comprobación del SQL compara bytes, así que no ve un duplicado que sólo lo es para la intercalación (mayúsculas y
   minúsculas en `utf8mb4_unicode_ci`), y por encima de 200 000 valores por índice se fía de la instantánea. Las
   carpetas de código se comparan por su fecha, en segundos: un archivo reescrito en su sitio sin crear ni borrar nada
   no se detecta (una actualización de WordPress sí, por `.maintenance`). Si la verificación no puede leer el manifiesto
   con la memoria libre, lo declara pero no lo prueba. Sólo se retoma un punto de control del mismo servidor: con
   varios servidores tras un balanceador, la fase empieza de nuevo en cada cambio y a la tercera falla. El sellado DGBK
   sigue siendo un paso, igual que la exportación y el empaquetado de «Extraer como WordPress individual»; la
   restauración sigue siendo una sola petición larga (fuera de T2).
2. `create_incremental_site()` selecciona por fecha de modificación y no inventaría borrados ni verifica la cadena; la
   retención puede borrar la base de incrementales dependientes.
3. `restore_digitalisimo_zip()` importa SQL antes de extraer archivos; la recuperación es de mejor esfuerzo, no atómica.
   `restore_updraft_set()` no prueba que estén todas las piezas de WordPress.
4. `database_sql()` heredado acumula SQL en memoria para exportaciones antiguas; el respaldo integral usa
   `database_sql_to_file()` e `import_zip_sql()`.

Arquitectura objetivo, por etapas compatibles: un motor único recibe una especificación de trabajo inmutable (sitio o
red, tipo, destinos, retención) y una cola persistente gestiona bloqueos, heartbeat, cancelación y reanudación. Los
exportadores escriben a un staging privado por lotes; el verificador reabre el paquete y compara todas las entradas
del manifiesto antes de transferir o registrar `SUCCESS`. Los destinos implementan `put/get/size/delete/list/verify` (hecho
desde 1.0.62, «Destinos remotos») y el trabajo guarda el recibo y el SHA-256 de cada copia; la retención trata cada cadena incremental como una unidad. La
restauración descarga y verifica en staging, respalda el destino, activa mantenimiento y aplica los cambios con
rollback, y nunca importa SQL de un archivo no verificado. Interfaz, REST y WP-CLI invocan el mismo motor, con
capability, nonce o autenticación, sin tareas largas durante el guardado.

## REST y WP-CLI

Las dos superficies llaman a los mismos casos de uso que la pantalla (`Digitalisimo_Backups_Service`), sin repetir pasos
del motor: alcance, comprobaciones previas, la cola de «Trabajos», la apertura del paquete de «Seguridad» y la
restauración con copia previa. Crear y ejecutar una automatización encolan un trabajo, como los botones de la pantalla;
restaurar y verificar trabajan en la misma petición.

| REST (`/wp-json/digitalisimo-backups/v1`) | WP-CLI | Qué hace |
| --- | --- | --- |
| `GET /status` | `wp digitalisimo-backups status [--format=json]` | Si la instalación puede respaldarse completa (y por qué no), operación y trabajos en curso, ubicación de `wp-config.php`, destinos, cifrado, «sólo firmados», origen de la clave (`wp-config`, `passphrase` o `none`), credenciales de los destinos (`credentials`: `none`, `encrypted`, `pending`, `unprotected` o `needs_reentry`, y `credentials_notice` con qué hacer, nunca un valor), último respaldo y automatizaciones. `restoring` y `restore` (`state`: `none`, `running`, `interrupted`, `rollback`, `failed`, `failed_midway`, `rolled_back` o `cancelled`, con fase, avance, copia previa y `notice`) dicen qué pasa con una restauración sin terminar o que no terminó bien. Desde 1.0.63, `notifications`: si avisan los fallos y los éxitos y si el correo es propio (`custom`) o el de administración (nunca la dirección). |
| `GET /backups` | `wp digitalisimo-backups list [--fields=…] [--format=…]` | Historial, del más reciente al más antiguo: tamaño, SHA-256, destino, alcance, contenedor (`zip` o `dgbk`), firma, huella corta de la clave, si falta la clave (`needs_secret`), bloqueo y si es restaurable aquí. Desde 1.0.63, `copies` enumera las copias (la local, si el ZIP sigue en el almacén, y la remota con su estado: `ok`, `pending`, `failed`, `cancelled` o `legacy`), `destination` es a dónde se envió y `restorable` sólo exige el ZIP local completo (en WP-CLI, la columna `copies`: «local, google»). |
| `POST /backups` (`destination`, `network`) | `wp digitalisimo-backups create [--destination=…] [--network] [--async] [--porcelain]` | Encola el respaldo completo de la instalación o de la red. REST responde 202 con el trabajo; WP-CLI lo hace avanzar hasta que queda verificado (y copiado, con destino remoto) o, con `--async`, sólo lo encola. |
| `GET\|POST /backups/<archivo>/verify` (`secret`, `addresses`) | `wp digitalisimo-backups verify <archivo> [--secret-file=…\|--secret-stdin] [--addresses=current\|backup]` | Sin tocar el sitio, las mismas comprobaciones del paquete que la restauración: SHA-256 del historial, firma o registro, descifrado completo de un `.dgbk`, entradas, CRC y SHA-256 de cada una contra el manifiesto, manifiesto v3 estricto y el SQL en seco. Informa la confianza, la confirmación que pedirá, si las condiciones de esta instalación lo admiten (`problems` dice qué cambiar) y, en `relocation`, «Origen → este sitio» y qué se reescribiría al restaurarlo (direcciones, rutas del servidor, prefijo, sitios de la red). Desde 1.0.63, `wp_config`: las constantes de `wp-config.php` que difieren entre el respaldo y este servidor (WP-CLI las lista, sin valores secretos ni rutas). |
| `POST /backups/<archivo>/restore` (`confirm`, `network`, `secret`, `adopt_secret`, `addresses`) | `wp digitalisimo-backups restore <archivo> --confirm=… [--network] [--secret-file=…\|--secret-stdin] [--adopt-secret] [--addresses=current\|backup]` | Restauración por pasos (ver «Restauración por pasos») con la copia previa bloqueada. REST avanza lo que cabe en la petición y responde 202 con `restoring`, el trabajo (`job`) y `maintenance_token` (el testigo que deja pasar sus ticks durante el mantenimiento; también llega como cookie) si sigue, o 200 con el resultado si terminó; `POST /jobs/tick` o la pantalla la terminan. WP-CLI la hace avanzar hasta terminar. `RESTAURAR` para un archivo firmado o registrado aquí; `RESTAURAR SIN FIRMA` para uno sin firma ni registro. Con `adopt_secret` o `--adopt-secret`, la frase escrita firma y cifra los respaldos nuevos. Un respaldo de otra dirección, carpeta o prefijo se reubica: `addresses` `current` (por defecto, el sitio conserva sus direcciones) o `backup` (conserva las del respaldo); la respuesta lleva `relocation` con lo reescrito por tabla, las cachés vaciadas y la comprobación final, y el aviso lo resume. Otro valor responde 400 (`addresses`). |
| `POST /backups/<archivo>/fetch` (`network`) | `wp digitalisimo-backups destinations fetch <archivo> [--network]` | Desde 1.0.62, «Traer al almacén»: descarga en streaming la copia remota de una fila del historial, la compara con su SHA-256 registrado y la deja en el almacén privado para verificarla o restaurarla; si ya está, no hace nada. Escribe en el almacén de toda la instalación: en una red pide el alcance de red, como crear o restaurar, y toma el cerrojo de operaciones (409 si hay otra en curso). Si la conexión de la fila se eliminó, responde 410 con qué hacer. |
| `GET /destinations` | `wp digitalisimo-backups destinations list [--format=…]` | Desde 1.0.62: cada destino remoto (la conexión principal de cada tipo y las adicionales) con su tipo, nombre, si está listo y, si sus credenciales no se abren, qué volver a escribir; nunca un valor secreto. |
| `POST /destinations/<id>/test` | `wp digitalisimo-backups destinations test <id>` | Desde 1.0.62, «Probar conexión» de una conexión guardada: sube un archivo pequeño, lo verifica, lo descarga, lo busca al listar y lo borra (502 con el motivo si falla). |
| `GET /automations` | `wp digitalisimo-backups automation list` | Automatizaciones y su próxima ejecución. Con `time`, la hora fija del sitio (vacía si no tiene). |
| `POST /automations/<id>/run` (`network`) | `wp digitalisimo-backups automation run <id> [--network] [--async] [--porcelain]` | «Ejecutar ahora»: encola la automatización (202), como `create`. |
| `GET /jobs` (`limit`) · `GET /jobs/<id>` | `wp digitalisimo-backups jobs list [--limit=…] [--fields=…] [--format=…]` | Trabajos sin terminar y los últimos terminados, con la vista de la tarjeta «Trabajos» (estado, fase, avance, reintento, archivo o error) más destino, automatización y si se cifra. |
| `POST /jobs/<id>/cancel` | `wp digitalisimo-backups jobs cancel <id>` | Como «Cancelar»: uno en cola termina ya; uno en curso se detiene en su siguiente punto de control; uno ya verificado y registrado no se cancela (409). |
| `POST /jobs/tick` | `wp digitalisimo-backups jobs run [--quiet]` | Hace avanzar la cola: REST da un paso con el presupuesto de una petición web, como el navegador con la pantalla abierta; WP-CLI sigue sin límite de tiempo hasta que no queda trabajo listo (sirve para el cron del sistema en un hosting que corta las peticiones largas). |
| — (pantalla: «Volver a la copia previa») | `wp digitalisimo-backups jobs rollback [--network]` | Desde @NEXT_BACKUPS@: recupera un sitio que quedó a medio cambiar (una restauración que falló después del cambio y cuya vuelta a la copia previa no se pudo hacer o falló): encola otra vuelta a su copia previa, sin hacer otra, con el mismo testigo del mantenimiento, y la hace avanzar (WP-CLI hasta terminar). Sin una restauración a medio cambiar responde 409; sin su copia previa en el almacén, 422. |
| `POST /sites/<id>/extract` (`network`, `super_admins`, `network_secrets`, `destination`; en el cuerpo, `secret`) | `wp digitalisimo-backups extract <id> --network [--super-admins=none\|managing\|all] [--secret-file=…\|--secret-stdin] [--network-secrets] [--destination=…] [--async] [--porcelain]` | «Extraer como WordPress individual» (ver «Extraer un sitio de la red»): sólo en una red, desde el sitio principal, con el alcance de red y, en REST, un superadministrador. `super_admins` es `none` por defecto. La frase propia del paquete (`secret`) sólo en el cuerpo de la petición (en la URL, 400) o por archivo o entrada estándar; con el cifrado de la red activo es obligatoria. REST responde 202 con el trabajo (409 si ese sitio ya tiene una extracción pendiente); WP-CLI lo hace avanzar hasta que queda verificado. `verify` de un paquete así añade `extract` (sitio, usuarios, bloqueados, plugins, mu-plugins, drop-ins, ajustes materializados, claves no copiadas y lo que no viajó). |
| `POST /uploads` (`name`, `size`, `sha256`) · `GET /uploads` · `GET /uploads/<id>` · `POST\|PUT /uploads/<id>/chunk` (`offset`; cuerpo en bruto o archivo `chunk`) · `POST /uploads/<id>/finish` (`secret`) · `DELETE /uploads/<id>` | `wp digitalisimo-backups upload list [--format=…]` · `upload finish <id> [--secret-file=…\|--secret-stdin]` · `upload cancel <id>` (para subir, se copia el archivo al almacén) | Desde 1.0.63: subir un respaldo por fragmentos al almacén privado. `POST /uploads` responde 201 con `id`, `chunk_size` y `received`; cada fragmento va en `offset` = lo ya recibido (si no, 409 con `received`); `finish` lo valida como archivo no confiable y responde con el nombre en el almacén, la confianza, la palabra de confirmación, `problems`, `relocation` y `wp_config`, o 422 si se descartó; repetido, devuelve lo mismo. Con la subida conservada: 507 `upload_space`, 400 `secret_wrong`, 500 `upload_error`; 202 `upload_validating` mientras otro proceso la comprueba y 504 `upload_timeout` tras tres cortes (se termina con `wp … upload finish`, sin límite de tiempo). `GET /uploads/<id>` dice `phase` (`uploading`, `validating`, `done`, `rejected`), `validating`, `attempts`, `error` y, cerrada, `result`; `GET /uploads` lista las de todas las cuentas (`mine`). La frase de un `.dgbk` sólo va en el cuerpo de `finish`. |

- **Hora exacta con poco tráfico.** WP-Cron sólo corre cuando el sitio recibe visitas, así que una automatización con
  hora fija sale a esa hora o en la primera visita después. Para una hora exacta, programa el cron del sistema (en
  Webuzo, «Cron») con la cuenta del sitio: `*/5 * * * * cd /ruta/de/wordpress && wp cron event run --due-now --quiet`
  ejecuta lo vencido (las automatizaciones y el tick de la cola) y `wp digitalisimo-backups jobs run --quiet` hace
  avanzar la cola hasta vaciarla. Define `DISABLE_WP_CRON` sólo si ese cron corre siempre.
- **Autorización.** REST usa la autenticación de WordPress: con cookie exige el nonce `wp_rest` en `X-WP-Nonce`, y
  también sirven las contraseñas de aplicación. Cada ruta pide la capability de la pantalla (`manage_network_options`
  en una red, `manage_options` en un sitio individual): sin sesión responde 401 y sin permiso, 403. WP-CLI corre con la
  cuenta del sistema, que ya controla los archivos y `wp-config.php`; como cualquier comando que escribe archivos, debe
  ser la del servidor web: con `--allow-root`, los ZIP y lo restaurado quedarían de root y WordPress no podría
  reemplazarlos.
- **Contexto.** En una red, el historial, las automatizaciones y la cola son del sitio principal: un subsitio (otra URL
  de REST o `--url`) responde 400 y su `status` lo explica. Crear, restaurar y ejecutar abarcan todos los sitios y piden
  `network=true` o `--network`; en un sitio individual ese alcance se rechaza. Cancelar o hacer avanzar un trabajo no
  abarca más que la cola, así que no lo pide. Con varias redes en la misma base de datos, el respaldo las abarca todas:
  sólo responde el sitio principal de la red principal, porque administrar una red secundaria no autoriza a copiar ni a
  reemplazar las demás, y el motor tampoco exporta desde ella (vista con avance, cron y automatizaciones).
- **Cola.** Un respaldo manual o una automatización nunca tienen dos trabajos sin terminar: el segundo responde 409 con
  el identificador del primero (`data.job`). El trabajo anota su origen (`rest` o `cli`) y lo continúan el navegador,
  WP-Cron, `POST /jobs/tick` o WP-CLI; con otra operación en el almacén, un paso responde `busy` y WP-CLI espera y
  vuelve a probar. Restaurar se niega con un trabajo pendiente o en curso (409): hay que cancelarlo o esperar.
- **Frase o clave.** Sólo hace falta para un respaldo cifrado o firmado con una clave que este sitio no tiene, y se usa
  tal cual. En REST va en el cuerpo de la petición (JSON o formulario): en la cadena de consulta quedaría en los
  registros del servidor y del proxy, así que se rechaza (400, `secret_in_url`) sin usarla. En WP-CLI se lee de un
  archivo (`--secret-file`, hasta 64 KiB, sin el salto de línea final) o de la entrada estándar (`--secret-stdin`, sin
  eco en una terminal); `--secret=<valor>` es un error, porque quedaría en la lista de procesos y en el historial del
  shell. Un `.dgbk` cuya clave no tiene el sitio responde, sin la frase y antes de tomar el bloqueo, 400
  `secret_required` con la huella corta de la clave (`data.key`) y dónde va la frase en esa superficie.
- **Respuestas.** Los resúmenes (estado, historial, trabajos, verificación y automatizaciones) no llevan la ruta del
  almacén ni identificadores remotos: de una copia remota sólo se nombra el proveedor. Ninguna respuesta ni salida lleva
  la frase, la clave, su envoltura ni la huella completa (de una clave sólo se nombran sus 8 primeros caracteres),
  tampoco el error de una frase equivocada. Un aviso que impide respaldar nombra la carpeta que hay que corregir. Un
  archivo se busca sólo dentro del almacén: un nombre con ruta o un enlace simbólico que salga de él responde 404.
- **Errores.** Responden con el mensaje del motor y un código estable, `digitalisimo_backups_<motivo>` (los que vienen
  de una automatización conservan los suyos, `digitalisimo_backup_*`):
  - 400: alcance (`scope`, `main_site`), destino (con credenciales que no se abren, el mensaje dice qué volver a escribir), automatización parcial, confirmación (`confirm`, `confirm_unsigned`),
    `secret_required`, `secret_in_url` o identificador de trabajo no válido.
  - 403: sin permiso, o un archivo sin firma con «Restaurar sólo respaldos firmados» (`signed_only`).
  - 404: archivo, automatización o trabajo inexistente.
  - 409: otra operación en curso o un trabajo activo al restaurar (`busy`), respaldo ya pendiente (`duplicate`),
    automatización desactivada o trabajo que ya no se puede cancelar.
  - 422: instalación no respaldable (`not_ready`; también al restaurar, porque la copia previa es un respaldo completo,
    y con el cifrado activo y sin clave), paquete que no supera las comprobaciones o frase que no corresponde
    (`invalid`), y restauración rechazada antes de tocar el sitio (`restore_rejected`).
  - 500: fallo del motor o del almacén, y restauración que falla después de la copia previa (`restore_failed`, con lo
    que se recuperó).

  WP-CLI termina con código 1 y el mismo mensaje.
- **Almacén.** Sin `DOCUMENT_ROOT` (WP-CLI y cron), la carpeta privada sale de `DIGITALISIMO_BACKUPS_STORAGE_DIR` o de la
  ruta que registró la pantalla (ver «Almacenamiento local»).

Ensayo en WordPress Playground (`tests/playground/backups-coverage.sh`), sobre el motor de 1.0.59: WordPress 7.1.4,
PHP 8.5 y SQLite, en un sitio individual y en una red con un subsitio, todo por HTTP real. Pasaron 99 comprobaciones
(45 en el sitio individual y 54 en la red, 395 s). Con E1 (1.0.63) se repitió con `addresses` en `verify` y `restore` (REST con la validación de WordPress, WP-CLI
con la suya): 109 comprobaciones, 320 s, todo correcto. El ensayo corta el loopback de WP-Cron (un mu-plugin de `setup.php`)
para que la cola sólo avance cuando él lo pide; sin ese corte, en el sitio individual WP-Cron terminó por su cuenta el
trabajo encolado por REST y el de `create --async` antes de que el ensayo los cancelara.

| Qué | Resultado |
| --- | --- |
| Autenticación | Sin sesión, 401. Cookie sin nonce, 401 (WordPress la trata como anónima). Nonce falso, 403. Cookie con nonce, 200. |
| Contraseñas de aplicación | De admin, 200. De una editora, 403. En la red, de un administrador del sitio principal que no administra la red, 403. |
| Cola por REST | `POST /backups` responde 202 con el trabajo y un segundo, 409 con el identificador del primero. `GET /jobs` lo lista y `POST /jobs/tick` lo termina (ZIP de 39 MB en 21 a 25 s). `POST /jobs/<id>/cancel` cancela uno en cola, y repetirlo responde 409. |
| Respaldo y restauración por REST | El respaldo del trabajo queda en el historial, restaurable, y `verify` lo da por íntegro y registrado sin nombrar rutas del servidor. La frase en la URL se rechaza (400) sin repetirla. Un cambio posterior vuelve atrás al restaurar (25 a 28 s), con su copia previa. |
| «Seguridad» | Con un `.dgbk` pequeño cifrado con una frase que el sitio no tiene: `verify` y `restore` sin ella responden 400 `secret_required` con la huella corta y dónde va la frase, y WP-CLI nombra `--secret-file` y `--secret-stdin`. Una frase equivocada da 422 sin repetirla. Con la buena, en el cuerpo o en `--secret-file`, lo descifrado no es un ZIP: la validación lo rechaza (422) y `.staging` queda vacía. Ninguna respuesta lleva la frase ni la huella completa. |
| Red por REST | Sin `network=true`, 400. El subsitio responde 400 y su estado explica por qué. |
| WP-CLI real | Sinopsis tomadas del PHPDoc. `restore` sin `--confirm` no corre. `create --porcelain` hace avanzar la cola hasta terminar (19 a 20 s) y su respaldo aparece primero en el historial de REST. `create --async` deja el trabajo en cola, `jobs list` lo muestra primero y `jobs cancel` lo cancela. En la red, sin `--network` o desde el subsitio, falla con el motivo. |
| Medios de un subsitio | Con `upload_path` fuera de `ABSPATH`, el estado nombra el sitio, el ajuste y la carpeta que calcula WordPress, y `POST /backups` responde 422 sin exportar. Con su valor original, la red vuelve a estar lista. |

## Restaurar en otro dominio

Desde 1.0.63 (E1). Un respaldo completo se puede restaurar en otro servidor, otro dominio, otra ruta y con otro
prefijo de tablas, en un sitio individual o en una red. El motor es `Digitalisimo_Backups_Relocate`
(`includes/class-backups-relocate.php`), con su contrato en el PHPDoc; lo reutilizarán la migración en un clic y
«extraer un sitio de la red».

**Cuándo se aplica.** Al restaurar, se compara el origen del respaldo (su manifiesto y, sobre todo, lo que dice su
propio SQL: `home` y `siteurl` de cada sitio, `blogs`, `site` y `subdomain_install`, leídos en la pasada en seco del
guardián) con esta instalación. Si coinciden direcciones, rutas y prefijo, nada cambia respecto a versiones anteriores.
Si no:

- Por defecto, el sitio **conserva sus direcciones** (las de este servidor) y las del respaldo se cambian por ellas.
- «Conservar las direcciones del respaldo» (pantalla), `addresses=backup` (REST) o `--addresses=backup` (WP-CLI) deja
  las URL del respaldo. Las rutas del servidor y el prefijo de tablas siguen siempre a este servidor: son hechos de la
  instalación, no una preferencia. Si `wp-config.php` de este servidor define `WP_HOME` o `WP_SITEURL`, esas constantes
  son «las direcciones de este sitio» y siguen mandando después de restaurar, también con la opción del respaldo.
- La pantalla de Restaurar dice en cada archivo «origen … → este sitio» y, al elegirlo, qué se reescribirá; `verify`
  lo devuelve en `relocation` (REST) o lo escribe (WP-CLI). Después de restaurar, el aviso resume lo hecho y la pantalla
  muestra el informe por tabla («Última restauración en otro dominio», `digitalisimo_backups_last_relocation`).

**Qué se reescribe.**

- **URL**: `https://origen.com/ruta` → `https://destino.com/otra`, también `http://`, `//origen.com` (protocolo
  relativo, queda `//destino.com`), `www.origen.com`, JSON con barras escapadas una y dos veces (`https:\/\/…`, como el
  `_elementor_data` de Elements) y URL codificadas una y dos veces (`https%3A%2F%2F…`, `https%253A%252F%252F…`,
  conservando `%2f` o `%2F`, también con el puerto codificado, `localhost%3A8080`). Un puerto por defecto explícito
  (`https://origen.com:443/`, `http://origen.com:80/`) es el mismo origen. El host compara sin mayúsculas y sólo
  completo: `origen.com` no toca `origen.com.mx`, `miorigen.com`, `cdn.origen.com`, `origen.com:8443` ni
  `ftp://origen.com`. La ruta compara hasta un límite de segmento (`/blog` no toca `/blogs` ni `/blog.html`). Un sitio
  que se queda en su dirección (un par igual) no lo cambia el par más corto de su host.
- **PHP serializado**: se recorre el formato (nunca `unserialize()`, así que ningún objeto del respaldo se instancia y
  ningún `__wakeup` o `__destruct` corre) y cada cadena se reescribe con su longitud en bytes recalculada, también
  serializados dentro de cadenas serializadas (hasta 16 niveles) y con multibyte. Las claves de un array que son URL (un
  mapa de redirecciones) también cambian, salvo que chocaran con otra clave del mismo array.
- **Rutas del servidor**: `ABSPATH` (y su ruta real), `wp-content` y `uploads` del origen → las de este servidor, con
  límites (`/var/www/origen2` no se toca), sólo donde las guardan WordPress y los plugins: un valor que **es** una ruta
  (empieza por ella y no sigue texto), en cualquier tabla y dentro de un serializado; y, en ajustes y metas (`options`,
  `sitemeta`, `*meta`), una ruta dentro de un texto o JSON si va seguida de `/wp-content`, `/wp-includes`, `/wp-admin` o
  `/wp-config.php`. El contenido público (`post_content`, extractos, comentarios) y un tutorial que nombra la ruta no
  cambian, y un origen de un solo segmento (`/wordpress`, el de Playground) nunca se cambia dentro de un texto, porque
  se confunde con un enlace relativo (`href="/wordpress/"`). El manifiesto trae las rutas desde 1.0.63 (bloque
  `origin`).
- **Prefijo de tablas**: cada tabla del origen se crea, vacía y llena con el prefijo de este sitio (el guardián cambia
  el identificador exacto que leyó, también en `REFERENCES` de una clave foránea). Cambian `{prefijo}user_roles` de cada
  sitio y en `usermeta` todas las claves que empiezan por el prefijo (`{prefijo}capabilities`, `{prefijo}N_user_level`,
  lo que escribe `update_user_option()`). Las claves de permisos que el respaldo ya traía con el prefijo de **este**
  sitio (`{prefijo-destino}capabilities`, `user_level` o `user_roles` de otra instalación: una tabla de usuarios
  compartida o un cambio de prefijo anterior) no se aplican, porque darían permisos que el usuario no tenía en el sitio
  restaurado; de otra clave repetida queda la del sitio restaurado. El otro WordPress que use en esta base el prefijo
  del origen no se toca. Un prefijo distinto ya no impide restaurar.
- **Tablas ajenas**: el respaldo guarda todas las tablas de la base del origen. Al restaurar en otro dominio, servidor o
  prefijo, las que no tienen el prefijo del origen (otra aplicación u otro WordPress de aquella base) **no se
  restauran**: las de este servidor con el mismo nombre no se borran ni se recrean. `verify`, la pantalla y el informe
  las nombran antes de confirmar. Restaurar en el mismo sitio, sin reubicar, las sigue restaurando como antes.
- **Red**: con el dominio y la ruta de la red de este servidor, `site`, `sitemeta.siteurl`, `blogs` y `home`/`siteurl` de
  cada sitio, todo **durante la importación**, fila por fila: ningún punto de la restauración deja unas tablas de la red
  en un dominio y otras en el otro. Por subdirectorios, `origen.com/uno/` pasa a `destino.com/<ruta de la red>uno/`; por subdominios,
  `uno.origen.com` pasa a `uno.destino.com`; también la meta `source_domain` de cada usuario (el dominio, sin esquema,
  del sitio donde se registró). Un sitio con dominio propio (`cliente.com`) se queda como está.
- **Cachés**: transitorios (`options` y `sitemeta`), `rewrite_rules` de cada sitio (WordPress las regenera en la
  siguiente visita: es `flush_rewrite_rules` sin depender del `$wp_rewrite` de otro sitio, que `switch_to_blog()` no
  cambia), las metas `_elementor_css`, `_elementor_element_cache` y `_elementor_page_assets`, las opciones
  `_elementor_assets_data`, `elementor-custom-breakpoints-files` y `_elementor_global_css`, los archivos de
  `uploads[/sites/N]/elementor/css/` (Elements los regenera) y la caché de objetos. El CSS de las fuentes locales de
  Google (`elementor/google-fonts/css/`) se reescribe. En un sitio individual cuya ruta cambia, el bloque de WordPress de
  `.htaccess` se regenera como al guardar los enlaces permanentes, como último paso: en algunos hostings escribir
  `.htaccess` recarga el servidor web (OpenLiteSpeed de Webuzo) y corta la petición en curso. Las cachés de página y de
  recursos optimizados que viajan en el respaldo (`wp-content/cache/`: WP Super Cache, W3TC, WP Rocket, Autoptimize;
  `wp-content/litespeed/`) llevan las direcciones del origen: con otras direcciones se vacían. Después de cualquier
  restauración se pide a LiteSpeed que purgue su caché (cabecera `X-LiteSpeed-Purge` y la acción del plugin LiteSpeed
  Cache); por WP-CLI sólo queda la acción del plugin.
- **Archivos de configuración del servidor** (en la raíz de WordPress: `.htaccess`, `.user.ini`, `php.ini`,
  `wordfence-waf.php`), al extraerlos: las rutas y las direcciones del origen cambian por las de este servidor (también
  el host escapado de un `RewriteCond`, `^www\.origen\.com$`), y un `auto_prepend_file` o `auto_append_file` cuyo
  archivo no existirá aquí queda comentado con un aviso: si no, PHP daría un error fatal en cada petición, también en el
  escritorio desde el que se restauraría la copia previa. El cortafuegos de Wordfence del respaldo queda apuntando a este
  servidor (su `wordfence-waf.php` viaja); si el del respaldo se desactiva y este servidor tenía uno que funcionaba en
  ese archivo, se conserva el de este servidor.

**Qué no se reescribe, y por qué.**

- La columna `guid` de las entradas: WordPress pide no cambiarla (identifica la entrada en los lectores de feeds).
- Los correos (`hola@origen.com`, `admin_email`, `user_email`): un buzón vive en el DNS del dominio, no en el servidor
  web; cambiarlo rompería el inicio de sesión por correo, los avisos y los pedidos.
- El dominio como texto, sin esquema ni `//` («visita origen.com»): es redacción, no una dirección.
- Un serializado dañado (longitudes que no cuadran): se deja intacto, porque reescribirlo como texto lo rompería más. Un
  objeto `C:` (Serializable: su contenido es un formato propio de la clase) y un anidamiento de más de 16 serializados o
  512 arrays también se dejan e informan.
- Las rutas del servidor en el contenido público y en textos libres (ver «Rutas del servidor»), y las de un origen en
  Windows (`C:\xampp\htdocs\…`).
- Base64, entidades HTML (`&#x2F;`) y otras codificaciones propias de un plugin.
- Las vistas SQL y las tablas dentro de comentarios ejecutables con otro prefijo: se rechazan antes de tocar nada.
- Las rutas del servidor de un respaldo anterior a 1.0.63: su manifiesto no las trae (las URL sí se reescriben).
- Un conjunto de UpdraftPlus: sigue su propio camino, que exige el prefijo de este sitio y no reubica direcciones.
- Las reglas de nginx o del `.htaccess` de una red si cambia la ruta: se avisa.

La comprobación final busca en la base de datos (con `LIKE`) cada host y cada ruta del origen en las tablas de este
sitio y clasifica cada fila candidata con el mismo criterio: `urls` y `paths` (lo que la reescritura habría cambiado y
sigue ahí) deben ser 0; `guid`, `kept` (lo que se deja a propósito, también las rutas en el contenido público), `emails`
y `other` se informan. Un error de la base de datos en la comprobación se avisa; nunca cuenta como 0.

**Comprobaciones previas** (antes de la copia previa y de cualquier escritura, con qué cambiar): sitio individual ↔ red;
red por subdominios ↔ por subdirectorios («define SUBDOMAIN_INSTALL como …»); `DOMAIN_CURRENT_SITE` o
`PATH_CURRENT_SITE` definidas en `wp-config.php` con otro dominio o ruta que los que tendrá la red (los de este sitio o,
con «conservar las del respaldo», los del respaldo); `SITE_ID_CURRENT_SITE` o `BLOG_ID_CURRENT_SITE` que el respaldo no
tiene; un respaldo sin lista de tablas (formato anterior al v3) con otro prefijo; tablas que acabarían con el mismo
nombre o con más de 64 caracteres; una vista o una tabla dentro de un comentario ejecutable que no se puede renombrar
(`verify` responde lo mismo que la restauración); y un valor que menciona el origen y no cabe en la memoria de PHP para
reescribirlo (ver «Escala y fallos»).

**Escala y fallos.** La reubicación ocurre en la importación, sentencia por sentencia y con los literales que comprobó
el guardián, así que la memoria no depende del tamaño de las **tablas** ni hace falta clave primaria, y no hay una
segunda pasada sobre la base de datos. Sí depende del tamaño de cada **valor**: recorrer un serializado grande cuesta de
3 a 19 veces su tamaño. Por eso la pasada en seco mide cada valor que menciona el origen contra lo libre de
`memory_limit` (entre 24, como mucho 32 MiB) y, si alguno no cabe, rechaza la restauración antes de la copia previa
diciendo qué subir; en la importación, uno que se escapara haría fallar la sentencia y volver a la copia previa, nunca
dejaría el sitio a medias por un error fatal. Para una base ya importada, `rewrite_tables()` trabaja por lotes de clave
primaria (simple o compuesta, con el literal según el tipo de la columna), lee grupos de hasta 4 MiB y no toca una
tabla sin clave primaria (la informa). Si algo falla durante la importación, el sitio no cambió (desde @NEXT_BACKUPS@ la importación va a tablas temporales);
si falla después del cambio (la red, las direcciones, los archivos), la restauración vuelve sola a la copia previa. La
restauración es un trabajo por pasos que se reanuda tras una muerte, y la red y las direcciones se fijan en las tablas
temporales antes del cambio, así que ningún punto intermedio deja una red sin cargar (ver «Restauración por pasos»;
`backups-relocate-interrupted.php` corta justo después del cambio).

Ensayo en WordPress Playground (`tests/playground/backups-relocate.sh`): WordPress 7.1.4, PHP 8.5, SQLite y núcleo de
Elements 4.3.5, todo por HTTP. Un sitio individual y una red por subdirectorios (con el subsitio `/uno/`), con páginas
de Elements con imagen de fondo, se respaldan por REST en `http://origen.test` (prefijo `wp_`) y se restauran por REST
como archivo suelto de otro servidor en `http://destino.test`, instalado con el prefijo `dst_` junto a otro WordPress
con `wp_` en la misma base. `verify` dice «Origen → este sitio»; después de restaurar (71 y 72 s con el servidor
cargado), las páginas y su CSS regenerado cargan sin `origen.test`, los enlaces internos van al dominio nuevo, ninguna
tabla del sitio menciona el origen salvo `guid` (7 y 13, que la comprobación final encuentra y deja), el otro WordPress
queda intacto, los sitios de la red tienen el dominio nuevo y se inicia sesión con la cuenta del respaldo, con el
escritorio (de la red) y sus permisos: 45 comprobaciones, 574 s, todo correcto. El cambio de ruta del sitio y de carpeta
del servidor y la red por subdominios no caben en Playground (sirve en la raíz, con `ABSPATH=/wordpress` y hosts por
cabecera) y se prueban en `backups-relocate.php`.

## Restauración por pasos

Desde @NEXT_BACKUPS@. El ensayo en un hosting real (`/var/tmp/digi-wt/bk-lab/LAB.md`: Webuzo con OpenLiteSpeed,
`max_execution_time=30`, `memory_limit=64M`, Cloudflare que corta a los 100 s y un cron que recarga el servidor web al
cambiar un `.htaccess`) mostró cuatro fallos de la restauración en una sola petición: con 64 MB no se podía restaurar
desde la web (H1), una restauración cortada a mitad del SQL dejó una red a medias y sin aviso (H2), lo que no estaba en
el respaldo sobrevivía (H3) y la restauración entera duraba de 2 a 4 minutos en una petición (H4). Ahora la restauración
es un trabajo del mismo motor que los respaldos (`Digitalisimo_Backups_Restore_Job`). Una revisión adversaria
(`/var/tmp/digi-wt/bk-restore-review/REVIEW.md`) encontró después caminos que dejaban el sitio a medias o sin aviso; lo
que sigue ya los cubre (C1–C2, A1–A5, M1–M4, B1 y los plausibles, ver `tests/backups-restore-review.php`).

**Fases** (cada una con su punto de control en `jobs/<id>/job.json` y lo intermedio en su área de trabajo):

| Fase | Qué hace | Si el proceso muere |
| --- | --- | --- |
| Verificación del respaldo | Entradas del ZIP, manifiesto v3 leído por partes a un índice en disco, CRC y SHA-256 de cada entrada, espacio, el SQL en seco (guardián, sonda de la reubicación, tablas), el plan de origen a destino con el sitio intacto y las sentencias más grandes pasadas por la reubicación (memoria). Un sitio extraído de una red, con sus reglas (sólo en un WordPress individual). Un rechazo es permanente y no toca nada. | Sigue desde la entrada o la sentencia en que se quedó. |
| Copia previa | Un respaldo completo con las fases del respaldo (sus tramos, cuando T2 los tenga), bloqueado en el historial. La vuelta a la copia previa no hace otra. | Sigue desde la fase del respaldo en que se quedó. |
| Base de datos | Desde aquí, mantenimiento. El SQL va a tablas temporales `<prefijo>dgbkr_<marca>_<n>`; el sitio sigue sirviéndose de las suyas. Al terminar se fijan en ellas la red (`site`, `sitemeta`, `blogs`) y `home`/`siteurl` de cada sitio. | Repite sólo la tabla en curso desde su `DROP TABLE IF EXISTS` (lo ejecutado tras el último punto de control no consta). |
| Archivos | Recorre las raíces gestionadas (lo ajeno, al informe; dentro de una carpeta, por tramos), compara cada archivo con el sitio (tamaño y SHA-256 del manifiesto; sin él, se reescribe siempre) y prepara lo que cambia en un área de trabajo: una carpeta de código entera si algo suyo cambia, un medio o un archivo suelto, solo. Una carpeta de código preparada en otro disco que su sitio se copia ya junto a él. | Sigue desde la entrada en que se quedó; el recorrido se repite (sólo mira). |
| Cambio del sitio | En un solo paso, sin cortes: las tablas pasan de una vez (MySQL: un solo `RENAME TABLE`, atómico; el SQLite de WordPress: una transacción), se reponen historial, llavero, seguridad y credenciales y se pone **todo el código**: cada carpeta de código (wp-admin, wp-includes, cada plugin y tema, mu-plugins) con dos `rename()` (la vieja se aparta junto a la viva como `.digitalisimo-old-<marca>-<nombre>`, en su mismo disco) y los archivos sueltos fuera de los medios (los de la raíz, los drop-ins). Después, por tramos, los medios y lo ajeno. Desde aquí el mantenimiento no caduca. | Sigue; lo hecho lo dice la base de datos (no el error del `RENAME`) y cada carpeta lleva una marca de hecha; si murió justo tras el `RENAME`, el diario repone historial, llavero y credenciales al tomar el bloqueo. |
| Direcciones y cachés | `finish()` de la reubicación (E1): red, direcciones, cachés y comprobación final. | Se repite (idempotente). |
| Limpieza | Tablas viejas y temporales y carpetas apartadas (lo que cambió después de empezar se aparta en el almacén); al final, sin cortes, **este plugin** del respaldo y los `.htaccess` (una sola vez), el informe y el fin del mantenimiento. Nunca hace fallar la restauración. | Se repite; el último paso es de una vez. |

**Reanudar o volver.** Un tramo que muere se reconoce como en los respaldos (el detector, o el apagado de PHP) y la
operación siguiente lo reanuda: la pantalla abierta, WP-Cron (también el cron del sistema), `POST /jobs/tick` o
`wp digitalisimo-backups jobs run`. Una fase tiene 5 intentos seguidos sin avanzar (cada tramo guardado vuelve a contar
desde cero) con esperas de 10 s, 1 min y 5 min; también en WP-CLI (sólo la restauración dentro de un proceso, de las
pruebas, va sin reintentos). Si se agotan antes del cambio, o se cancela, se retiran las tablas temporales, el área de
trabajo y las copias junto a su sitio, y el sitio queda como estaba. Si falla después del cambio, se encola la **vuelta a
la copia previa**: una restauración de la copia previa con el mismo motor, sin reubicación, en mantenimiento sin caducar
desde su primera fase. Si también falla (antes o después de su propio cambio), el sitio **sigue en mantenimiento**, el
estado es «a medio cambiar» y el aviso dice cómo recuperarlo: «Volver a la copia previa» en la pantalla Backups (con el
navegador que la empezó) o `wp digitalisimo-backups jobs rollback`, que encolan otra vuelta con el mismo testigo; las
tablas de antes (`…dgbko_<marca>_*`) y las carpetas apartadas se conservan hasta entonces.

**Qué cambió de verdad (C1).** Que el `RENAME` se hizo lo decide la base de datos: si ya no queda ninguna tabla temporal,
el cambio está hecho aunque PHP recibiera un error (una conexión que se cae con el `RENAME` ya ejecutado, o un `RENAME`
que esperaba un bloqueo y terminó después); entonces se repone lo demás y se sigue. Las tablas viejas de otra
restauración sólo se borran si esa restauración terminó bien; las de una que no terminó pueden ser la única base de
datos de antes de ella: se quedan y el informe las nombra.

**Avisos.** Mientras no termina, la pantalla Backups muestra en todas sus pestañas qué fase lleva, si se interrumpió, qué
pasa con el sitio y qué la hace avanzar; `status` (REST y WP-CLI) lo da en `restore` y lo cuenta entre los `problems`
(los respaldos esperan a que termine). Una restauración que falló a mitad (`failed_midway`, con `recover`: la copia
previa que se puede volver a poner), que volvió a la copia previa o que se canceló se avisa durante 7 días. La tarjeta
«Trabajos» la lista («Restauración» o «Vuelta a la copia previa») y deja cancelarla antes del cambio. Al terminar, el
aviso por correo (pestaña «Avisos») sale con los ajustes que había antes del SQL, pero nunca desde un tick con WordPress
mínimo (sin los plugins de SMTP del sitio, `wp_mail()` iría por `mail()` de PHP): queda pendiente y un tick de WP-Cron
programado a 60 s lo envía con WordPress entero.

**Mantenimiento.** Desde la base de datos hasta el final, `ABSPATH/.maintenance` pone el sitio en mantenimiento y es una
puerta: deja pasar `wp-cron.php` (sin testigo: el cron del sistema o el loopback), el tick por testigo de la pantalla
(`admin-ajax.php?action=digitalisimo_backups_restore_tick`, sin sesión: tras el cambio, las sesiones son las del
respaldo), a quien trae el testigo (la cookie `digitalisimo_backups_restore` del navegador que la empezó, o el parámetro
del mismo nombre, como `maintenance_token` de REST; en el archivo sólo va su SHA-256) y WP-CLI. Esas peticiones cargan
WordPress **mínimo**: sólo este plugin, sin los demás, sin mu-plugins (salvo `DIGITALISIMO_BACKUPS_RESTORE_MU_PLUGINS` en
`wp-config.php`, para un hosting que los necesite) y sin el tema; así ningún código de un plugin corre contra la base de
datos o el núcleo del otro lado. Mientras dura, WP-Cron sólo ejecuta el tick y el watchdog de Backups (nunca las
actualizaciones automáticas ni los eventos vencidos del cron del respaldo). Una petición que se queda fuera (un
visitante, otro administrador) despierta la restauración como mucho una vez por minuto (lanza `wp-cron.php` sin esperar):
cerrar el navegador no la detiene si el sitio recibe visitas o tiene cron del sistema (la pantalla da la línea:
`* * * * * curl -s -o /dev/null https://…/wp-cron.php`); sin ninguno de los dos, sigue cuando se vuelve a la pantalla o
con `wp digitalisimo-backups jobs run`. Cada tick deja programado el siguiente en cuanto toma el trabajo (WP-Cron ya
retiró el evento que lo lanzó): si muere a mitad, WP-Cron lo sigue al minuto sin esperar al watchdog horario. Hasta el
cambio el mantenimiento caduca solo a los 10 minutos si la restauración se detiene (el sitio sigue entero); desde el
cambio, y en una vuelta a la copia previa, no.

**Archivos ajenos (H3).** Dentro de las raíces que el respaldo gestiona —núcleo (`wp-admin`, `wp-includes` y, en la raíz,
todo archivo que PHP pueda ejecutar: `.php`, `.phtml`, `.phar`…), plugins, temas, mu-plugins y medios—, lo que no figura
en un manifiesto v3 se retira: un `.php` inyectado en uploads (también en una carpeta `cache`, `node_modules` o `.git`,
que el respaldo completo trae), un plugin instalado después. Nunca fuera de esas raíces (otra aplicación junto a
WordPress, `robots.txt`, `wp-content/languages` u otras carpetas de `wp-content`), nunca `wp-config.php`, el almacén ni
la base de datos SQLite activa, y nunca un archivo que cargue el `auto_prepend_file`/`auto_append_file` de un
`.user.ini`, `php.ini` o `.htaccess` de la raíz que se queda (un WAF instalado después): se conserva y el aviso lo dice.
Una carpeta de código con un enlace simbólico o un archivo especial, o que es un punto de montaje, no se cambia entera:
sus archivos van uno a uno. En un disco que no distingue mayúsculas, `Foto.JPG` del respaldo es el `foto.jpg` del sitio.
Un respaldo anterior al v3 no retira nada. Lo que se retira y no cambió desde que se encoló la restauración está en la
copia previa; lo que cambió después y no está igual en ella (tamaño y SHA-256) se aparta en el almacén privado
(`restore-kept-<marca>/`) en vez de borrarse. El resultado (`removed`, con `kept` y `kept_in`), el aviso y
`jobs/<id>/removed.txt` dicen la verdad de cada caso.

**Memoria (H1).** El manifiesto se lee por partes (`Digitalisimo_Backups_Manifest_Stream`, la misma semántica que
`json_decode()`) a un índice en disco por posición del ZIP; los duplicados los reconoce la tabla de nombres de libzip y
las colisiones de mayúsculas, sólo en un disco que no las distingue y con muchas entradas, una tabla en un archivo
temporal. Así lo hacen también la firma (HMAC por partes), el esquema estricto y la verificación del contenido de un
respaldo nuevo. Cada petición eleva la memoria con `wp_raise_memory_limit()`, pero las fases caben en 64 MB aunque no
suba: `backups-restore-memory.php` verifica y restaura un respaldo de 40 008 entradas con `memory_limit=64M` y 38 MB ya
ocupados, y `backups-restore-review.php` comprueba que 60 000 entradas no ocupan memoria por entrada.

**Presupuesto (H4).** Cada fase trabaja por tramos con el medidor de T2 (`Digitalisimo_Backups_Step_Budget`, copiado tal
cual): el plazo del tick, nunca más allá del límite real de la petición menos un margen, y la mitad tras un tramo que
murió. La memoria de un tramo se mide por lo que crece (cierra si creció más de 4 MB o si quedan menos de 2 MB hasta
`memory_limit`) y no con la reserva fija de T2: WordPress con sus plugins ya ocupa 52 a 56 de 64 MB en Playground, y la
reserva partía cada tramo en una sola unidad. El paso del cambio pone las tablas y todo el código sin cortes (son
`rename()`; lo que estaba en otro disco ya se copió antes). La copia previa usa las fases del respaldo: hasta que llegue
T2, su empaquetado y su verificación son un paso largo cada uno (límite declarado).

**Garantías de la revisión de E1.** La red y las direcciones se fijan en las tablas temporales antes del cambio, así que
las tablas llegan de una vez coherentes (H1); de dos claves de `usermeta` que chocan al cambiar de prefijo queda la del
sitio restaurado, también resuelto en las temporales (`after_import()` de E1, con lo que cada tramo anota). Una tabla sin
el prefijo del WordPress que se restaura (otra aplicación u otro WordPress de la misma base) se comprueba con el guardián
pero nunca se borra ni se recrea, y se informa (H3). La sonda de E1 mide en la verificación los valores que mencionan el
origen y no cabrían en la memoria para reescribirlos (el plan los rechaza), y además las tres sentencias más grandes pasan
por la reubicación en seco: si no caben, la petición muere ahí, antes de la copia previa; y si algo muere en la
importación, el sitio sigue intacto (H4). Los archivos del servidor de la raíz (`.htaccess`, `.user.ini`, `php.ini`,
`wordfence-waf.php`) pasan por `server_file()` de E1 al prepararlos (rutas y dominio del origen, un `auto_prepend_file`
que aquí no existiría se desactiva y el de este servidor que sí, se conserva), y sin reubicación un `auto_prepend_file`
inexistente también se desactiva (P1); la limpieza avisa si alguno sigue apuntando a un archivo que no existe y vacía la
caché del servidor web (LSCache). El informe público no lleva rutas de este servidor (las de la raíz van relativas, el
almacén y la cuenta del hosting abreviados); las del servidor de origen que traía el respaldo se quedan.

**Ensayo en Playground** (`tests/playground/backups-restore.sh`, con `memory_limit=64M`, límite de 30 s por petición y
tick de 15 s; sitio individual y red por subdirectorios, 1 200 entradas y unas 5 340 entradas en el respaldo): el respaldo se
crea desde la pantalla, el sitio cambia (base de datos, plugin, tema, un `.php` inyectado en uploads, un plugin nuevo y
un archivo fuera de las raíces gestionadas) y la restauración se pide por REST (202) y avanza sólo con ticks web (el de
la pantalla con el testigo y `wp-cron.php` sin testigo, como el cron del sistema), con dos peticiones muertas a propósito a
mitad de la base de datos y a mitad de los archivos; en la red muere además la propia petición de REST ya en
mantenimiento, así que la restauración sólo sigue con `wp-cron.php`. Los dos terminan con el inventario (cada tabla y cada archivo con su hash) idéntico al
de después del respaldo (12 tablas y 5 350 archivos; 28 tablas y 5 351, con T2 integrado), lo inyectado y el plugin nuevo retirados e
informados, el archivo de fuera intacto, mantenimiento (503) para los visitantes y sin él al terminar. En la red, sin
trabajo ni testigo en el cliente, `wp-cron.php` la reanudó y, tras cada muerte dentro de WP-Cron, el tick de guardia la
siguió al minuto. En el sitio individual, una segunda restauración que falla al mover vuelve sola a la copia previa y deja
el sitio como estaba. Ninguna petición pasó de 30 s (con T2 integrado, la copia previa va por tramos dentro de los ticks: la más
larga, 21,2 s, una de la copia previa; las de las fases, 15,5 s) ni de 64 MB (pico 62,5 MB).

**Límites declarados.**

- Las vistas SQL de un respaldo no se restauran por pasos: el renombrado a las tablas temporales las rechaza antes de la
  copia previa, como E1 con otro prefijo. Una clave foránea con nombre que ya usa la tabla viva se crea con el nombre que
  pone MySQL. Una tabla viva que no está en el respaldo y tiene una clave foránea hacia una que sí sigue a su padre
  renombrado (InnoDB) y queda apuntando a la tabla vieja: la limpieza no puede borrarla y lo avisa (no probado en MySQL).
- La copia previa: su empaquetado y su verificación son un paso largo cada uno hasta T2; sin T2, un sitio muy grande en
  un hosting que corta a los 30 s puede no terminarla (el sitio no cambia). Un `.dgbk` se descifra entero en la petición
  que pide restaurarlo, y `database.sql` se copia y se comprueba en un solo tramo.
- Un archivo muy grande se prepara entero en un tramo. Las carpetas apartadas (`.digitalisimo-old-<marca>-…`, con una
  marca aleatoria que no se puede adivinar) viven junto a las vivas hasta la limpieza.
- Una muerte justo después del `RENAME` deja el llavero sin su envoltura hasta volver a escribir la frase (el diario, como
  en 1.0.59); el resto se repone.
- El plugin del respaldo, si es de una versión sin la restauración por pasos, encuentra en el almacén el trabajo ya
  terminado y lo da por dañado (lo aparta); el sitio, el informe en `digitalisimo_backups_last_restore` y lo apartado se
  quedan. Volver a una versión anterior desde `rollback/` con una restauración sin terminar: lo mismo, sin terminarla.
- Un `.htaccess` dentro de una carpeta de plugin o de tema se escribe con su carpeta, en el paso del cambio.
- El aviso por correo de una restauración sale en la primera petición de WP-Cron con WordPress entero después de terminar
  (un tick programado a 60 s); un sitio sin visitas, sin cron del sistema y con `DISABLE_WP_CRON` lo envía cuando algo
  vuelva a correr WP-Cron o la cola.
- UpdraftPlus sigue restaurándose en una sola petición.
- No probado en MySQL real ni en el hosting del laboratorio.

## Extraer un sitio de la red

Desde 1.0.63. Saca un sitio de una red Multisite como paquete restaurable en un WordPress individual, en otro
servidor y otro dominio, que puede ser de otro dueño. El sitio sigue en la red: extraerlo no lo cambia ni lo retira.

**Cómo.** En la red, Backups → «Extraer un sitio como WordPress individual»: el sitio, qué hacer con los
superadministradores que no son miembros, la frase propia del paquete, si copiar las claves y cuentas que el sitio hereda
de la red y el destino. Encola un trabajo del mismo motor que los respaldos completos (tarjeta «Trabajos»: «Extracción de
red.test/dos como WordPress individual»), con sus fases, reintentos y verificación; el paquete
`extracted-site-<sitio>-<fecha>-<rand>` (`.zip`, o `.dgbk` con frase) queda en el historial de la red como «Sitio
extraído», se descarga como cualquier respaldo y no aparece en «Restaurar» de la red. En el destino, un WordPress
individual recién instalado con Backups: se copia a su almacén privado, aparece en «Restaurar» como archivo sin registro y
se restaura con su frase (`RESTAURAR`) o, sin frase, con `RESTAURAR SIN FIRMA`. Después se entra con un usuario del sitio
extraído: las cuentas que tenía el destino ya no existen. REST `POST /sites/<id>/extract` y
`wp digitalisimo-backups extract <id> --network` usan el mismo caso de uso (`Digitalisimo_Backups::service_extract()`).

**Quién.** Es una acción de la red: sólo un superadministrador (en WP-CLI, la cuenta del sistema), desde el sitio
principal y con el alcance de red explícito (`network=true`, `--network` o el formulario de la administración de la red).
La tarjeta sólo existe en la administración de la red y el handler rechaza el envío sin ese contexto: un sitio no puede
extraer otros sitios. Una extracción pendiente de un sitio no se duplica (409).

**Cifrado: la frase del paquete, nunca la clave de la red.** La clave de la red abre todos sus respaldos (los datos de
todos los sitios), así que nunca cifra ni firma un sitio extraído. Con una frase propia (16 caracteres o más; en REST sólo
en el cuerpo, `secret`; en WP-CLI `--secret-file` o `--secret-stdin`) el paquete se cifra y se firma con una clave
derivada de ella como la de «Seguridad» (PBKDF2-SHA256 con su salt, que viaja en el paquete): en el destino basta esa
frase. Mientras dura el trabajo, la clave vive envuelta con `AUTH_KEY` y `SECURE_AUTH_KEY` en el almacén privado y se
borra al registrar el paquete; nunca entra en el llavero. Sin frase, el paquete sale sin cifrar y sin firma, salvo que la
red tenga el cifrado activo: entonces la frase es obligatoria (nunca sale en claro lo que debía cifrarse). La cabecera del
`.dgbk` dice `scope: extracted-site` y `multisite: false`, y debe coincidir con el manifiesto que lleva dentro.

### Qué lleva el paquete

El paquete es un WordPress individual cuyo prefijo de tablas es el que el sitio tenía en la red (`wp_N_`; el sitio
principal, `wp_`): así sus metas y sus roles ya llevan el prefijo de esa instalación, y al restaurar el motor de
reubicación (ver «Restaurar en otro dominio») los pasa al prefijo del destino junto con las URL y las rutas. La extracción
no reescribe ninguna URL, ruta ni prefijo: decide qué viaja, y todo por lista blanca.

| Parte | Qué viaja |
| --- | --- |
| Tablas | Las del sitio (`wp_N_*`; del sitio principal, las `wp_*` que no son globales de la red ni de otro sitio `wp_<n>_*`), más `wp_users` y `wp_usermeta` como `wp_N_users` y `wp_N_usermeta`, leídas sólo para los usuarios que viajan. Manifiesto v3 con `scope: extracted-site`, `multisite: false`, `table_prefix: wp_N_`, `home_url` del sitio, `origin` (el sitio descrito como el WordPress individual que será) y `extract` (el informe). |
| Usuarios | Los miembros del sitio (tienen `wp_N_capabilities`, aunque sea sin rol), con todos sus datos y el hash de su contraseña. Los superadministradores que no son miembros, según `super_admins`: `none` (por defecto) ninguno; `managing`, como administrador sólo quien tiene el correo de administración del sitio (quien tiene un rol con `manage_options` en el sitio ya es miembro); `all`, todos. Haber escrito una entrada, una revisión o un comentario no prueba que se administra el sitio: WordPress guarda como autor a quien edita, también como soporte. Un usuario bloqueado en la red (`spam` o `deleted`) nunca viaja como miembro ni como administrador, porque fuera de la red WordPress no comprueba esas marcas. |
| Autores sin cuenta | Quien escribió entradas o comentarios y no viaja como miembro ni como administrador (un usuario retirado del sitio, un superadministrador, un usuario bloqueado) viaja sólo con lo que pinta su autoría: usuario y nombre público, y de sus metas `nickname`, `first_name`, `last_name` y `description`. Sin contraseña, correo, web, rol ni ninguna otra meta (ni la facturación de otra tienda ni su segundo factor). |
| Metas de usuario | Las del sitio (`wp_N_*`) tal cual. No viajan las de otros sitios (`wp_<n>_*`), las de núcleo del sitio principal (`wp_capabilities`, `wp_user_level`, `wp_user-settings`, `wp_user-settings-time`, `wp_dashboard_quick_press_last_post_id`, `wp_persisted_preferences`, `wp_media_library_mode`), una del principal cuyo nombre ya tiene el sitio, ni `primary_blog` y `source_domain`. Las demás globales de los miembros viajan (también las de un plugin con el prefijo de la red sin número, `wp_2fa_…`, que nunca dan permisos), incluidas las que otros sitios de la red guardaron sobre ellos. |
| Roles | `wp_N_user_roles`, con sus roles y capacidades tal cual. |
| Opciones | `active_plugins` con los plugins activos en el sitio, los activados en la red y Backups (quien restaura); `upload_path` y `upload_url_path` vacías; los ajustes de red DIGITALÍSIMO que hereda, materializados (tabla siguiente). Las opciones `digitalisimo_backups_*` del sitio no viajan. |
| Archivos | Los archivos de la raíz que trae WordPress (`index.php`, `wp-*.php` del núcleo, `xmlrpc.php`, `license.txt`, `readme.html`, `wp-config-sample.php`), `wp-admin` y `wp-includes`; de `wp-content`, sólo `index.php`; mu-plugins (se avisan y se listan), idiomas, los plugins activos (del sitio y de la red), el tema y su padre, y los medios del sitio: `uploads/sites/N/` (o `blogs.dir/N/files`, o la carpeta que calcula WordPress para el sitio) pasa a `uploads/`; del sitio principal, `uploads/` sin `sites/`. Un nombre de tema o de plugin con `.`, `..` o una barra no se usa (el informe lo nombra) y cada carpeta debe quedar dentro de su raíz; la verificación rechaza cualquier entrada con `.` o `..` o fuera de las raíces de un respaldo. |

Ajustes de red que se materializan en las opciones del sitio, con la misma resolución de cada plugin
(`contenido → sitio → red → default`, ver `AGENTS.md`), leyendo sus datos aunque el plugin no esté cargado, **sólo si el
plugin está activo en el sitio** (por su archivo principal) y **por lista blanca**:

| Plugin | Opción del sitio · de la red | Cómo |
| --- | --- | --- |
| SEO | `digitalisimo_integrations_options` · la misma en `sitemeta` | Una clave que el sitio hereda (`digitalisimo_seo_network_inherit`, o `digitalisimo_module_network_inherit` para las de módulos) toma el valor de la red si la red lo tiene y está en la lista de ajustes de SEO; las de identidad del sitio nunca. Las reglas de rastreadores IA que el sitio marcó «heredar» toman la de la red. |
| Tools | `digitalisimo_tools_site_options` · `digitalisimo_tools_site_defaults` | Cada ajuste heredado (ruta privada de acceso, redirección de adjuntos, desplazamiento en móvil) toma el de la red; sin la opción del sitio, como la importaba Tools desde SEO. |
| Elements (front) | `digitalisimo_elements_front` · `digitalisimo_elements_front_defaults` | Cada interruptor que el sitio no personalizó (`own`) toma el de la red, o el neutro si la red no lo guardó; sólo booleanos. |
| Elements (Google Places) | `digitalisimo_elements_google_places_key` | La del sitio o, si no tiene, la de la red (de la cuenta de la red). |
| Hosting | `digitalisimo_hosting_options` | Con «heredar de la red», los ajustes de la lista; usuario, cuenta y clave de Namecheap son de la cuenta de la red. |
| IA Tools | `digitalisimo_ai_options` | Con «heredar de la red» (lo que hace un sitio sin opción), los ajustes de la lista y los proveedores sin su clave. |

Lo que es de la cuenta de la red (claves de API de SEO, de los proveedores IA, de Google Places, de Namecheap y de IPinfo,
la cuenta de Namecheap, la clave de IndexNow, los códigos de verificación de buscadores y los identificadores de
medición) **sólo** viaja con `network_secrets` («Copiar también las claves y cuentas de la red», `--network-secrets`): sin
marcarlo queda vacío y el informe dice cuáles. Una clave que no está en la lista (un ajuste nuevo de un plugin) no se
copia y el informe la nombra (`not_copied`): un secreto nuevo nunca viaja sin pedirlo. Un plugin inactivo en el sitio no
recibe nada de la red (el informe lo dice, `inactive`): si el nuevo dueño lo activa después, no entra en vigor, por
ejemplo, la ruta privada de acceso de la red. Las claves que el sitio guardó como suyas viajan siempre.

### Qué no lleva y por qué

- **Archivos de configuración de la raíz** (`wp-config.php`, `wp-config-*.php`, `wp-salt.php`, `.env` y todo lo que no es
  del núcleo): llevan la contraseña de la base de datos, las salts o claves de la red. El paquete trae el núcleo limpio y
  el destino conserva los suyos (una restauración nunca escribe `wp-config.php`).
- **Drop-ins** (`db.php`, `object-cache.php`, `advanced-cache.php`, `sunrise.php` y los demás): son del servidor de la red
  (HyperDB, Memcached, caché de página) y en otro servidor lo dejarían sin servicio. La pantalla y el informe los nombran;
  el destino conserva los suyos.
- **La configuración de Backups de la red** (destinos, credenciales, historial, automatizaciones): es del servidor de la
  red. Al restaurar, la del destino se conserva; antes, lo que siguiera en claro en sus ajustes pasa a su almacén cifrado,
  así que una restauración interrumpida tampoco la pierde (el diario lleva el almacén cifrado, nunca un secreto en claro).
- **Usuarios de otros sitios**, superadministradores que no son miembros (por defecto), usuarios bloqueados que no
  escribieron en el sitio, y la contraseña, el correo y las metas de los autores sin cuenta.
- **Plugins y temas que el sitio no usa**, otros archivos de la raíz y las carpetas de `wp-content` de otros plugins
  (cachés, registros), **tablas sin número de sitio que no son de WordPress** (de plugins del sitio principal o globales de
  un plugin): el paquete sólo lleva cuántos; los nombres se quedan en el historial de la red, porque pueden ser de otros
  clientes. En una red de más de 500 sitios las tablas no se listan.
- **Los `sitemeta` de otros plugins**: el informe del paquete los nombra para revisarlos.
- **Los fragmentos PHP de red de Tools**: son código firmado con las claves de cada servidor; se copian a mano en
  Herramientas → Código PHP del sitio extraído.

Viajan, con aviso en la pantalla y su lista en el informe, los **mu-plugins** de la red: pueden llevar personalizaciones
de otros sitios o claves de la agencia; revísalos antes de entregar el sitio.

### Restaurar el paquete

Sólo en un WordPress individual: una red rechaza el alcance `extracted-site` antes de tocar nada, y el alcance `site` de
la vía antigua sigue rechazado en todas partes. Pasa por lo mismo que cualquier restauración: comprobación previa (la
lista de «Restaurar» y `verify` lo dan por compatible y dicen «Origen → este sitio» con lo que se reescribirá), copia
previa, diario y recuperación. El motor de reubicación (ver «Restaurar en otro dominio») cambia el prefijo `wp_N_` por el del destino (tablas,
`wp_N_user_roles`, `wp_N_capabilities` y demás metas), las URL del sitio por las del destino y las rutas del servidor; la
extracción añade a su mismo plan los pares de la URL de los medios del sitio en la red (`…/wp-content/uploads/sites/N`, la
misma carpeta servida desde el dominio de la red y, en una red antigua con `ms_files_rewriting`, la URL `…/files`) a la
de los medios del destino, porque los archivos pasaron a `uploads/`. Después vacía el CSS generado de Elements, que se
regenera con las nuevas URL. Quedan, como en cualquier restauración en otro dominio, la columna `guid`, los correos y los
enlaces a otros sitios de la red (siguen apuntando a ellos). La respuesta y el aviso de la pantalla dicen con qué cuentas
se entra. Fuera de la red, administradores y editores recuperan `unfiltered_html` (en la red era sólo del
superadministrador): el aviso lo dice y sugiere `DISALLOW_UNFILTERED_HTML` si no se quiere. Con «Conservar las direcciones
del respaldo» (`addresses=backup`) el sitio queda con su dirección de la red y sólo cambian el prefijo, las rutas y la
carpeta de los medios.

### La vía antigua (`create_site()`)

La exportación v2 de un sitio (tablas renombradas a `wp_`, `migration.json` con el alcance `site` y
`MIGRAR-A-WORDPRESS-UNICO.txt` con pasos manuales) se conserva por compatibilidad: sólo la llama una automatización
incremental anterior a 1.0.38, sin botón, y su paquete nunca se restaura. Llevaba todos los usuarios de la red, dejaba
los roles como `wp_N_capabilities`, no movía `uploads/sites/N` ni dejaba activos los plugins de red. La vía recomendada
es «Extraer como WordPress individual».

Ensayo en WordPress Playground (`tests/playground/backups-site-extract.sh`): WordPress 7.1.4, PHP 8.5, SQLite y
DIGITALÍSIMO Elements. Una red por subdirectorios `red.test` con `/dos/` y `/tres/`, usuarios de cada tipo (dueña y
editora del sitio, un suscriptor, una administradora sólo del otro sitio, un superadministrador que escribió en el sitio
sin ser miembro y otro que no lo administra), un ajuste de red de Tools (inactivo en el sitio), una página de Elements con
fondo, imagen y enlaces y entradas creadas desde el sitio y desde el principal (las dos URL de los medios). La extracción
del sitio 2 por el formulario real de la red (superadministrador, nonce, alcance de red) y la cola por pasos tarda 37 s
(paquete de 63 MB); en otro WordPress individual (`http://127.0.0.1:<puerto>`, prefijo `wp_`, otras salts) se restaura
como archivo suelto en 36 s, con el plan de E1 (prefijo `wp_2_ → wp_`, dirección, las dos URL de los medios y la ruta
`uploads/sites/2 → uploads`; comprobación final con 0 URL y 0 rutas pendientes). 57 comprobaciones por HTTP, todas en
verde (198 s): cada miembro entra por `wp-login.php` con su contraseña de la red y abre lo que su rol permite; el
superadministrador que no es miembro no entra (viaja como autor sin contraseña) y los que no son del sitio no existen;
contenido y medios iguales, servidos desde `uploads/`; la página de Elements y su CSS regenerado con el dominio nuevo;
plugins activos; la configuración de Backups del destino intacta; ningún rastro de `red.test/dos` ni de `uploads/sites/2`.

Límites declarados: un plugin que guarda datos de cada sitio fuera de sus tablas `wp_N_*` y de `uploads/sites/N` (una
carpeta propia en `wp-content` o una tabla global) no los lleva. Una meta de usuario con el prefijo de la red y sin número
que no es de WordPress viaja con un miembro aunque fuera del sitio principal (no se distingue de una global de un plugin),
salvo que el sitio tenga una con el mismo nombre. `super_admins = none` no deja fuera a un superadministrador que es
miembro del sitio (viaja con su rol del sitio, como cualquier miembro). Al extraer el sitio principal, `SHOW TABLES`
recorre todas las tablas con el prefijo de la red. La URL `/files/` de una red con `ms_files_rewriting` se comprobó por
unidad, no en un ensayo con una red así.

## Almacenamiento local

Desde 1.0.44 los ZIP se guardan en una carpeta privada fuera de la raíz pública del servidor y de WordPress. Al abrir la
pantalla Backups, el plugin identifica la raíz web, crea la carpeta con permisos `0700` y traslada los archivos del
antiguo `wp-content/plugins/digitalisimo-backups-storage`. Los ZIP históricos conservan su nombre y siguen en el
historial; la migración compara SHA-256 antes de retirar una copia y se detiene ante archivos con el mismo nombre y
distinto contenido.

Si el hosting no permite crear una carpeta junto a la raíz web, define en `wp-config.php` una ruta absoluta privada y
escribible por PHP:

```php
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', '/ruta/privada/digitalisimo-backups' );
```

La ruta debe quedar fuera del directorio público, de `ABSPATH`, de `WP_CONTENT_DIR` y de `WP_PLUGIN_DIR` (nunca
`uploads`, `plugins` ni otra carpeta que el servidor pueda servir). Sin una ruta segura, el plugin muestra el motivo y
no crea respaldos. En WP-CLI o cron sin `DOCUMENT_ROOT`, abre antes la pantalla Backups para registrar la ruta o
define la constante.

Junto a los ZIP, el almacén guarda `jobs/` (estado y área de trabajo de cada respaldo en curso; se conservan los
últimos 20 trabajos terminados o los de 30 días), `incoming/` (las subidas sin terminar: caducan a las 24 h sin actividad y cualquier administrador las descarta) y los
cerrojos `.operation.lock` y `.jobs.lock`. Nada de eso se incluye en un respaldo ni se puede descargar. Desde
1.0.63 todo archivo del almacén es `0600` y toda carpeta `0700` (H5 del laboratorio: antes los ZIP quedaban
`0644` y sólo los protegía la carpeta). Si cambias `DIGITALISIMO_BACKUPS_STORAGE_DIR` con un trabajo activo,
ese trabajo se queda en la carpeta anterior y no continúa: vuelve a iniciar el respaldo.

La retención global (la de los respaldos manuales, de la pantalla, REST o WP-CLI: el ajuste `retention`) sólo cuenta
y borra respaldos con fila en el historial, y nunca los bloqueados. Un archivo «sin registro» (subido desde el navegador
o copiado al almacén por SSH) no ocupa un hueco ni se borra solo: antes contaba como el más nuevo y hacía borrar
respaldos reales. «Restaurar» los lista en «Archivos del almacén sin registro» con «Eliminar» (capability y nonce; sólo
nombres de respaldo del almacén sin fila: un respaldo registrado se elimina desde «Últimos respaldos»).

### Historial: copia local y copia remota

Desde 1.0.63 una fila del historial es la del ZIP del almacén (`destination` = `local`); su copia remota va en
`transfer_destination` (el destino), `transfer` (`pending`, `ok`, `failed` o `cancelled`) y `remote` (proveedor y
referencia, que nunca salen en REST ni en la pantalla). Las filas de 1.0.59 a 1.0.61 guardaban el proveedor en
`destination` tras copiar, y las anteriores no tienen `transfer` (sin `remote`, «sin identificador»): se leen igual.
Una fila es restaurable mientras su ZIP siga en el almacén (la retención de una automatización puede borrarlo y dejar
sólo la copia remota, que hoy se descarga pero no se restaura desde aquí: ver «Pendiente»). `copies` (REST y WP-CLI)
sólo lista las copias que existen: el ZIP local y la copia remota verificada o anterior sin identificador; una copia
pendiente, fallida o cancelada sólo aparece en `transfer`. Si no queda ninguna (la copia falló y la retención borró el
ZIP), la pantalla la rotula «Sin copias: …» y la fila se elimina sin pedir nada al destino.

### Archivos que Backups escribe fuera del almacén

En Webuzo con OpenLiteSpeed, un cron recarga cada 2 minutos el servidor web de **todos** los sitios si algún
`.htaccess` de una cuenta cambió de fecha, y una de esas recargas cortó una restauración en el laboratorio (H6). Por eso
Backups nunca reescribe un archivo que ya tiene el contenido correcto:

- `index.php` y `.htaccess` («Deny from all») **del almacén**: defensa por si alguna vez quedara bajo una ruta servida
  (no debería: el almacén se rechaza dentro de cualquier raíz pública). Se escriben sólo si faltan o dicen otra cosa,
  con temporal y `rename()`, en `0600`; el primer respaldo de un sitio crea el `.htaccess` una sola vez.
- Los archivos **del sitio** sólo los escribe la restauración (los del respaldo) y, al reubicar en otra ruta (E1), el
  bloque de WordPress del `.htaccess`, como al guardar los enlaces permanentes. La restauración compara cada archivo que
  ya existe con el del respaldo, byte a byte (nunca por el CRC, que se puede falsificar), y no reescribe ni cambia de
  fecha el que es idéntico: un `.htaccess`, un `web.config` o un `index.php` iguales quedan intactos. `wp-config.php`
  nunca se escribe.
- `ABSPATH/.maintenance` durante la restauración (no es un `.htaccess`: no hace recargar el servidor web); el del
  respaldo nunca se restaura.

Después de actualizar, comprueba en Backups que aparecen los archivos históricos y descarga uno con la acción
autenticada. Si una migración no termina, los originales permanecen en su ubicación anterior y el plugin muestra el
error: corrige permisos o configura otra carpeta privada antes de reintentar.

## Destinos remotos

Desde 1.0.62. Petición del propietario: DIGITALÍSIMO Backups sustituye a Backuply, así que ofrece **todos** sus
destinos. Lista de Backuply según su documentación del 2026-11-09 (backuply.com/docs, «Backup Locations»;
backuply.com/pricing; wordpress.org/plugins/backuply, versión 1.6.0 del 22-10-2026): local, FTP, FTPS, SFTP, WebDAV,
Google Drive, Microsoft OneDrive, Dropbox, Amazon S3, «S3 Compatible Storages» (DigitalOcean Spaces, Linode Object
Storage, Vultr Object Storage, Cloudflare R2 y Wasabi), pCloud y Backuply Cloud. Se incorporan todos menos **Backuply
Cloud**, que es el almacenamiento comercial de su empresa. Se añaden **Backblaze B2** (por su API compatible con S3, que
el propietario pidió aunque Backuply no lo nombra) y **otro compatible con S3** (MinIO, Ceph, Garage…).

### Contrato

`Digitalisimo_Backups_Destination` (`includes/destinations/class-destination.php`) es el contrato de todos los destinos:

| Operación | Qué garantiza |
| --- | --- |
| `put( $archivo )` | Sube por fragmentos a `<nombre>.part-<aleatorio>`, verifica la copia y sólo entonces la publica con el nombre final; una copia que no supera la verificación se retira del destino. Devuelve el recibo del historial: `provider`, `connection`, `path`/`key`/`id`, `sha256` y `size`, nunca un secreto ni una URL firmada. |
| `get( $recibo, $archivo )` | Descarga en streaming a un archivo (memoria constante), también Google Drive y OneDrive, que hasta 1.0.60 cargaban el respaldo entero en memoria al descargarlo. |
| `size( $recibo )` | Tamaño remoto o `false` si no existe (HEAD, PROPFIND, SIZE o metadatos). |
| `delete( $recibo )` | Borra; un archivo que ya no existe no es un error. |
| `list()` | Archivos de la carpeta del destino con nombre, tamaño y recibo para `get`/`delete` (restaurar desde el remoto, sincronizar y retener en el remoto lo usarán). |
| `verify( $recibo, $archivo )` | Descarga y compara tamaño y SHA-256, o usa una suma que el proveedor calcula sobre lo guardado (ver la tabla). |
| `test()` | «Probar conexión»: sube un archivo pequeño, lo verifica, lo descarga, lo busca al listar y lo borra. |
| `abandon()` | Al fallar o cancelarse un trabajo en «transfer», retira lo que su subida dejó a medias según su estado (multipart, temporal). El motor, con el registro de trabajos bloqueado, sólo aparta ese estado a `jobs/<uuid>/abandon.json`, sin ninguna llamada de red; el siguiente tick o el vigilante lo retira del destino fuera de ese cerrojo, con esperas de 20 s como máximo por petición y un solo intento. |

`Digitalisimo_Backups_Destinations` registra los tipos y las conexiones; `Digitalisimo_Backups_Http` hace las peticiones
(TLS verificado, cURL en streaming para subidas y descargas de archivos, la API HTTP de WordPress para los fragmentos);
la pantalla, el OAuth, «Probar conexión» y «Traer al almacén» viven en `trait-backups-destinations.php`. La fase
«transfer» del motor de trabajos sigue igual (reintentos, cancelación, `success` sólo tras verificar) y ahora pasa el
identificador del trabajo para que el estado reanudable viva en su área (`jobs/<uuid>/staging/transfer.json`, `0600`).

### Cada destino

| Destino | Subida | Verificación | Publicación | Reanudación |
| --- | --- | --- | --- | --- |
| SFTP | Flujo `ssh2.sftp://` al temporal | Lee la copia remota completa y compara SHA-256 | `ssh2_sftp_rename` | No (repite entera) |
| FTP/FTPS | STOR al temporal; FTPS explícito (AUTH TLS) o implícito; pasivo (EPSV/PASV, siempre al servidor de control) o activo | Descarga (RETR) y SHA-256 | RNFR/RNTO (no sobrescribe) | Sí: SIZE del temporal y APPE desde ahí |
| WebDAV | PUT del archivo entero en streaming (MKCOL crea la carpeta) | Descarga y SHA-256 | MOVE con `Overwrite: F` | No: WebDAV no define subidas por partes |
| Google Drive y OneDrive | Sesión reanudable de 8 MiB y 3,1 MiB con el nombre final (como en 1.0.47) | Descarga y SHA-256; si no coincide se borra | — | No (como antes) |
| Dropbox | `upload_session/start`, `append_v2` de 8 MiB y `finish` al temporal | `content_hash` que Dropbox calcula sobre lo guardado (documentado para comprobar la integridad) y tamaño | `move_v2` sin renombrar | Sí: sesión y desplazamiento confirmado; `incorrect_offset` sigue desde `correct_offset`; una sesión caducada (48 h) empieza de nuevo |
| Amazon S3 y compatibles | PutObject o multipart (partes iguales de 8 MiB o más, hasta 9 000) al temporal; cada petición firma el SHA-256 de su cuerpo | Descarga y SHA-256 | CopyObject (UploadPartCopy por tramos de 1 GiB si pasa de 5 GiB), tamaño comprobado y temporal borrado | Sí: identificador del multipart y partes confirmadas; `NoSuchUpload` empieza de nuevo; cancelar o fallar aborta el multipart |
| pCloud | `uploadfile` por PUT en streaming con `nopartial=1` al temporal | `checksumfile`: SHA-256 (Europa) o SHA-1 (EE. UU.) que pCloud calcula, y tamaño | `renamefile` | No |

Regiones y endpoints guiados, de la documentación de cada proveedor del 2026-11-09: las 36 regiones de Amazon S3
(docs.aws.amazon.com/general/latest/gr/s3.html), las 13 de DigitalOcean Spaces (firma con `us-east-1`, la región va en
el host), las 16 de Wasabi, Cloudflare R2 con su Account ID y su jurisdicción (predeterminada, UE o FedRAMP; firma con
`auto`), y Backblaze B2 (`s3.<región>.backblazeb2.com`), Akamai/Linode (`<región>.linodeobjects.com`) y Vultr
(`<ubicación>.vultrobjects.com`) por el endpoint que muestra su panel, del que sale la región de la firma. El estilo de
ruta es «automático» (el que recomienda el proveedor; de ruta si el bucket lleva puntos), virtual o de ruta.

### Seguridad

- TLS 1.2 o superior verificado siempre (nombre y cadena) contra las autoridades de WordPress o una **CA propia** pegada
  en la conexión (FTPS, WebDAV y «otro compatible con S3»). No verificar el certificado y usar `http://` o FTP sin cifrar
  sólo con una opción explícita que lleva su advertencia en la pantalla; sin ella, el guardado se rechaza.
- S3 sólo con Signature Version 4 y carga firmada (`x-amz-content-sha256` con el SHA-256 del cuerpo: el servicio rechaza
  una parte alterada); nunca `UNSIGNED-PAYLOAD` ni URL prefirmadas. Las firmas coinciden con las de botocore
  (`backups-destinations-protocols.php`).
- Ningún mensaje, registro ni estado lleva una cabecera de autorización, una firma, un token ni una URL con su consulta
  (`Digitalisimo_Backups_Http::redact()`); los errores sólo nombran el servidor. Los errores de credenciales, permisos,
  bucket o región son permanentes (el trabajo no los reintenta).
- FTP propio sobre sockets de PHP (no la extensión `ftp`, cuyo `ftp_ssl_connect` no verifica el certificado ni hace
  FTPS implícito): AUTH TLS antes de USER, PBSZ 0 y PROT P antes de transferir, TLS también en el canal de datos.
- SFTP con contraseña o **clave privada** (en memoria con `ssh2_auth_pubkey` o en archivos `0600` de la carpeta privada
  que se borran al momento) y huella **SHA-256** de OpenSSH (`SHA256:…`) si la extensión la calcula, además de la SHA-1
  que ya se guardaba.
- Lo que decide adónde van los bytes se valida **al usarse**, no sólo al guardar: el bucket (3 a 63 caracteres,
  minúsculas, números, puntos, guiones y guion bajo; nunca `/`, `@`, `:` ni una IP), el estilo de ruta, el endpoint de
  «otro compatible» (sin ruta, consulta ni credenciales) y la región de la firma. Un dato guardado que no lo cumpla hace
  fallar el trabajo de forma permanente sin ninguna petición: una escritura directa en la base no convierte el bucket en
  otro host.
- Ningún CR, LF ni NUL llega a un protocolo de texto: el cliente FTP rechaza cualquier comando que los lleve, y las
  carpetas y nombres los rechazan antes. Un recibo del historial sólo se usa dentro de **la carpeta de su conexión**:
  ruta dentro de la carpeta y sin `..` (SFTP, FTP, WebDAV, Dropbox, que además opera siempre por esa ruta y nunca por un
  id suelto), clave dentro del bucket y el prefijo de la conexión (S3), o archivo cuya carpeta padre es la de la conexión
  (pCloud por `stat`, OneDrive por `parentReference`). Google Drive pide el alcance `drive.file`, que ya sólo ve los
  archivos que creó la app. Un recibo ajeno falla sin llamar al destino.
- Al **servicio de metadatos de la nube** (`169.254.169.254`, `169.254.170.2`, `fd00:ec2::254`, `100.100.100.200`,
  `metadata.google.internal`…) no se envía nada: `Digitalisimo_Backups_Http` lo rechaza también desde un dato guardado,
  guardar una conexión que apunte ahí se rechaza y «Probar conexión» no se conecta.

### Credenciales y conexiones

Los secretos nuevos (clave privada y su frase de SFTP, contraseñas de FTP y WebDAV, Secret keys de S3, refresh token de
Dropbox, Client secret y token de pCloud) van al almacén cifrado de S4, guardados tal como se escriben, y su dato
adicional los ata a **todo lo que decide adónde van los bytes** de su conexión: servidor, puerto, usuario, cifrado, CA,
verificación TLS, carpeta y modo pasivo (FTP); URL, usuario, opciones TLS, `http://` y carpeta (WebDAV); Access key,
región, bucket, estilo de ruta y carpeta (Amazon S3); Access key, proveedor, endpoint, región de firma, región de
DigitalOcean o Wasabi, cuenta y jurisdicción de R2, opciones TLS, bucket, estilo y carpeta (S3 compatible); App key y
carpeta (Dropbox); Client ID, región y carpeta (pCloud). Así una escritura directa en la base de datos que cambie el
servidor, el bucket, la carpeta, desactive TLS o cambie la CA no desvía el secreto: deja de abrirse y pide volver a
escribirlo. SFTP, Google Drive y OneDrive conservan el contexto de 1.0.61 (host, puerto, usuario y huella; Client ID)
para que sus secretos sigan abriéndose al actualizar: su carpeta no está atada, pero cambiarla no cambia de servidor ni
de cuenta.

Los cinco campos que ya existían en 1.0.61 siguen en `digitalisimo_backups_credentials`; los nuevos y los de las
conexiones adicionales van en `digitalisimo_backups_credentials_more` (mismo formato, misma clave, misma ubicación,
también en el SQL de cada respaldo). 1.0.61 sólo reescribe la primera, así que volver a ella no borra los secretos nuevos.

Además de la conexión principal de cada tipo, se pueden crear **conexiones adicionales con nombre** del mismo tipo
(«Añadir otra conexión»): sus valores no secretos viven en `digitalisimo_backups_connections` y sus secretos en el
almacén con el campo `<id>/<clave>` (atados a esa conexión). Cada una es un destino propio en «Crear respaldo», las
automatizaciones, el historial, REST y WP-CLI. Borrar una conexión retira sus secretos; no se puede borrar la que es
destino predeterminado o de una automatización. Las conexiones adicionales sólo se guardan cifradas (sin AUTH_KEY y
SECURE_AUTH_KEY propias se rechazan; la principal sigue guardándose sin cifrar con aviso, la decisión de S4).

### Pantalla

«Servidores» lista las conexiones con su estado (lista, incompleta, vuelve a escribir sus credenciales), pinta para el
tipo elegido sólo sus campos, con desplegables donde hay opciones (región, proveedor, estilo de ruta, cifrado FTP,
modo pasivo, clase de almacenamiento, autenticación SFTP) y los campos que dependen de otra opción, y tiene en cada tipo
«Probar conexión» (por AJAX con lo escrito, sin guardarlo; sin JavaScript, el mismo botón por admin-post) y, en los de
OAuth, «Conectar». Dropbox se conecta con **PKCE** (S256, `token_access_type=offline`, sin App secret: la app de
Dropbox con «Allow public clients»); pCloud con su código y la región que indica al volver (sólo `api.pcloud.com` o
`eapi.pcloud.com`). Corrección: la vuelta del proveedor no trae `provider` en la URL (Google, Microsoft, Dropbox y
pCloud sólo devuelven `code` y `state`), así que el tipo y la conexión salen del estado guardado; hasta ahora esa vuelta
respondía «Proveedor no válido». En el historial, una copia remota cuyo archivo ya no está en el almacén tiene **«Traer
al almacén»**: la descarga en streaming, la compara con el SHA-256 registrado y la deja en el almacén privado para
restaurarla (también con `service_fetch()`, la misma pieza para REST y WP-CLI). «Descargar» de una copia remota pasa por
el mismo temporal verificado, que se borra también cuando la descarga falla.

- **«Conectar» de una conexión adicional.** «Añadir otra conexión» no ofrece «Conectar» (sin identificador conectaría la
  principal y su token cambiaría de cuenta en silencio): al crearla, la pantalla abre su edición con el aviso «Ahora usa
  «Conectar …» en esta conexión», y ahí el enlace lleva su identificador y el token vuelve a ella.
- **El estado OAuth pendiente** (estado aleatorio, tipo, conexión y el verificador PKCE de Dropbox) vive en la fila
  `digitalisimo_backups_vault_oauth_<usuario>`, que ningún respaldo exporta, caduca a los 15 minutos y se borra al usarse.
- **«Probar conexión» con lo escrito** usa un secreto vacío como el guardado, pero nunca hacia otro servidor: si cambió
  un dato al que está atado (servidor, puerto, usuario, URL, endpoint, bucket, huella, opciones TLS…) pide volver a
  escribirlo; cambiar sólo la carpeta no lo pide. Avisa en el mismo mensaje (también por AJAX, en `warning`) cuando el
  servidor es este mismo equipo (loopback), una red privada (10/8, 172.16/12, 192.168/16, 100.64/10, `fc00::/7`), o una
  dirección de enlace local, también si su nombre se resuelve ahí; el servicio de metadatos de la nube se rechaza.
- **Una conexión borrada.** No se borra con un respaldo en curso hacia ella. Al borrarla, el aviso cuenta las filas del
  historial que tienen ahí su copia: siguen en el destino, pero «Traer al almacén» y «Descargar» explican que la conexión
  ya no existe, y «Eliminar» quita sólo la fila, sin ninguna llamada al destino, con el aviso de que la copia remota no
  se tocó.

### Ensayos

- Servidores simulados fieles a cada API (`backups-destinations.php`): el contrato completo en los ocho tipos, fallos
  de verificación, reanudación de S3 (sólo las partes pendientes) y de Dropbox (`incorrect_offset`), 200 con `<Error>`,
  `NoSuchUpload`, abandono, orden FTPS, implícito, pasivo a la IP privada, APPE, `Overwrite: F`, pCloud por región.
- Con el motor real (`backups-destinations-screen.php`): guardado cifrado de todos los tipos, ningún secreto en el SQL
  de un respaldo, pantalla guiada, conexiones adicionales usadas por un trabajo, OAuth con PKCE, «Probar conexión»,
  transferencia S3 interrumpida que el reintento completa subiendo sólo las partes que faltaban, cancelación que aborta
  el multipart, «Traer al almacén» y descarga verificadas, y red con el almacén en `sitemeta`.
- Revisión de 1.0.62 (las mismas dos pruebas; cada caso falla con el código revisado, `4c1ade71`): bucket, estilo y
  endpoint desviados desde la base sin ninguna petición y atados al AAD (también en una conexión adicional); CR/LF y
  NUL rechazados por el cliente FTP; recibos fuera de su carpeta o bucket rechazados sin llamar a S3, FTP, WebDAV,
  Dropbox, pCloud, OneDrive ni SFTP; clasificación de direcciones internas y nada al servicio de metadatos; «Conectar»
  de una conexión nueva; el verificador PKCE fuera del SQL; cancelar sin ninguna llamada de red con el registro de
  trabajos bloqueado; la prueba que no envía la contraseña guardada a otro servidor; filas de una conexión borrada;
  «Traer al almacén» con alcance de red y cerrojo; y los secretos nuevos a salvo de una escritura de 1.0.61.
- Servidores reales (`backups-destinations-live.php`, se omite sin ellos): MinIO RELEASE.2025-09-07 (S3 compatible),
  pyftpdlib 2.2.0 (FTPS explícito) y wsgidav 4.4.0 (WebDAV sobre HTTPS), con TLS verificado por una CA de prueba y
  rechazado sin ella.
- Playground (`tests/playground/backups-destinations.sh`, WordPress 7.1.4 sobre PHP 8.5.10 con cURL 7.69.1, 386 s):
  en un sitio individual, las conexiones de MinIO y FTPS se guardaron por «Servidores» y la de WebDAV como conexión
  adicional, sin ningún secreto en claro en `wp_options`; «Probar conexión» por admin-post pasó en las tres; un respaldo
  real de 36 MB hacia cada una terminó en «success» después de «transfer», con el recibo verificado en el historial y el
  destino listándolo sin temporales; con la copia local borrada, «Traer al almacén» la devolvió verificada por SHA-256,
  la descarga al navegador fue el mismo archivo y la restauración por admin-post devolvió el sitio al respaldo; «Eliminar»
  la borró también del destino (S3 91 s, FTPS 44 s, WebDAV 94 s). En una red, lo mismo con MinIO desde la
  administración de red (84 s). Sobre el mismo código se repitió `tests/playground/backups-security.sh` (los tres
  modos, S9 incluido) sin ningún fallo. Con las correcciones de la revisión de 1.0.62 se repitió entero (342 s: sitio
  individual 232 s, red 110 s), con «Probar conexión» avisando de que el servidor de prueba es loopback y el resto igual.

Para probar con cuentas reales (Amazon S3, Backblaze B2, Cloudflare R2, Wasabi, DigitalOcean, Akamai, Vultr, Dropbox,
Google Drive, OneDrive y pCloud): crear la cuenta y la clave como indica «Cómo obtener…» en la pantalla, guardar la
conexión, «Probar conexión», y crear un respaldo hacia ella; la prueba en vivo admite cualquier S3 compatible con
`DIGI_LIVE_S3`.

Volver a 1.0.61 desde `rollback/`: los secretos nuevos y las conexiones adicionales sobreviven (1.0.61 no conoce
`digitalisimo_backups_credentials_more` ni `digitalisimo_backups_connections` y no los toca), pero su «Guardar
conexiones» reescribe `digitalisimo_backups_settings` sin los datos no secretos de los destinos nuevos y sus
automatizaciones convierten en Local los destinos que no conoce. Al volver a actualizar, esos destinos piden sus datos;
al escribirlos iguales que antes, sus secretos vuelven a abrirse (su AAD los ata a esos mismos datos).

Un recibo de antes de cambiar la carpeta de una conexión (o con una carpeta que WordPress no puede confirmar) ya no se
trae, descarga ni borra desde WordPress: la copia sigue en el destino.

Límites declarados: FTPS no reutiliza la sesión TLS en el canal de datos (PHP no lo permite): un servidor que lo exige
(vsftpd con `require_ssl_reuse=YES`, ProFTPD sin `NoSessionReuseRequired`) rechaza la transferencia y hay que
desactivar esa opción para la cuenta. SFTP, WebDAV, pCloud, Google Drive y OneDrive repiten la subida entera tras un
corte (SFTP por desplazamiento y las sesiones de Google y OneDrive quedan para T3). La verificación de S3, FTP, WebDAV,
SFTP, Google Drive y OneDrive descarga la copia: en S3 cuesta tráfico de salida. Sin cURL, WebDAV y pCloud no suben en
streaming un archivo de más de 32 MB. Dropbox necesita la Redirect URI exacta de cada sitio en la app (o una app por
sitio). Las regiones de Akamai/Linode y Vultr se firman con la primera etiqueta del endpoint (Ceph no comprueba la
región), sin ensayo con cuenta real. Un servidor FTPS implícito sólo se probó con el servidor simulado (pyftpdlib no lo
ofrece). La pantalla de restauración (rama de restauración en otro dominio) sigue listando sólo las filas locales: un
respaldo traído de un remoto se restaura por REST, WP-CLI o el caso de uso; añadir esas filas a la lista es una línea en
`restore_panel()` que se deja a esa rama.

## Cifrado, firma y archivos no confiables

Desde 1.0.59. Qué se promete y qué no:

- El cifrado protege las copias **fuera del servidor** (todos los destinos remotos y las descargas). No protege de quien
  ya controla el servidor: quien lee `wp-config.php` y la base de datos también lee la clave.
- La firma detecta cualquier cambio en un respaldo hecho con una clave que el sitio o el administrador conocen. La
  validación garantiza que un archivo no salga de la raíz de WordPress ni de sus tablas, que no agote el disco y que
  no traiga entradas ocultas. **No** garantiza que su contenido sea benigno: restaurarlo instala su código y sus datos.
- Sin la clave, un respaldo cifrado no se puede restaurar. Nadie puede recuperarlo, tampoco DIGITALÍSIMO.
- Las credenciales de los destinos viajan en cada respaldo, cifradas (ver «Credenciales de los destinos»): la base de
  datos y su SQL ya no las revelan, pero quien tiene también `wp-config.php` sí puede abrirlas.

### La clave

Hay dos formas, y la constante manda si existen las dos (las claves del llavero siguen sirviendo para restaurar):

1. **Frase** en Backups → Seguridad (de 16 a 1024 caracteres). La frase no se guarda: se deriva la clave con
   PBKDF2-SHA256 (600 000 iteraciones y un salt de 16 bytes) y se guarda envuelta (secretbox) con una clave que sale de
   `AUTH_KEY` y `SECURE_AUTH_KEY`. Sin esas dos constantes propias en `wp-config.php` no se puede guardar una frase.
2. **Constante** en `wp-config.php`, base64 de 32 bytes. Con ella el cifrado es obligatorio, porque cada respaldo
   lleva el propio `wp-config.php`:

   ```bash
   php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
   ```

   ```php
   define( 'DIGITALISIMO_BACKUPS_KEY', 'pega-aquí-el-resultado' );
   ```

   Una constante mal formada no se muestra nunca: la pestaña dice «inválida» y los respaldos fallan hasta corregirla.

Guarda la frase o la constante **fuera del sitio**. Cambiar la frase retira la clave anterior sin borrarla; «Quitar del
llavero» (con la confirmación `QUITAR`) es aparte y muestra cuántos respaldos del historial dependen de cada clave. Si
cambian `AUTH_KEY` o `SECURE_AUTH_KEY`, la entrada queda en «Vuelve a escribir la frase» y, mientras tanto, los
respaldos con cifrado activo fallan: nunca salen en claro. El llavero vive en `digitalisimo_backups_vault_keyring` (de
red en Multisite) y las opciones `digitalisimo_backups_vault_*` no entran en ningún respaldo; una restauración une los
llaveros por huella y nunca deja la configuración más débil que antes, también si el proceso muere a mitad: el diario
`.restore-journal.json` del almacén (sin la frase, la clave ni su envoltura) devuelve cada clave pidiendo la frase. Límite declarado: si vuelves a 1.0.58 desde
`rollback/` y restauras con ella, esa versión no conoce el llavero y lo pierde; los respaldos cifrados se siguen
abriendo escribiendo la frase.

### Restaurar en otro servidor

En Restaurar, elige el archivo (también los que copies a la carpeta privada del almacén, que aparecen como «sin
registro»), escribe la frase o la clave y confirma. La lista dice si un archivo está cifrado (por sus bytes, no por su
extensión), con qué clave y si este sitio la tiene; si no la tiene, pide «escribe su frase o su clave». El salt viaja en cada respaldo, así que basta con la frase. La
casilla «Usar esta frase para los respaldos nuevos» la guarda en el llavero de ese servidor.

| Archivo | Confianza | Confirmación |
| --- | --- | --- |
| Firmado con una clave del sitio o con la frase escrita | Firmado | `RESTAURAR` |
| Fila del historial creada aquí con el mismo SHA-256 (todos los respaldos desde 1.0.39) | Registrado en este sitio | `RESTAURAR` |
| Sin firma válida y sin fila (otro servidor, versiones anteriores sin registro, UpdraftPlus) | Sin firma | `RESTAURAR SIN FIRMA`, o bloqueado con «Restaurar sólo respaldos firmados» |
| Firma que no coincide, comentario sin bloque de firma o frase de otra clave | — | Se rechaza siempre |

La copia `safety-before-restore-*` nunca se cifra (es local y se recupera sin depender de una clave que el SQL
restaurado podría no traer), pero se firma si hay clave.

### Credenciales de los destinos

Desde 1.0.61. Decisión del propietario (2026-11-08): un respaldo restaura el sitio **completo**, también las
conexiones de sus destinos, así que las credenciales siguen viajando en el SQL de cada respaldo, pero cifradas.

- **Qué se guarda y dónde.** La contraseña de SFTP y el Client secret y el Refresh token de Google Drive y OneDrive
  viven en `digitalisimo_backups_credentials` (`sitemeta` en una red, `options` en un sitio individual; los campos
  nuevos de la entrega de destinos, en `digitalisimo_backups_credentials_more`, que 1.0.61 no reescribe), nunca en
  `digitalisimo_backups_settings`. Guardar «Servidores», conectar por OAuth y la migración pasan por el almacén; el
  navegador nunca recibe un valor (campo vacío y su estado en el `placeholder`). Desde la entrega de destinos
  (1.0.62) también los de los destinos nuevos y los de cada conexión adicional (campo `<id>/<clave>`), atados a
  los datos de su conexión (ver «Destinos remotos», «Credenciales y conexiones»); una clave privada de SFTP admite sus
  saltos de línea y ningún otro carácter de control.
- **Valores exactos.** Un secreto se guarda tal como se escribió (o como lo devolvió el proveedor por OAuth): sin
  las barras que WordPress añade a `$_POST` y sin saneado ni recorte, así que comillas, barras invertidas, `<…>`, `%xx`,
  espacios al borde y texto no ASCII forman parte del secreto. Sólo se rechazan, con un mensaje que nombra el campo y
  nunca su valor y sin cambiar nada, NUL, los caracteres de control (C0, DEL y C1) y lo que pase de 8192 bytes. Un campo
  vacío o sólo con espacios conserva lo guardado. Host, usuario, ruta y los demás campos que no son secretos mantienen
  su saneado. REST y WP-CLI no reciben secretos de destinos.
- **Formato v1.** `{"v":1,"items":{"<campo>":{"kw":"<huella>","c":"<base64url(nonce ‖ cifrado)>"}}}`. Cada elemento es
  AEAD XChaCha20-Poly1305-IETF con un nonce aleatorio de 24 bytes y el dato adicional
  `DGBK1/credentials|v1|<campo>|<contexto>`, donde el contexto son los datos de la conexión a la que va el secreto
  (host, puerto, usuario y huella SSH para SFTP; el Client ID para Google Drive y OneDrive), cada uno como
  `<longitud>:<valor>;`: un valor movido a otro campo o alterado no se abre, y una escritura directa en la base de datos
  que cambie el host o el Client ID no desvía el secreto, que deja de abrirse y pide escribirse (cambiarlos desde el
  formulario, en cambio, vuelve a atarlo sin escribirlo). La clave es
  `K = BLAKE2b-256("DGBK1/credentials", clave = BLAKE2b-256(AUTH_KEY ‖ "\0" ‖ SECURE_AUTH_KEY))` (separación de dominio
  distinta a la del llavero) y su huella, `kw = hex(BLAKE2b-128("DGBK1/credentials-id", clave = K))`, los 16 primeros
  caracteres. Cada elemento lleva su huella, así que unas credenciales que no se abren no impiden usar las demás. Los
  vectores de `backups-credentials.php` se contrastaron con `hashlib` y `cryptography` de Python.
- **Qué protege.** Quien sólo tiene la base de datos o el SQL de un respaldo (un volcado filtrado, una copia de
  staging, una inyección SQL de lectura, la exportación de otro plugin) ya no lee las credenciales. **No** protege de
  quien tiene también `wp-config.php`, igual que la contraseña de la base de datos: un respaldo ZIP sin cifrar lleva
  `wp-config.php`, así que quien tenga ese ZIP puede abrirlas. Un `.dgbk` lo protege todo con la frase.
- **Restaurar en el mismo servidor.** Las credenciales del respaldo mandan sobre las que tenía el servidor (restaurar
  es volver al estado del respaldo) y los destinos quedan funcionando sin volver a escribirlas.
- **Restaurar en otro servidor o dominio.** Tras el SQL, lo cifrado con las salts del origen se vuelve a cifrar con las
  de este servidor. Las del origen se leen del `wp-config.php` que va dentro del propio respaldo, ya comprobado contra su
  manifiesto: sólo `define( 'AUTH_KEY'|'SECURE_AUTH_KEY', '<literal>' )` con literales de cadena, con el tokenizador de
  PHP; el archivo nunca se ejecuta ni se incluye, y cada candidato se comprueba por su huella. Un respaldo de 1.0.60 o
  anterior trae los secretos en claro en los ajustes: se cifran al restaurarlo.
- **Cuando no se abren.** Si las salts del respaldo no se pueden leer (`getenv()`, una constante definida en otro
  archivo o con una expresión) o cambiaron `AUTH_KEY` o `SECURE_AUTH_KEY` de este servidor, cada destino afectado queda
  en «vuelve a escribir la contraseña de SFTP» o «vuelve a escribir el Client secret y conéctalo de nuevo»: lo dicen la
  pantalla, `status` de REST y WP-CLI y el aviso de la restauración, crear un respaldo hacia él responde 400 con el
  motivo y un trabajo con ese destino falla de forma permanente con el mismo mensaje y conserva el ZIP local
  verificado. Nunca se cae al texto en claro. Lo cifrado se conserva: si vuelven las salts que lo cifraron, vuelve a
  abrirse. Al escribir un valor nuevo, ese campo se cifra con las salts actuales y los demás no cambian.
- **Sin `AUTH_KEY` y `SECURE_AUTH_KEY` propias** (las mismas reglas que el llavero: al menos 16 caracteres y no el texto
  de ejemplo de WordPress) no hay con qué cifrar: se guardan en los ajustes como hasta 1.0.60, porque no guardarlas
  dejaría el sitio sin destino, y la redirección, la pantalla y `status` avisan que hay que definirlas. En cuanto
  existen, la siguiente carga del escritorio las cifra.
- **Migración.** Al cargar el escritorio, el cron o WP-CLI, antes de volcar el SQL de cada respaldo y después de cada
  restauración. Los ajustes se guardan con autoload, así que comprobarlos al cargar no cuesta ninguna consulta; una
  versión anterior (1.0.60 tras volver desde `rollback/`) los reescribe sin autoload y en claro, y la siguiente carga
  los lee, los cifra y les devuelve el autoload. Se cifra, se comprueba que vuelve a abrirse y sólo entonces se retira
  el texto en claro. La única lectura de los secretos (los ajustes en memoria) cifra también lo que aparezca en claro
  por otro camino.
- **Ningún respaldo lleva un secreto en claro si hay salts propias.** Antes del volcado se migra lo que quede en claro
  (si no se puede comprobar, la fase falla y se reintenta) y, fila a fila, una fila de `digitalisimo_backups_settings`
  de cualquier tabla de opciones sale sin secretos en claro (también la de un subsitio con unos de una versión anterior,
  que el motor no usa).
- **Red.** Las credenciales son de toda la red y sólo las lee y escribe el sitio principal (la administración de red
  corre en él). La pantalla de un subsitio no muestra «Servidores» ni anuncia los secretos de la red, y guardar o
  conectar por OAuth desde un subsitio se rechaza sin cambiar nada; su migración tampoco toca el almacén de la red. Un
  sitio individual convertido en red adopta su almacén (de sus opciones a `sitemeta`) la primera vez que se lee.
- **Olvidar.** Cada destino con algo guardado tiene en «Servidores» la casilla «Olvidar las credenciales guardadas»
  (mismo formulario, con su nonce y su capability): al guardar se retiran del almacén, también las que ya no se abren,
  y su aviso desaparece. Lo que se escriba en sus campos en ese mismo guardado sí se guarda.
- **Datos hostiles.** Un elemento del almacén cuyos datos no son texto (un objeto de un SQL no confiable o de una
  escritura directa) se lee como «vuelve a escribir»: nunca rompe los ajustes, la pantalla ni `status`. Un
  `wp-config.php` de más de 64 KiB dentro de un respaldo no se analiza (uno real ocupa de 3 a 10 KB): sus salts quedan en
  «vuelve a escribir» y el tokenizador nunca usa más de unos 18 MiB.
- **Restauración interrumpida.** El diario lleva las claves del origen cifradas con las de este servidor (nunca en
  claro), como lista con su huella (una huella toda decimal no se pierde): si el proceso muere entre el SQL y el nuevo
  cifrado, la operación siguiente lo termina. Lo que el servidor
  tenía antes de restaurar está en la copia previa.

Límites declarados: volver a 1.0.60 desde `rollback/` deja los destinos sin secreto (esa versión no conoce el almacén):
hay que volver a escribirlos. Un conjunto de UpdraftPlus no trae `wp-config.php`, así que lo cifrado con otras salts
que traiga su SQL queda en «vuelve a escribir». Unos secretos en claro en la opción de un subsitio (de una versión
anterior, sin efecto) se quedan en la base de datos de ese subsitio, aunque ningún respaldo los lleva. Una escritura
directa en la base de datos todavía puede reponer un texto cifrado antiguo del mismo campo y la misma conexión. El
parser de `wp-config.php` no reconoce la coma final en `define()`, los argumentos con nombre, `const` ni nowdoc: esas
salts quedan en «vuelve a escribir», nunca con un valor equivocado. En WordPress anterior a 6.4 (sin
`wp_set_option_autoload()`), unos ajustes que 1.0.60 dejó sin autoload y sin nada en claro se leen con una consulta en
cada carga del escritorio hasta el siguiente guardado.

### Firma del manifiesto

Con clave, el manifiesto v3 añade `"auth":{"version":1,"alg":"hmac-sha512256","key":<descriptor>,"encrypted":bool}` y
el ZIP lleva el comentario `DIGITALISIMO-AUTH/1 <key_id> <mac>`, con
`mac = HMAC-SHA512/256(K_mac, "DIGITALISIMO-AUTH/1\n" ‖ key_id ‖ "\n" ‖ bytes exactos de manifest.json)` en base64url
sin relleno. El manifiesto fija tamaño y SHA-256 de todas las entradas y la verificación rechaza entradas sobrantes o
ausentes, así que la firma cubre todo el respaldo. El formato sigue siendo `digitalisimo-backup/v3`: 1.0.58 ignora el
bloque y el comentario. Quien borra a la vez el bloque y el comentario deja un archivo sin firma, que pide
`RESTAURAR SIN FIRMA`.

### Formato DGBK v1

Archivo `digitalisimo-backup-<host>-<fecha>-<rand>.dgbk`, descargado como `application/octet-stream`. Se abre con
cualquier libsodium:

```text
"DGBK" | 0x01 | 0x00 0x00 0x00 | u32be len(H) | H (JSON UTF-8, ≤ 16 KiB) | cabecera secretstream (24 B) | C_1 … C_n
H = {"format":"digitalisimo-encrypted-backup/v1","backup_id","created_at","plugin_version","scope":"installation",
     "multisite","inner_size","inner_sha256","cipher":"secretstream-xchacha20poly1305","chunk_size":1048576,
     "file_salt":"<base64url de 32 B>","key":{"id","source":"passphrase|wp-config","kdf":{…}|null}}
```

- Claves, con `H(m, k, n) = BLAKE2b-n(m, clave k)`: `key_id = hex(H("DGBK1/key-id", K_root, 16))` (la interfaz muestra
  8 caracteres), `K_mac = H("DGBK1/manifest-mac", K_root, 32)` y `K_file = H("DGBK1/file|" ‖ file_salt, K_root, 32)`.
  Con frase, `K_root = PBKDF2-HMAC-SHA256(frase, salt, iteraciones, 32)` con los parámetros de `key.kdf`, que se
  validan antes de derivar (100 000 a 5 000 000 iteraciones y salt de 16 bytes).
- `C_i = crypto_secretstream_xchacha20poly1305_push(P_i)` con bloques de `chunk_size` bytes (potencia de 2 entre 4 KiB
  y 8 MiB; el último, de 1 a `chunk_size`). El dato adicional de `C_1` es todo el prefijo, desde `"DGBK"` hasta el
  final de `H`; los demás no llevan. Etiqueta `MESSAGE` (0) en los intermedios y `FINAL` (3) en el último, y tras
  `FINAL` debe llegar el fin del archivo.
- Tamaño exacto: `prefijo + 24 + inner_size + ceil(inner_size / chunk_size) · 17`; compararlo detecta truncamientos
  antes de descifrar. El texto descifrado es el ZIP v3 firmado (`auth.encrypted = true`), cuyo `backup_id` y huella
  deben coincidir con `H`, y cuyo SHA-256 es `inner_sha256`.
- `tests/fixtures/backups/kat-v1.dgbk` es el vector conocido: clave base64 de 32 bytes `0x42`
  (`QkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkI=`), huella `c0748d5b…`. `backups-encryption.php` lo abre y lo
  restaura en cada ejecución; los vectores de PBKDF2, de las subclaves y del MAC se contrastaron con `hashlib` y `hmac`
  de Python.
- Detalle de libsodium que una implementación propia debe respetar: tras el texto cifrado, Poly1305 se rellena con
  `(0x10 − 64 + mlen) & 0xf` bytes nulos (no hasta el múltiplo de 16 de `64 + mlen`).
- **Recuperación sin WordPress:** `scripts/dgbk-decrypt.py` es una implementación independiente en Python (HChaCha20
  propio y ChaCha20-IETF y Poly1305 de `cryptography`) que descifra y verifica un `.dgbk` con la frase o la clave y
  deja el ZIP interior: `python3 -I scripts/dgbk-decrypt.py respaldo.dgbk respaldo.zip` (pide el secreto por la
  terminal, o `--secret-file`; nunca en la línea de órdenes). Se comprobó con el vector conocido, con un archivo de
  varios bloques y con una frase con espacios, `<` y `&`.

Rendimiento: con libsodium nativo el cifrado no es el cuello de botella. Sin él, `sodium_compat` es PHP puro; la
pestaña mide la velocidad de este servidor y estima cuánto tardaría el último respaldo.

### Archivos no confiables

Antes de la copia previa y antes de cualquier consulta o escritura, en el respaldo propio, en un archivo suelto del
almacén, en un ZIP descifrado y en UpdraftPlus:

- **Archivo.** El tipo sale de los bytes mágicos (`PK` o `DGBK`), nunca de la extensión; debe ser un archivo normal
  de la raíz del almacén, no un enlace. Si tiene fila en el historial, su SHA-256 debe coincidir.
- **Entradas.** Hasta `DIGITALISIMO_BACKUPS_MAX_ENTRIES` (2 000 000); nombres de hasta 4096 bytes con segmentos de
  hasta 255; sin NUL, rutas absolutas, unidades ni segmentos `.`, `..` o vacíos; sólo las raíces `manifest.json`,
  `database.sql`, `migration.json`, `MIGRAR-A-WORDPRESS-UNICO.txt` y `wordpress/`; sin enlaces ni archivos especiales
  (atributos Unix), sin cifrado ZIP y sólo los métodos guardado y deflate; sin duplicados, y sin nombres que sólo se
  distinguen por mayúsculas si el disco de destino no las distingue. La barra invertida se convierte en un respaldo
  confiable, como siempre, y se rechaza en uno sin firma.
- **Manifiesto v3.** Hasta 256 MiB, con identificador UUID, prefijo `[A-Za-z0-9_]`, lista de tablas, URL http(s),
  tamaños enteros y SHA-256 hexadecimales; los totales deben coincidir con lo observado.
- **Espacio.** Lo que crecen los archivos sobrescritos o nuevos, más el temporal del mayor y 64 MiB, debe caber junto a
  WordPress; descifrar necesita `inner_size · 1,05 + 256 MiB` en el almacén. Si el servidor deshabilita
  `disk_free_space()`, se sigue: la restaurabilidad manda.
- **SQL.** El guardián recorre todo el SQL antes de la copia previa y vuelve a comprobar cada sentencia al importar.
  Error con el número de sentencia, su tipo y sus primeros caracteres, sin valores de filas.
