<?php
defined( 'ABSPATH' ) || exit;

/** Automatizaciones de contenido antes alojadas en functions.php. */
class Digitalisimo_Content_Automation {
	public static function init() { add_action( 'rest_api_init', array( __CLASS__, 'routes' ) ); add_filter( 'wp_insert_post_data', array( __CLASS__, 'excerpt_data' ), 20, 2 ); }
	public static function routes() { register_rest_route( 'digitalisimo/v1', '/import-image', array( 'methods'=>'POST', 'permission_callback'=>function(){return current_user_can('upload_files');}, 'callback'=>array(__CLASS__,'import_image') ) ); }
	public static function import_image( WP_REST_Request $request ) { if ( ! Digitalisimo_AI::get( 'mcp_featured_image_enabled' ) ) return new WP_Error( 'mcp_images_disabled', 'La carga de imágenes destacadas por MCP está desactivada.', array( 'status' => 403 ) );
		$post_id=absint($request->get_param('post_id')); if(!$post_id||!get_post($post_id)){ $posts=get_posts(array('numberposts'=>1,'post_type'=>'post','post_status'=>'publish','orderby'=>'date','order'=>'DESC','author'=>get_current_user_id()?:0)); $post_id=!empty($posts)?$posts[0]->ID:0; }
		// La imagen sólo se adjunta y se pone como destacada en un contenido que este usuario puede editar.
		if($post_id&&!current_user_can('edit_post',$post_id))return new WP_Error('digitalisimo_image_post','No puedes editar el contenido indicado.',array('status'=>403));
		$url=esc_url_raw($request->get_param('url')); $files=$request->get_file_params(); $file=$files['file']??null; if(!$url&&empty($file['tmp_name']))return new WP_Error('no_image','Envía una URL o un archivo.',array('status'=>400));
		require_once ABSPATH.'wp-admin/includes/file.php'; require_once ABSPATH.'wp-admin/includes/media.php'; require_once ABSPATH.'wp-admin/includes/image.php'; $title=sanitize_text_field($request->get_param('image_title'))?:($post_id?wp_strip_all_tags(get_the_title($post_id)):''); $alt=sanitize_text_field($request->get_param('image_alt'))?:$title;
		if(!empty($file['tmp_name'])){ $file['name']=sanitize_file_name(sanitize_title($title?:'digitalisimo-image').'.'.(pathinfo($file['name']??'',PATHINFO_EXTENSION)?:'png')); $image_id=media_handle_sideload($file,$post_id); } else $image_id=media_sideload_image($url,$post_id,$title,'id'); if(is_wp_error($image_id))return$image_id;
		if($post_id){if($title){wp_update_post(array('ID'=>$image_id,'post_title'=>$title,'post_name'=>sanitize_title($title)));update_post_meta($image_id,'_wp_attachment_image_alt',$alt);if(!get_post_meta($post_id,'digitalisimo_seo_keywords',true))update_post_meta($post_id,'digitalisimo_seo_keywords',$title);}set_post_thumbnail($post_id,$image_id);}
		return rest_ensure_response(array('success'=>true,'id'=>$image_id,'post_id'=>$post_id,'source_url'=>wp_get_attachment_url($image_id)));
	}
	private static function excerpt( $content, $length=160 ) { $text=trim(preg_replace('/\s+/',' ',strip_shortcodes(wp_strip_all_tags($content)))); if(mb_strlen($text)<=$length)return$text; $cut=mb_substr($text,0,$length);$space=mb_strrpos($cut,' ');return mb_substr($cut,0,false===$space?$length:$space).'…'; }
	/**
	 * Extracto automático de una entrada creada o editada por la REST sin extracto: se calcula en los datos que se van a
	 * guardar (wp_insert_post_data), no con un wp_update_post() anidado en save_post que repetía todos los save_post del
	 * sitio y creaba otra revisión. Mismo texto (160 caracteres del contenido) y mismos filtros de un extracto guardado.
	 */
	public static function excerpt_data( $data, $postarr = array() ) {
		if ( ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || 'post' !== ( $data['post_type'] ?? '' ) || '' !== trim( (string) ( $data['post_excerpt'] ?? '' ) ) ) return $data;
		$excerpt = self::excerpt( wp_unslash( (string) ( $data['post_content'] ?? '' ) ) );
		if ( ! $excerpt ) return $data;
		$data['post_excerpt'] = wp_slash( sanitize_post_field( 'post_excerpt', $excerpt, (int) ( $postarr['ID'] ?? 0 ), 'db' ) );
		return $data;
	}
}
