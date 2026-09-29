# DIGITALÍSIMO Tools

`DIGITALÍSIMO Tools` es un plugin independiente para herramientas internas de una red WordPress. Su primer módulo es **Plantillas Elementor de Red**.

## Uso

1. Activa el plugin para la red.
2. En **Administrador de la red → DIGITALÍSIMO Tools**, elige el sitio maestro.
3. Agrega una Saved Template de Elementor, asígnale un identificador global inmutable como `portfolio-coahuila-1000` y define el alcance.
4. Usa **Sincronizar ahora**. Las tareas se encolan con WP-Cron; una plantilla padre espera a que sus dependencias estén en cada sitio destino.

La edición sucede siempre en el sitio maestro. Una copia bloqueada muestra un aviso y no permite abrir el editor Elementor a administradores locales; un superadministrador conserva acceso.

## Arquitectura

- `Registry` guarda en opciones de red el UUID, maestro, destino, colección, alcance, bloqueo y último resultado.
- `Id_Mapper` marca cada copia local con `_digitalisimo_tools_network_template_uuid`, más los metadatos de procedencia. Nunca usa el título ni el ID maestro como identidad.
- `Dependency_Resolver` recorre recursivamente el JSON de `_elementor_data`. Busca claves de referencia de plantilla y referencias en shortcodes para poder añadir futuros detectores sin depender de un solo widget.
- `Sync` resuelve primero las dependencias, protege contra ciclos y reentradas, y usa `switch_to_blog()` con `try/finally` para restaurar siempre el contexto.
- `Elementor_Adapter` exporta sólo los metadatos de documento necesarios, remapea IDs internos al destino, limpia caché y llama a la API pública disponible de Elementor para limpiar CSS.

El plugin no hace consultas, JavaScript, REST, iframes, shortcodes ni solicitudes entre sitios durante el frontend. Los medios se preservan por URL; la duplicación de medios queda fuera de esta versión.

## Compatibilidad y pruebas pendientes

El módulo no ejecuta sincronizaciones si no está activo Elementor y no muestra su pantalla en una instalación que no sea Multisite. La versión inicial debe validarse en una red de pruebas con la versión real de Elementor antes de activar sincronización automática: plantilla simple, datos responsive, medios, widget Template, Tabs/Nested Tabs, dependencias anidadas, borrado de una copia, cambio de título, exclusiones, bloqueo y regeneración de CSS.
