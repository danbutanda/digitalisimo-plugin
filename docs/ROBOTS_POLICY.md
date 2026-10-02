# Política de robots de DIGITALÍSIMO SEO

La pestaña **SEO → Robots** muestra el archivo efectivo de cada sitio, permite añadir reglas manuales y controla **Google-Extended** por separado. En Multisite, la red define el valor predeterminado y cada sitio puede heredarlo o personalizarlo. El sitemap se publica con `home_url( '/sitemap.xml' )` del sitio actual.

La salida normal usa las reglas técnicas generales de WordPress (`User-agent: *`). Googlebot, bingbot, OAI-SearchBot, Claude-SearchBot, Claude-User, ChatGPT-User y PerplexityBot las heredan sin grupos redundantes. GPTBot y ClaudeBot tienen una política diferente: `Disallow: /` para entrenamiento. Google-Extended se permite inicialmente para favorecer Gemini; la opción independiente puede bloquearlo, lo cual no afecta Google Search, pero sí puede limitar entrenamiento y grounding en Gemini.

El catálogo en `Digitalisimo_Integrations_SEO_AI::crawlers()` registra proveedor, categoría, propósito, valor inicial y documentación. Se amplía con los rastreadores personalizados de SEO AI o con el filtro `digitalisimo_seo_ai_crawlers`. Añadir un rastreador permitido no añade por sí mismo un grupo al archivo. Un grupo se genera sólo si hay un bloqueo o una excepción por contenido; cuando permite rutas, copia también las reglas técnicas generales porque un grupo específico no hereda `*`. Las reglas manuales con un User-agent explícito tienen prioridad y no se duplican automáticamente. Revise sus reglas técnicas al escribir estos grupos manuales.

`robots.txt` sólo expresa preferencias para rastreadores que lo respetan. Un archivo físico en la raíz puede impedir que WordPress entregue la versión dinámica.
