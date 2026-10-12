# DIGITALÍSIMO Migración

Compatibilidad temporal con documentos de Elementor creados con Element Pack Pro.

- **Mientras está activo**, las páginas con widgets de Element Pack se siguen viendo sin ese plugin: cada widget heredado se pinta con su equivalente de DIGITALÍSIMO Elements o de PRO Elements (adaptadores en `legacy/`).
- **Herramientas → Migrar Element Pack** (y `wp digitalisimo-migration`) muestra el inventario del sitio y convierte cada documento al formato propio, con copia del original y restauración. En Multisite, **Red → Ajustes → Migrar Element Pack** resume todos los sitios sin modificarlos.
- **Al terminar**, el contenido ya sólo usa widgets y ajustes de DIGITALÍSIMO Elements: se desinstalan Element Pack y este plugin.

Requiere Elementor y DIGITALÍSIMO Elements. Los pasos completos están en `docs/ELEMENT_PACK_RETIREMENT.md` del repositorio.
