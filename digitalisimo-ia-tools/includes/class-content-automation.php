<?php
defined( 'ABSPATH' ) || exit;

/** Automatizaciones de contenido antes alojadas en functions.php. */
class Digitalisimo_Content_Automation {
	public static function init() { add_action( 'rest_api_init', array( __CLASS__, 'routes' ) ); add_action( 'save_post', array( __CLASS__, 'set_excerpt' ), 20, 3 ); }
	public static function routes() { register_rest_route( 'digitalisimo/v1', '/import-image', array( 'methods'=>'POST', 'permission_callback'=>function(){return current_user_can('upload_files');}, 'callback'=>array(__CLASS__,'import_image') ) ); }
	public static function import_image( WP_REST_Request $request ) { if ( ! Digitalisimo_AI::get( 'mcp_featured_image_enabled' ) ) return new WP_Error( 'mcp_images_disabled', 'La carga de imágenes destacadas por MCP está desactivada.', array( 'status' => 403 ) );
		$post_id=absint($request->get_param('post_id')); if(!$post_id||!get_post($post_id)){ $posts=get_posts(array('numberposts'=>1,'post_type'=>'post','post_status'=>'publish','orderby'=>'date','order'=>'DESC','author'=>get_current_user_id()?:0)); $post_id=!empty($posts)?$posts[0]->ID:0; }
		$url=esc_url_raw($request->get_param('url')); $files=$request->get_file_params(); $file=$files['file']??null; if(!$url&&empty($file['tmp_name']))return new WP_Error('no_image','Envía una URL o un archivo.',array('status'=>400));
		require_once ABSPATH.'wp-admin/includes/file.php'; require_once ABSPATH.'wp-admin/includes/media.php'; require_once ABSPATH.'wp-admin/includes/image.php'; $title=sanitize_text_field($request->get_param('image_title'))?:($post_id?wp_strip_all_tags(get_the_title($post_id)):''); $alt=sanitize_text_field($request->get_param('image_alt'))?:$title;
		if(!empty($file['tmp_name'])){ $file['name']=sanitize_file_name(sanitize_title($title?:'digitalisimo-image').'.'.(pathinfo($file['name']??'',PATHINFO_EXTENSION)?:'png')); $image_id=media_handle_sideload($file,$post_id); } else $image_id=media_sideload_image($url,$post_id,$title,'id'); if(is_wp_error($image_id))return$image_id;
		if($post_id){if($title){wp_update_post(array('ID'=>$image_id,'post_title'=>$title,'post_name'=>sanitize_title($title)));update_post_meta($image_id,'_wp_attachment_image_alt',$alt);if(!get_post_meta($post_id,'digitalisimo_seo_keywords',true))update_post_meta($post_id,'digitalisimo_seo_keywords',$title);}set_post_thumbnail($post_id,$image_id);}
		return rest_ensure_response(array('success'=>true,'id'=>$image_id,'post_id'=>$post_id,'source_url'=>wp_get_attachment_url($image_id)));
	}
	private static function excerpt( $content, $length=160 ) { $text=trim(preg_replace('/\s+/',' ',strip_shortcodes(wp_strip_all_tags($content)))); if(mb_strlen($text)<=$length)return$text; $cut=mb_substr($text,0,$length);$space=mb_strrpos($cut,' ');return mb_substr($cut,0,false===$space?$length:$space).'…'; }
	public static function set_excerpt( $post_id,$post,$update ) { if(wp_is_post_revision($post_id)||'post'!==$post->post_type||$post->post_excerpt||!(defined('REST_REQUEST')&&REST_REQUEST))return;$excerpt=self::excerpt($post->post_content);if(!$excerpt)return;remove_action('save_post',array(__CLASS__,'set_excerpt'),20);wp_update_post(array('ID'=>$post_id,'post_excerpt'=>$excerpt));add_action('save_post',array(__CLASS__,'set_excerpt'),20,3); }
}
