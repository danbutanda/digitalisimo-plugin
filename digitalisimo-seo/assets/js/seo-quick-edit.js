/**
 * Edición rápida de SEO en los listados: copia los datos ocultos de la fila
 * (.digitalisimo-seo-inline) a los campos del panel al abrirlo.
 */
( function () {
	if ( ! window.inlineEditPost ) return;
	var original = window.inlineEditPost.edit;
	window.inlineEditPost.edit = function ( id ) {
		original.apply( this, arguments );
		var postId = typeof id === 'object' ? this.getId( id ) : id;
		var row = document.getElementById( 'post-' + postId );
		var editor = document.getElementById( 'edit-' + postId );
		if ( ! row || ! editor ) return;
		var data = row.querySelector( '.digitalisimo-seo-inline' );
		if ( ! data ) return;
		var set = function ( name, value ) {
			var field = editor.querySelector( '[name="digitalisimo_seo_quick[' + name + ']"]:not([type="hidden"])' );
			if ( ! field ) return;
			if ( field.type === 'checkbox' ) field.checked = value === '1';
			else field.value = value || '';
		};
		set( 'seo_title', data.dataset.title );
		set( 'seo_description', data.dataset.description );
		set( 'keywords', data.dataset.keywords );
		set( 'noindex', data.dataset.noindex );
		set( 'nofollow', data.dataset.nofollow );
	};
} )();
