# Visor de documentos: costo estático

La referencia Element Pack Pro 9.9.1 implementa `bdt-document-viewer` con CSS de su infraestructura general y un iframe. DIGITALÍSIMO Elements registra una hoja específica de 243 B y no declara JavaScript para este widget. Los estilos se solicitan sólo al renderizarlo; la referencia completa tiene recursos compartidos que no pueden atribuirse exclusivamente a este widget sin medir una página real.

La comparación funcional queda pendiente en WordPress/Elementor. Se debe probar el PDF local, un archivo público en Google Docs, una URL de red privada, la altura responsiva y las políticas de iframe del origen. No se afirma una mejora de Core Web Vitals sin medir esas páginas.
