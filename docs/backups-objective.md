# Objetivo de DIGITALÍSIMO Backups

La especificación recibida define un respaldo como **la instalación completa de WordPress, tratada como una unidad indivisible**: archivos de Core, `wp-content`, configuración y base de datos de la instalación. En Multisite, un respaldo completo incluye la red entera, sus sitios y tablas. Un archivo parcial no puede llamarse respaldo completo ni alcanzar `SUCCESS`.

Este objetivo permanece abierto hasta demostrar respaldo, verificación, transferencia, restauración y migración completos, incluso a otro servidor o dominio, sin depender del hosting de origen. La implementación debe preservar los datos existentes y las funciones utilizables de la versión actual mientras reemplaza gradualmente los caminos inseguros.

## Condiciones de aceptación

1. Un trabajo persistente avanza por `QUEUED`, `RUNNING`, `VERIFYING` y, si aplica, `TRANSFERRING`, hasta `SUCCESS`, `FAILED` o `CANCELLED`. Sólo llega a `SUCCESS` después de verificar todas las partes requeridas en todos los destinos exigidos.
2. El paquete incluye un manifiesto versionado, inventario íntegro y sumas criptográficas comprobables. Una copia incompleta, ilegible, truncada, alterada o sin piezas requeridas se rechaza antes de tocar el sitio de destino.
3. La restauración completa es portable entre servidor, ruta y dominio; conserva datos serializados, aplica cambios de URL con seguridad y permite recuperación de fallos. La prueba decisiva es una restauración extremo a extremo en otro entorno.
4. Automatización, incrementales dependientes de una base válida, respaldo previo a actualizaciones, retención consciente de cadenas, varios destinos, reintentos, cifrado, SFTP real y operación en instalaciones grandes y Multisite comparten un mismo motor.
5. Interfaz, REST y WP-CLI muestran el mismo estado real; registros, alertas y comprobaciones de restaurabilidad permiten diagnosticar fallos. Una instalación WordPress individual y una red deben funcionar sin otro plugin Digitalisimo activo.
6. Las 30 condiciones de la sección 51 de la especificación se verifican con pruebas automatizadas, inyección de fallos y ensayos reales. Ningún ZIP por sí solo prueba que el objetivo terminó.

## Prioridad de entrega

Integridad de datos → restaurabilidad → seguridad → portabilidad → fiabilidad → recuperación ante fallos → automatización → compatibilidad → rendimiento → experiencia de usuario.

Las funciones heredadas de copia de base de datos, copia por sitio y restauración Updraft siguen inventariadas, pero no satisfacen por sí mismas el requisito de respaldo completo. Su compatibilidad debe evaluarse y señalizarse explícitamente; no se las debe presentar como verificación completa.
