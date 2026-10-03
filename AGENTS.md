# Digitalisimo · guía de arquitectura y avance

Este repositorio entrega módulos WordPress independientes que comparten un núcleo interno (`Digitalisimo_Core`). No existe un plugin Core que el usuario tenga que instalar: el primer módulo instalado crea el menú **Digitalisimo** y los siguientes se integran en él.

## Reglas de trabajo

- Repositorio oficial de código y Releases: `danbutanda/digitalisimo-plugin`. Los seis actualizadores nativos consultan este repositorio de forma independiente. Incrementar la versión y generar ZIP **sólo de cada plugin modificado**; nunca incrementar versiones de plugins intactos para completar una entrega. El workflow `release.yml` publica automáticamente los plugins modificados que llegan a `main`; hay que comprobar que terminó con éxito y que la Release contiene exactamente sus assets. Ver `docs/RELEASE_PROCESS.md`.

- Cada módulo se instala solo o junto a los demás; nunca debe requerir que otro módulo esté activo salvo para una mejora opcional.
- Enlaces, formularios, AJAX y redirecciones deben conservar el contexto donde inició la acción: administración de red, administración del sitio actual en Multisite o WordPress individual. Usar `admin_url( 'admin-post.php' )`/`admin_url( 'admin-ajax.php' )` para procesar solicitudes y enviar el contexto explícitamente cuando el handler ya no pueda inferir la pantalla de origen. Una tarea iniciada en un sitio sólo puede modificar datos de ese sitio, salvo que su interfaz indique y autorice expresamente el alcance de red.
- WordPress Multisite: la red define defaults y cada sitio puede heredar o personalizar. La resolución es `contenido → sitio → red → default`.

- **Paridad obligatoria sitio/red.** Toda opción nueva debe quedar operativa en las dos superficies antes de darse por terminada; una opción que sólo existe en una de ellas se considera incompleta. Los cuatro puntos son:
  1. `Digitalisimo_Integrations_Settings::defaults()` — declarar la clave con su valor neutro.
  2. `Digitalisimo_Integrations_Settings::sanitize()` — validar el envío **por sitio** (Digitalisimo → SEO).
  3. `Digitalisimo_Integrations_SEO_Suite::network_fields()` — pintar el campo en **Red → Digitalisimo → SEO**.
  4. `Digitalisimo_Integrations_SEO_Suite::save_network()` — validar el envío **de red**. Este método sanitiza por defecto con `sanitize_textarea_field()`: cualquier clave que necesite reglas propias (rutas, slugs, secretos, enumeraciones) debe declarar su caso explícito, o la red aceptará valores que el formulario del sitio rechaza.
- El checkbox «Heredar de la red» existe sólo en la administración de cada sitio. La pantalla de red define los valores que todos heredan: nunca muestra ese checkbox (usar `! is_network_admin()` cuando un mismo formulario sirve a ambas superficies).
- La lectura en runtime siempre pasa por `Digitalisimo_Integrations_SEO_Resolver::option()`, nunca por `get_option()` directo: es lo que hace efectiva la herencia. Recordar que `inherits_network()` hereda **por defecto** cuando la clave aún no figura en `digitalisimo_seo_network_inherit`, así que un valor de red recién definido se aplica de inmediato a los sitios que nunca lo personalizaron.
- Las URLs se construyen con `home_url()`/`site_url()` del sitio en curso, no con constantes ni con el dominio principal: es lo que permite que la misma opción de red funcione en subdominios y en subdirectorios.
- Las listas de URLs con destinatarios conocidos (perfiles sociales) se piden con un campo por destino, no en un textarea de texto libre: `Digitalisimo_Integrations_Social_Profiles` declara las redes y deja el textarea sólo para lo que no encaja. Un campo agrupado necesita `data-group` en su checkbox de herencia, porque el script de los ajustes sólo sabe deshabilitar un id.
- Un dato con formato propio (teléfono, horario, coordenadas) se pide con controles que impidan equivocarse, no con un campo libre que obligue a conocer la sintaxis de schema.org. `Digitalisimo_Integrations_Local_Business` es la referencia: lada desplegable más número, y horario por día que se traduce a `openingHoursSpecification`.
- Nunca duplicar title, description, canonical, robots, sitemap o schema en motores paralelos.
- Nunca crear metatags inventados para IA, contenido invisible, modificaciones automáticas de contenido ni promesas de posicionamiento/citación.
- Toda opción cuyo valor sea un archivo se pinta con el tipo `media` (`Digitalisimo_Media_Field`), nunca como input de URL a mano: el archivo se elige de la biblioteca o se arrastra, y la dirección no se escribe (el valor viaja en un input oculto). El valor se guarda como URL con `esc_url_raw()`, y la clave se declara en `Digitalisimo_Integrations_SEO_Suite::media_keys()` para que las dos superficies la sanitizen igual.
- Una pantalla con pestañas pinta **todas** en el mismo `<form>` como paneles `.digitalisimo-panel`, y la navegación `.digitalisimo-tabs` las alterna en cliente: así cambiar de pestaña no recarga ni pierde lo escrito, y un guardado envía el formulario completo. Consecuencia obligatoria: **ningún campo puede repetirse en dos pestañas**, porque dos inputs con el mismo `name` hacen que gane el último pintado y se descarte lo editado en el otro.
- Un secreto ya guardado nunca se devuelve al navegador: el input va vacío, la máscara se pone en `placeholder` y el estado se anuncia con `.digitalisimo-secret`. Un campo vacío siempre conserva el valor almacenado, nunca lo borra.
- El blindaje de fuentes nunca nombra familias, rutas, hashes ni pesos en el código: la whitelist de cada sitio sale de su propia detección más sus excepciones, y los icon fonts siguen siempre la ruta de exclusión de `Digitalisimo_Integrations_Performance_Font_Guard::icon_family()`. Una copia CSS que no supera `validate_css()` no se sirve.
- La auditoría de calidad frontend sólo informa: el navegador recoge hechos y `Digitalisimo_Integrations_Quality_Rules` decide su estado. Colores, encabezados, textos de enlace, destinos y jerarquía nunca se corrigen solos; sólo se aplica lo determinista que el administrador pide (alt="" en imágenes que clasifica como decorativas, área táctil en los selectores que enumera, un handle que añade a la lista diferible de su sitio). Ninguna regla nombra URLs, colores, IDs de Elementor ni páginas concretas.
- Schema se publica como **un solo `@graph` por URL** (`Digitalisimo_Integrations_Schema_Graph`): Organization (LocalBusiness sólo con datos reales de negocio) + WebSite + WebPage, BreadcrumbList sólo en páginas internas y la entidad del contenido, relacionados por `@id`. Nunca se imprime otro bloque JSON-LD propio. Todo `@type` sale del vocabulario oficial (`includes/schema/schema-vocabulary.php`, regenerado con `node scripts/schema-vocabulary.mjs`); un valor libre se valida con `Digitalisimo_Integrations_Schema_Vocabulary`. La unificación del JSON-LD de otros plugins retira duplicados, tipos inexistentes y preguntas FAQ no visibles, y deja intacto lo que no puede leer.
- Configuraciones sensibles se protegen con capability, nonce y sanitización. Las tareas remotas se ejecutan en cola, no durante el guardado.
- Al incrementar una versión, mover el ZIP anterior a `rollback/`, actualizar `tests/validate-suite.mjs`, crear el nuevo ZIP y comprobarlo con `unzip -t`.
- No borrar fuentes ni paquetes históricos: usar `rollback/`.

## Módulos

### Digitalisimo SEO

Responsable de las señales SEO públicas: title, description, canonical, robots/noindex, sitemap, schema, keywords, clusters, SEO local, social, importación Yoast/Rank Math y SEO AI. Todas las auditorías, pruebas y análisis del plugin se reúnen en SEO → Auditoría (`Digitalisimo_Integrations_SEO_Audit_Hub`).

SEO AI pertenece aquí porque regula descubrimiento, rastreo, interpretación y citabilidad externa. Incluye crawlers, robots.txt, llms.txt, IndexNow, entidades, diagnóstico, auditoría y referencias de tráfico IA.

### Digitalisimo AI y Chatbot

Responsable de la IA interna: proveedores (OpenAI, Anthropic, Google, DeepSeek y xAI), perfiles de uso, chatbot, asistentes editoriales, automatizaciones, imágenes y base de conocimiento del sitio.

La base de conocimiento es RAG, no entrenamiento de modelos: sólo indexa contenido público permitido y lo usa como contexto. No afecta la indexación externa ni transfiere contenido a un proveedor hasta que una función IA configurada por el administrador realiza una consulta.

### Digitalisimo Ecommerce

Responsable de integraciones WooCommerce, productos, carrito, automatizaciones e IA ecommerce. Puede consumir la API interna de conocimiento si AI está activo, sin crear otro índice.

### Digitalisimo Geolocalización

Responsable de ubicación, IPInfo, ubicación administrativa y señales locales reutilizables por SEO Local.

### Digitalisimo Hosting

Responsable de búsqueda de dominios, configuración Namecheap, shortcodes/formularios, precios y el flujo de compra de dominio. Se integra con Ecommerce cuando este existe.

### DIGITALÍSIMO Tools

Responsable de herramientas de administración que no son SEO: plantillas Elementor de red, WebP y **Ajustes del sitio** (ruta privada de acceso, redirección de adjuntos y bloqueo del desplazamiento horizontal en móvil). Esos ajustes viven en `digitalisimo_tools_site_options` (sitio) y `digitalisimo_tools_site_defaults` (red), se importan una vez desde las claves que tenía SEO y, mientras Tools no esté activo, SEO los sigue aplicando (`Digitalisimo_Integrations_Settings::tools_handles_site_options()`) para no abrir `wp-login.php` al actualizar; nunca se ejecutan en los dos plugins a la vez.

## Estado y prioridad

La matriz operativa está en `docs/PLUGIN_ROADMAP.md`. Antes de empezar un lote, actualizar su estado; al terminar, registrar versión, validación y paquete generado.
