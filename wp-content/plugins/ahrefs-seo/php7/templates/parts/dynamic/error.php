<?php
/**
 * Show Contact Ahrefs block with messages.
 */

declare(strict_types=1);
namespace ahrefs\AhrefsSeo;

$locals = Ahrefs_Seo_View::get_template_variables();

$id      = $locals['id'] ?? '';
$title   = $locals['title'] ?? '';
$message = $locals['message'] ?? '';

if ( ! empty( $message ) ) {
	?>
	<div class="notice notice-error is-dismissible" id="ahrefs_api_messsages">
		<div id="ahrefs-messages">
			<span class="message-expanded-title">Oops, seems like there was an error. Please contact Ahrefs support to get it resolved.</span>
			<a href="#" class="message-expanded-link">(Show more details)</a>
			<div class="message-expanded-text">
								<p id="<?php echo esc_attr( "message-id-$id" ); ?>" data-count="1" class="ahrefs-message">
					<b><?php echo esc_html( $title ); ?></b>:
					<?php echo esc_html( $message ); ?>
					<span class="ahrefs-messages-count hidden">1</span>
				</p>
				<?php
				require __DIR__ . '/buttons.php';
				?>
			</div>
		</div>
	</div>
	<?php
}
