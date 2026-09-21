# Inspector del front SEO

**SEO Front** es la última pestaña de **Digitalisimo → SEO**. Sirve para comprobar una URL ya publicada del sitio, usando la respuesta HTML que WordPress recibe sin cookies ni sesión de administrador. No guarda datos, no modifica contenido y no cambia la configuración SEO.

Se puede inspeccionar cualquier URL `http` o `https` del mismo host del sitio. El inspector sigue hasta cinco redirecciones que se mantengan en ese host y muestra el estado HTTP final. Esta restricción evita que el administrador use la herramienta para solicitar direcciones internas o externas desde el servidor. Si el servidor no puede validar el certificado de su propio dominio, reintenta únicamente esa URL propia y muestra un aviso para revisar DNS, SNI o la cadena de certificados desde el hosting. Ese aviso no confirma por sí solo un fallo para visitantes externos.

El informe separa la respuesta técnica en cuatro grupos: SEO del HTML (`title`, description, keywords, canonical, robots, hreflang y paginación), metadatos Open Graph/X, bloques Schema JSON-LD y redirecciones. Además conserva un desplegable con el `head` recibido, escapado para que se pueda revisar sin ejecutar código del sitio inspeccionado.

Al final compara las señales observadas con las opciones efectivas de Digitalisimo. Advierte sobre `title`, description o canonical duplicados, IDs de Schema repetidos, Schema JSON-LD inválido y señales globales activas que no aparecieron en la respuesta. El resultado refleja una solicitud desde el propio servidor: una CDN, caché por ubicación o WAF puede entregar una variante distinta a rastreadores externos.
