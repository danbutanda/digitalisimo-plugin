<?php
/** RM4 y RM8: detección de vídeos, datos de Vimeo y entradas del sitemap de vídeo, sin inventar nada. */
define( 'ABSPATH', __DIR__ );
define( 'WEEK_IN_SECONDS', 604800 );
function add_action() {} function add_filter() {} function add_shortcode() {}
function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); }
function esc_xml( $v ) { return htmlspecialchars( (string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8' ); }
require __DIR__ . '/../digitalisimo-seo/includes/class-video.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-sitemap.php';
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$v = Digitalisimo_Integrations_Video::class;

$html = '<iframe src="https://player.vimeo.com/video/76979871?h=8272103f6e"></iframe> <figure class="wp-block-embed is-provider-vimeo"><div>
https://vimeo.com/22439234
</div></figure> https://vimeo.com/channels/staffpicks/123456789 <p>vimeo.com/123 no</p>'
	. '<iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"></iframe> https://youtu.be/aaaaaaaaaaa https://www.youtube.com/watch?feature=x&v=bbbbbbbbbbb'
	. '[{"settings":{"video_type":"vimeo","vimeo_url":"https:\/\/vimeo.com\/235215203"}}]';
$ids = $v::find_ids( $html );
check( array( '76979871', '22439234', '123456789', '235215203' ) === $ids['vimeo'], 'IDs de Vimeo en iframe, bloque, canal y JSON de Elementor: ' . json_encode( $ids['vimeo'] ) );
check( array( 'dQw4w9WgXcQ', 'aaaaaaaaaaa', 'bbbbbbbbbbb' ) === $ids['youtube'], 'IDs de YouTube: ' . json_encode( $ids['youtube'] ) );
check( array( 'vimeo' => array(), 'youtube' => array() ) === $v::find_ids( '<p>Sin vídeos</p>' ), 'Sin vídeos no detecta nada.' );

check( 'PT1H2M3S' === $v::iso_duration( 3723 ) && 'PT45S' === $v::iso_duration( 45 ) && 'PT10M' === $v::iso_duration( 600 ) && '' === $v::iso_duration( 0 ), 'Duración ISO 8601.' );

$oembed = array( 'video_id' => 76979871, 'title' => 'The New <b>Vimeo</b> Player', 'description' => 'Descripción real.', 'thumbnail_url' => 'https://i.vimeocdn.com/video/452001751-640.jpg', 'upload_date' => '2013-10-15 14:08:29', 'duration' => 62 );
check( array( 'name' => 'The New Vimeo Player', 'description' => 'Descripción real.', 'thumbnailUrl' => 'https://i.vimeocdn.com/video/452001751-640.jpg', 'uploadDate' => '2013-10-15', 'duration' => 'PT1M2S', 'embedUrl' => 'https://player.vimeo.com/video/76979871' ) === $v::normalize_vimeo( $oembed, '76979871' ), 'Un oEmbed completo de Vimeo se traduce; la fecha sin zona sólo publica el día.' );
check( null === $v::normalize_vimeo( array( 'upload_date' => '' ) + $oembed, '76979871' ), 'Sin fecha de publicación no hay VideoObject: nunca se inventa.' );
check( null === $v::normalize_vimeo( array( 'thumbnail_url' => 'http://i.vimeocdn.com/x.jpg' ) + $oembed, '76979871' ), 'La miniatura debe ser https.' );
check( null === $v::normalize_vimeo( $oembed, '1' ) && null === $v::normalize_vimeo( null, '76979871' ), 'Otra ID o una respuesta vacía no valen.' );

$s = Digitalisimo_Integrations_Sitemap::class;
$entry = $s::video_entry( $v::normalize_vimeo( $oembed, '76979871' ) );
check( '<video:video><video:thumbnail_loc>https://i.vimeocdn.com/video/452001751-640.jpg</video:thumbnail_loc><video:title>The New Vimeo Player</video:title><video:description>Descripción real.</video:description><video:player_loc>https://player.vimeo.com/video/76979871</video:player_loc><video:duration>62</video:duration><video:publication_date>2013-10-15</video:publication_date></video:video>' === $entry, 'Entrada de Vimeo con reproductor, duración y fecha: ' . $entry );
$local = array( 'name' => 'Demo & más', 'description' => 'Del archivo', 'thumbnailUrl' => 'https://sitio.test/p.jpg', 'uploadDate' => '2026-01-02T10:00:00+00:00', 'contentUrl' => 'https://sitio.test/v.mp4', 'duration' => 'PT9H' );
$entry = $s::video_entry( $local );
check( false !== strpos( $entry, '<video:content_loc>https://sitio.test/v.mp4</video:content_loc>' ) && false !== strpos( $entry, 'Demo &amp; más' ) && false === strpos( $entry, 'video:duration' ), 'Un archivo local usa content_loc; una duración de más de 8 h no se publica.' );
check( '' === $s::video_entry( array( 'description' => '' ) + $local ), 'Sin descripción no entra en el sitemap de vídeo.' );
echo "Vídeo: detección, datos de Vimeo y sitemap de vídeo correctos.\n";
