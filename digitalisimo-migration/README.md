# DIGITALÍSIMO Migración

Migra un sitio o una red a DIGITALÍSIMO Elements con un solo botón, y mantiene visibles mientras tanto los documentos creados con Element Pack.

- **Migrar**: en **Herramientas → Migrar a DIGITALÍSIMO Elements** (y `wp digitalisimo-migration --run`) desactiva en el sitio Element Pack (Pro o Lite) con su Addon, Elementor Pro, PRO Elements y Elementor, y al final Prime Slider; convierte al formato propio el contenido de Element Pack y de Prime Slider (también sus revisiones y lo conservado en marcadores de kits), sus extensiones y la plataforma de Element Pack (Theme Builder, fuentes, iconos, Mega Menu, kit, código global, barra lateral, muestras y etiquetas dinámicas), y el de los widgets fusionados de DIGITALÍSIMO Elements; vacía las cachés y deja un informe con lo que queda para una persona. Las plantillas del Theme Builder se publican solas cuando Element Pack ya no está, así que ninguna página se queda sin cabecera. Nunca borra plugins: los deja desactivados para borrarlos cuando el sitio esté comprobado. En Multisite, **Red → Ajustes → Migrar a DIGITALÍSIMO Elements → Migrar toda la red** (y `wp digitalisimo-migration --run --network`) hace lo mismo en todos los sitios, cada uno con sus propios plugins, y sólo desactiva Element Pack en la red cuando cada sitio preparó sus plantillas.
- **Restaurar**: `wp digitalisimo-migration --revert` deshace la plataforma y los documentos, sin pisar lo editado después (`--force` lo restaura igualmente).
- **Mientras dura la migración**, las páginas con widgets de Element Pack se siguen viendo sin ese plugin: cada widget heredado se pinta con su equivalente de DIGITALÍSIMO Elements o de PRO Elements (adaptadores en `legacy/`).
- **Al terminar**, el contenido ya sólo usa widgets y ajustes de DIGITALÍSIMO Elements: se borran Element Pack, su Addon, Prime Slider, Elementor, Elementor Pro y este plugin (si se quiere volver a Element Pack, hay que revertir antes de borrarlo).

Un plugin más que DIGITALÍSIMO Elements sustituya se añade a `Retire::PLUGINS` (`legacy/class-retire.php`) o con el filtro `digitalisimo_migration_retired_plugins`.

Requiere DIGITALÍSIMO Elements 4.3.0.170 o posterior (que ya incluye Elementor). El detalle está en `docs/ELEMENT_PACK_RETIREMENT.md` del repositorio.
