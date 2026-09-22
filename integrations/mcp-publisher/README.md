# DIGITALÍSIMO WP: puente MCP local

Depende de DIGITALÍSIMO SEO >= 1.0.69 instalado en cada WordPress. No modifica el plugin WordPress ni realiza llamadas a la API de OpenAI. Solo recibe texto/medios ya preparados y crea **borradores**.

## Instalar

Requiere Node.js 20+ y un cliente MCP por stdio, como VS Code.

~~~bash
cd integrations/mcp-publisher
npm install
npm test
~~~

Crea el directorio privado de configuración FUERA del repositorio. En macOS:

~~~bash
mkdir -p "$HOME/.config/digitalisimo"
cp sites.example.json "$HOME/.config/digitalisimo/sites.json"
chmod 600 "$HOME/.config/digitalisimo/sites.json"
~~~

Edita ese archivo privado para introducir URL, nombre de usuario y NUEVA contraseña de aplicación por instalación autorizada. No subas el archivo a GitHub ni compartas contraseñas en un chat. Para Multisite, configura cada subdominio o subdirectorio como un alias independiente. El plugin SEO debe estar activo en el sitio específico.

## Configurar en VS Code

En la configuración MCP de usuario o del proyecto, utiliza este ejemplo, sustituyendo las rutas por las absolutas de tu Mac:

~~~json
{
  "servers": {
    "digitalisimo-wp": {
      "type": "stdio",
      "command": "node",
      "args": ["/RUTA/ABSOLUTA/digitalisimo-plugin/integrations/mcp-publisher/server.mjs"],
      "env": {
        "DIGITALISIMO_SITES_FILE": "/Users/TU_USUARIO/.config/digitalisimo/sites.json",
        "DIGITALISIMO_IMAGE_DIR": "/Users/TU_USUARIO/Pictures/digitalisimo-wordpress"
      }
    }
  }
}
~~~

Crea el directorio de imágenes antes de probar. No incluyas contraseñas en el archivo MCP, especialmente si está dentro de un repositorio Git.

## Herramientas

- list_sites: aliases autorizados sin revelar contraseñas.
- get_site: comprobar permisos y SEO activo.
- upload_local_image: imagen local dentro de DIGITALISIMO_IMAGE_DIR; máximo 8 MB, PNG/JPEG/WEBP/GIF.
- upload_image: imagen base64 real (máximo 8 MB); no inventar bytes.
- create_draft: artículo o página WordPress con title, content HTML, seo_title, meta_description, primary_keyword, secondary_keywords, featured_media (ID devuelto al subir la imagen), image_alt, categorías y pilar opcionales. Nunca publica automáticamente.

**El puente es local/stdio. No es todavía un complemento privado instalable en ChatGPT**: un servidor remoto Streamable HTTP con autenticación apropiada y permisos de Apps en ChatGPT es un proyecto posterior. Los archivos generados dentro de ChatGPT tampoco se transfieren automáticamente a tu Mac: guarda primero la imagen en el directorio autorizado.

## Probar la imagen directamente en WordPress

Desde Terminal, con un PNG REAL en tu escritorio:

~~~bash
curl -i -u "digitalisimo" -X POST \
  "https://digitalisimo.mx/wp-json/wp/v2/media" \
  -H "Content-Type: image/png" \
  -H 'Content-Disposition: attachment; filename="prueba-digitalisimo.png"' \
  --data-binary @"$HOME/Desktop/prueba-digitalisimo.png"
~~~

Pega la contraseña de aplicación actual cuando curl la solicite. La respuesta mostrará el ID del medio: comprueba que aparece en Biblioteca de Medios y usa ese ID como featured_media al crear el siguiente borrador. Si es JPG adapta archivo y MIME. La cuenta requiere permiso upload_files.

## Seguridad y reversión

Todas las solicitudes al WordPress seleccionado utilizan su contraseña de aplicación por HTTPS, sin seguir redirecciones de autenticación. El puente no admite URLs de destino elegidas libremente por el modelo. Las fotos locales solo se leen en el directorio aprobado. Para desactivar, retira la configuración MCP de VS Code: **no necesitas reinstalar DIGITALÍSIMO SEO**. Código anterior guardado en rama backup/pre-mcp-bridge-2026-09-21.
