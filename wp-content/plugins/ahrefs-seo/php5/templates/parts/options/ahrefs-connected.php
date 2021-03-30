<?php

namespace ahrefs\AhrefsSeo;

$locals       = Ahrefs_Seo_View::get_template_variables();
$view         = Ahrefs_Seo::get()->get_view();
$api          = Ahrefs_Seo_Api::get();
$data         = $api->get_subscription_info(); // this also will fresh result of is_limited_account() call.
$is_free_plan = $api->is_free_account( false ); // uncached result.
$plan            = isset( $data['subscription'] ) ? $data['subscription'] : 'Unknown';
$rows_left       = isset( $data['rows_left'] ) ? number_format( intval( $data['rows_left'] ), 0 ) . ' left' : 'Unknown';
$disconnect_link = 'settings' === $locals['disconnect_link'] ? add_query_arg(
	[
		'page'              => Ahrefs_Seo::SLUG_SETTINGS,
		'tab'               => 'account',
		'disconnect-ahrefs' => wp_create_nonce( $locals['page_nonce'] ),
	],
	admin_url( 'admin.php' )
) : add_query_arg(
	[
		'page'              => Ahrefs_Seo::SLUG,
		'step'              => 1,
		'disconnect-ahrefs' => wp_create_nonce( $locals['page_nonce'] ),
	],
	admin_url( 'admin.php' )
);
$message         = $api->get_last_error();
// filter error message.
Ahrefs_Seo_Compatibility::filter_messages( $message );
if ( '' !== $message ) {
	?><div class="updated notice error is-dismissible"><p>
	<?php
	echo esc_html( $message );
	?>
	</p></div>
	<?php
}
?>

<form method="post" action="" class="ahrefs-seo-wizard ahrefs-token">
	<input type="hidden" name="ahrefs_step" value="2">
	<?php
	if ( isset( $locals['page_nonce'] ) ) {
		wp_nonce_field( $locals['page_nonce'] );
	}
	?>
	<div class="card-item">
		<div class="image-wrap">
			<img src="<?php echo esc_attr( AHREFS_SEO_IMAGES_URL ); ?>ahrefs-wp-plugin-2-x.jpg"
				srcset="<?php echo esc_attr( AHREFS_SEO_IMAGES_URL ); ?>ahrefs-wp-plugin-2-x@2x.jpg 2x, 
<?php echo esc_attr( AHREFS_SEO_IMAGES_URL ); ?>ahrefs-wp-plugin-2-x@3x.jpg 3x"
				class="ahrefs-wp-plugin_2x">
		</div>

		<?php
		if ( $is_free_plan ) {
			?>
			<div class="disconnect-wrap">
				<div class="your-account">Free Ahrefs account is connected to your WP dashboard</div>
				<a href="<?php echo esc_attr( $disconnect_link ); ?>" class="disconnect-button" id="ahrefs_disconnect"><span class="text">Change account</span></a>
			</div>

			<div class="help">
				The free Ahrefs connection allows you to analyze the performance of all content on your site and get a healthier website with fewer low-quality pages.
				To unlock monitoring new backlinks pointing to your website and tracking down negative SEO attacks, consider subscribing on one of the Ahrefs plans.
			</div>

			<a href="https://ahrefs.com/big-data" target="_blank" class="learn-more-link">
				<span class="text">Learn more about Ahrefs</span>
				<img src="
				<?php
				echo esc_attr( AHREFS_SEO_IMAGES_URL );
				?>
	link-open.svg" class="icon">
			</a>

			<?php
		} else {
			?>

			<div class="disconnect-wrap">
				<div class="your-account">Connection success! Time to let the SEO sparks fly.</div>
				<a href="<?php echo esc_attr( $disconnect_link ); ?>" class="disconnect-button" id="ahrefs_disconnect"><span class="text">Change account</span></a>
			</div>

			<div class="account-row"><span class="account-title">Plan</span><span class="account-value">
			<?php
			echo esc_html( $plan );
			?>
	</span></div>
			<div class="account-row"><span class="account-title">Data rows</span><span class="account-value">
			<?php
			echo esc_html( $rows_left );
			?>
	</span></div>

			<hr class="hr-shadow" />

			<div class="help">Data rows are consumed when we update for new backlinks.</div>
			<?php
		}
		?>
	</div>

	<?php
	// block with error messages, if any happened.
	$messages = Ahrefs_Seo_Errors::get_current_messages();
	if ( $messages ) {
		$view->show_part( 'notices/please-contact', $messages );
		?>
		<script type="text/javascript">
			jQuery('h1').after( jQuery('#ahrefs_api_messsages').detach() );
		</script>
		<?php
	}
	?>
	<?php
	if ( ! empty( $locals['show_button'] ) ) {
		?>
		<div class="button-wrap">
			<input type="submit" name="submit" id="submit" class="button button-primary button-hero" value="Continue">
		</div>
		<?php
	}
	?>
</form>
<?php
