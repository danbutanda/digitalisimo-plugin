# Revisión de DIGITALÍSIMO Elements

Base examinada: asset oficial `pro-elements.zip` de [PRO Elements 4.3.0](https://github.com/proelements/proelements/releases/tag/v4.3.0), SHA-256 `31ee1897f83ff8893ea5d4f76f10ae6c0b9033e86e755286909c52f6ce1f6bb8` (coincide con el digest publicado por GitHub). El ZIP contiene 1316 archivos, incluidos 860 PHP. La versión derivada conserva el motor y los identificadores internos de Elementor Pro para no romper documentos existentes.

## Dependencias y alcance

- Necesita el plugin **Elementor gratuito** activo antes de cargar. La base requiere Elementor 4.0 o posterior y recomienda 4.3; WordPress 6.8+ y PHP 7.4+. Si Elementor Pro u otro derivado ya definió `ELEMENTOR_PRO_VERSION`, este plugin no carga sus módulos y muestra un aviso de conflicto.
- La base incluye widgets, Theme Builder, formularios, popups, contenido dinámico, WooCommerce, fuentes e iconos personalizados, CSS y código personalizados, loops y otras funciones locales. La disponibilidad de cada módulo también depende de Elementor, WooCommerce y otras integraciones instaladas.
- **No equivale a una suscripción de Elementor Pro.** La biblioteca en la nube, plantillas remotas, soporte, IA, pagos y otros servicios de Elementor pueden requerir cuenta, conexión o permisos del proveedor. No se modifican sus APIs ni se promete acceso a ellos.
- En la portada pública de `digitalisimo.mx` observada el 3 de octubre de 2026 se sirvieron assets de Elementor gratuito 4.3.3 y Elementor Pro 4.3.1. Ese dato de HTML público no sustituye la lista de plugins activos del panel; antes de activar el derivado hay que verificar y desactivar Elementor Pro en el sitio o red correspondiente, con respaldo y prueba de diseño.

## Marca y licencia

- El plugin aparece como **DIGITALÍSIMO Elements** y su pantalla informativa usa los logos negro/blanco y el ícono D obtenidos de `digitalisimo.mx` en la fecha indicada. La marca de Elementor dentro del editor gratuito permanece intacta.
- `license.txt` conserva los créditos de Elementor Ltd. y PRO Elements. `COPYING` incluye GPLv3; `DIGITALISIMO_CHANGES.md` distingue las modificaciones de DIGITALÍSIMO. No se atribuye la creación del código original a DIGITALÍSIMO ni se presenta como producto oficial de Elementor.
- El actualizador de PRO Elements se desactiva en este derivado y se usa un `Update URI` propio que consulta los assets del repositorio DIGITALÍSIMO, para que una actualización ascendente no reemplace los cambios de marca.

## Instalación y comprobación

El ZIP `digitalisimo-elements-4.3.0.2.zip` se instala como plugin independiente. Los catálogos de SEO y Tools lo muestran cuando está publicado. No requiere que ninguno de esos dos módulos esté activo. La primera Release 4.3.0.1 omitió los archivos `vendor/` por una regla de Git y no debe instalarse. No activar junto a Elementor Pro ni otra copia de PRO Elements. La instalación o activación en el sitio real queda fuera de esta entrega hasta hacer una prueba de compatibilidad y respaldo.
