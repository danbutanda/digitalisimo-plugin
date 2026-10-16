# DIGITALÍSIMO Migración

Migra un sitio o una red a DIGITALÍSIMO Elements con un solo botón, y mantiene visibles mientras tanto los documentos creados con Element Pack Pro.

- **Migrar**: en **Herramientas → Migrar a DIGITALÍSIMO Elements** (y `wp digitalisimo-migration --run`) desactiva en el sitio Element Pack, Elementor Pro, PRO Elements y Elementor, convierte al formato propio el contenido de Element Pack (también sus revisiones) y el de los widgets fusionados de DIGITALÍSIMO Elements, vacía las cachés y deja un informe con lo que queda para una persona. Nunca borra plugins: los deja desactivados para borrarlos cuando el sitio esté comprobado. En Multisite, **Red → Ajustes → Migrar a DIGITALÍSIMO Elements → Migrar toda la red** (y `wp digitalisimo-migration --run --network`) hace lo mismo en todos los sitios, cada uno con sus propios plugins.
- **Mientras dura la migración**, las páginas con widgets de Element Pack se siguen viendo sin ese plugin: cada widget heredado se pinta con su equivalente de DIGITALÍSIMO Elements o de PRO Elements (adaptadores en `legacy/`).
- **Al terminar**, el contenido ya sólo usa widgets y ajustes de DIGITALÍSIMO Elements: se borran Element Pack, Elementor, Elementor Pro y este plugin.

Un plugin más que DIGITALÍSIMO Elements sustituya se añade a `Retire::PLUGINS` (`legacy/class-retire.php`) o con el filtro `digitalisimo_migration_retired_plugins`.

Requiere DIGITALÍSIMO Elements (que ya incluye Elementor). El detalle está en `docs/ELEMENT_PACK_RETIREMENT.md` del repositorio.
