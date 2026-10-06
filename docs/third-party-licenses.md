# Licencias de referencia para DIGITALÍSIMO Elements

La copia local `digitalisimo-elements/bdthemes-element-pack/` se utiliza sólo para análisis. Su cabecera declara `GPL3` y contiene un archivo `LICENSE` GPLv3. La presencia de esos archivos permite estudiar el código, pero no autoriza asumir automáticamente la procedencia ni licencia individual de todos los iconos, imágenes, fuentes, plantillas y bibliotecas incluidas.

| Componente | Estado | Acción antes de publicarlo |
| --- | --- | --- |
| Código PHP de Element Pack | Referencia local; no incluido en runtime ni ZIP | Si se reutiliza código directamente, registrar archivo, procedencia, avisos de copyright, cambios y fuente correspondiente. |
| Código de PRO Elements ya integrado | Distribuido como derivado GPLv3 | Mantener `COPYING`, `license.txt` y `DIGITALISIMO_CHANGES.md`. |
| UIkit de Element Pack | Referencia; exclusión prevista | Reconstruir sólo las funciones necesarias con código propio. |
| Swiper | Candidato a motor compartido | Confirmar versión, licencia, fuente y compatibilidad antes de reutilizar un archivo externo. |
| Iconos, imágenes, fuentes, plantillas y otros assets | Licencia individual pendiente de auditar | No trasladar a un widget ni a un ZIP hasta documentar origen y permiso de distribución. |
| Administración, activación y licenciamiento de Element Pack | Excluidos | No copiar ni ejecutar. |

## Primer componente reconstruido: Animated Link

`digitalisimo-elements/modules/digitalisimo-widgets/class-animated-link.php` reconstruye controles y salida con clases de Elementor. Sus tres trazos SVG decorativos se adaptaron del módulo `modules/animated-link/widgets/animated-link.php` de Element Pack Pro 9.9.1. `digitalisimo-elements/assets/css/animated-link.css` adapta únicamente las reglas del archivo `assets/css/ep-animated-link.css` de ese módulo: cambia el prefijo de clases y keyframes para evitar colisiones, añade foco visible y respeta `prefers-reduced-motion`. La copia de referencia declara GPLv3 en `LICENSE` y `bdthemes-element-pack.php`; DIGITALÍSIMO Elements distribuye `COPYING` y conserva esta atribución y descripción de modificaciones. No se copiaron el loader, UIkit, panel, código de licencia ni los demás assets.

El inventario estático no sustituye la revisión de licencias archivo por archivo. Cada nueva dependencia publicada debe añadirse aquí con versión, autor, URL de origen, licencia, ruta distribuida y archivo de aviso cuando corresponda.
