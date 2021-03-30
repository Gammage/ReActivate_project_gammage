<?php
/**
 * Notices template
 */

declare(strict_types=1);
namespace ahrefs\AhrefsSeo;

$locals   = Ahrefs_Seo_View::get_template_variables();
$messages = $locals['messages'];

$unique = [];
foreach ( $messages as $item ) {
	$key = md5( $item['message'] ); // unique messages, any source.
	if ( ! isset( $unique[ $key ] ) ) {
		$unique[ $key ]          = $item;
		$unique[ $key ]['count'] = 1;
	} else {
		$unique[ $key ]['count']++;
	}
}
?>
<div class="notice notice-info is-dismissible" id="ahrefs_api_notices">
	<div id="ahrefs-notices">
		<?php
		if ( count( $unique ) ) {
			foreach ( $unique as $key => $item ) {
				$title = Ahrefs_Seo_Errors::get_title_for_source( $item['source'] );
				?>
				<p id="<?php echo esc_attr( "message-id-$key" ); ?>" data-count="<?php echo esc_attr( "{$item['count']}" ); ?>" class="ahrefs-message">
					<b><?php echo esc_html( $title ); ?></b>:
					<?php echo esc_html( $item['message'] ); ?>
					<span class="ahrefs-messages-count<?php echo esc_attr( 1 === $item['count'] ? ' hidden' : '' ); ?>"><?php echo esc_html( "{$item['count']}" ); ?></span>
				</p>
				<?php
				if ( 'compatibility' === $item['source'] ) {
					?>
		<p><a href="https://help.ahrefs.com/en/articles/4858501-why-is-my-wordpress-plugin-incompatible-with-the-ahrefs-seo-wordpress-plugin" target="_blank">Why’s this happening?</a></p>
					<?php
				}
			}
		}
		?>
	</div>
</div>
<?php
