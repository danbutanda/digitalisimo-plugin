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

## Segundo componente reconstruido: Fancy List

`digitalisimo-elements/modules/digitalisimo-widgets/class-fancy-list.php` y `digitalisimo-elements/assets/css/fancy-list.css` se escribieron para reproducir la función básica del módulo `modules/fancy-list/widgets/fancy-list.php` de Element Pack Pro 9.9.1 (GPLv3), sin copiar su clase, trait de controles ni CSS, y sin incluir imágenes, iconos o librerías de la referencia. Los nombres de varios controles se conservan para facilitar una futura conversión explícita de documentos; aún no se registra el identificador antiguo.

## Tercer componente reconstruido: Document Viewer

`digitalisimo-elements/modules/digitalisimo-widgets/class-document-viewer.php` y `digitalisimo-elements/assets/css/document-viewer.css` se escribieron como reconstrucción funcional del módulo `modules/document-viewer/widgets/document-viewer.php` de Element Pack Pro 9.9.1 (GPLv3). No se incluyen sus clases, recursos gráficos ni librerías. El modo opcional de Google Docs carga un servicio externo elegido por el administrador; no incorpora su código al paquete.

## Cuarto componente reconstruido: Brand Carousel

`class-brand-carousel.php`, `class-carousel-engine.php`, `brand-carousel.css`, `carousel-engine.css` y `carousel-engine.js` son implementación propia de la función principal del módulo `modules/brand-carousel/widgets/brand-carousel.php` de Element Pack Pro 9.9.1 (GPLv3). No se copia la clase, los estilos, las bibliotecas UIkit/Swiper, los iconos ni las imágenes de la referencia. La mención del módulo original identifica la procedencia funcional; la equivalencia completa permanece pendiente.

## Quinto componente reconstruido: Logo Carousel

`class-logo-carousel.php` y `logo-carousel.css` son implementación propia de selección y presentación de logotipos inspirada en `modules/logo-carousel/widgets/logo-carousel.php` de Element Pack Pro 9.9.1 (GPLv3). Reutilizan sólo el motor propio descrito arriba. No se incluyen los SVG de ejemplo, UIkit, Swiper, Tippy ni archivos de la referencia.

## Sexto componente reconstruido: Advanced Heading

`class-advanced-heading.php` y `advanced-heading.css` son una implementación propia de la función básica de `modules/advanced-heading/widgets/advanced-heading.php` de Element Pack Pro 9.9.1 (GPLv3). Sólo se conserva el nombre de controles funcionales que facilitaría una conversión posterior; no se copian su clase, CSS, iconos ni efectos avanzados. El ID antiguo no se registra y la equivalencia visual está pendiente.

## Séptimo componente reconstruido: Brand Grid

`class-brand-grid.php` y `brand-grid.css` son implementación propia de la función principal de `modules/brand-grid/widgets/brand-grid.php` de Element Pack Pro 9.9.1 (GPLv3). No se copian su clase, traits, CSS, iconos ni recursos. La cuadrícula utiliza elementos semánticos y CSS nativo; el ID anterior continúa sin registrarse.

## Octavo componente reconstruido: Logo Grid

`class-logo-grid.php` y `logo-grid.css` son implementación propia inspirada en `modules/logo-grid/widgets/logo-grid.php` de Element Pack Pro 9.9.1 (GPLv3). No se incluyen su clase, traits, estilos, máscaras, Tippy, Popper ni recursos gráficos. El ID anterior permanece sin registrar mientras se comprueba la equivalencia funcional y visual.

## Noveno componente reconstruido: Scroll Button

`class-scroll-button.php`, `scroll-button.css` y `scroll-button.js` son implementación propia de la función principal de `modules/scroll-button/widgets/scroll-button.php` de Element Pack Pro 9.9.1 (GPLv3). No se incluyen su clase, traits, CSS, JavaScript ni efectos; el nuevo widget usa un enlace nativo y no registra el ID anterior.

## Décimo componente reconstruido: Accordion

`class-accordion.php` y `accordion.css` son implementación propia inspirada en `modules/accordion/widgets/accordion.php` de Element Pack Pro 9.9.1 (GPLv3). Utilizan elementos HTML de divulgación nativos; no incorporan clases, traits, estilos, scripts ni UIkit del original. El ID antiguo continúa libre mientras se verifica la paridad.

## Undécimo componente reconstruido: Advanced Button

`class-advanced-button.php` y `advanced-button.css` son implementación propia de un botón con icono y distintivo inspirada en `modules/advanced-button/widgets/advanced-button.php` de Element Pack Pro 9.9.1 (GPLv3). Sus nueve efectos se expresan mediante CSS nuevo y ligero. No se copia la clase, su hoja CSS, UIkit ni otros recursos de la referencia. El ID antiguo permanece libre hasta comprobar paridad funcional y visual.

## Duodécimo componente reconstruido: Advanced Divider

`class-advanced-divider.php` y `advanced-divider.css` implementan formas decorativas propias tras estudiar `modules/advanced-divider/widgets/advanced-divider.php` de Element Pack Pro 9.9.1 (GPLv3). No distribuyen sus SVG, hoja CSS, JavaScript, UIkit ni infraestructura. La imagen opcional se toma de la biblioteca de Medios del sitio. El ID antiguo permanece libre y la equivalencia completa está pendiente.

## Extensión editorial reconstruida: Duplicator

`digitalisimo-elements/digitalisimo-duplicator.php` es implementación propia de la función editorial observada en `includes/class-duplicator.php` de Element Pack Pro 9.9.1 (GPLv3). No copia su clase, consultas SQL, loader, ajustes ni otros archivos. Usa las APIs de WordPress, restringe tipos y permisos, y no incorpora recursos externos. Conserva datos de Elementor sin copiar sus cachés temporales.

## Decimotercer componente reconstruido: Advanced Icon Box

`class-advanced-icon-box.php` y `advanced-icon-box.css` son una implementación propia de la función principal de `modules/advanced-icon-box/widgets/advanced-icon-box.php` de Element Pack Pro 9.9.1 (GPLv3). No se copiaron la clase, los efectos, CSS ni JavaScript de referencia. El widget nuevo no registra el ID anterior y mantiene pendientes los controles avanzados y la equivalencia visual.

## Decimocuarto componente reconstruido: Animated Heading

`class-animated-heading.php`, `animated-heading.css` y `animated-heading.js` son una implementación propia de rotación progresiva tras estudiar `modules/animated-heading/widgets/animated-heading.php` de Element Pack Pro 9.9.1 (GPLv3). No se copiaron su clase, CSS, JavaScript, UIkit, Typed.js, Morphext ni GSAP. El ID antiguo sigue libre y la paridad de sus modos avanzados está pendiente.
