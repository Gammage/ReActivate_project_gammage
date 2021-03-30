<?php

namespace ahrefs\AhrefsSeo;

$locals    = Ahrefs_Seo_View::get_template_variables();
$view      = Ahrefs_Seo::get()->get_view();
$analytics = Ahrefs_Seo_Analytics::get();
// get ua list and current choice.
$connected         = $analytics->get_ua_id() && $analytics->get_gsc_site();
$incorrect_account = false === $analytics->is_gsc_account_correct() || false === $analytics->is_ga_account_correct();
if ( empty( $locals['button_title'] ) ) {
	$locals['button_title'] = 'Continue';
}
?>
<form method="post" action="" class="ahrefs-seo-wizard ahrefs-analytics 
<?php
if ( $incorrect_account ) {
	?>
	 gsc-no-account
	<?php
}
if ( $locals['preselect_accounts'] ) {
	?>
	 autodetect
	<?php
} // phpcs:ignore Squiz.ControlStructures.ControlSignature.NewlineAfterOpenBrace  ?>">
	<input type="hidden" name="analytics_step" value="2">
	<?php
	if ( isset( $locals['page_nonce'] ) ) {
		wp_nonce_field( $locals['page_nonce'] );
	}
	?>
	<div class="card-item">
		<div class="help">
			<div class="google-logos">
				<img src="<?php echo esc_attr( AHREFS_SEO_IMAGES_URL . 'google-analytics.svg' ); ?>">
				<img src="<?php echo esc_attr( AHREFS_SEO_IMAGES_URL . 'google-gsc.svg' ); ?>">
			</div>
			Connect your Google Analytics & Search Console accounts to see your pages’ rankings and traffic stats right in WP dashboard.
			The Content Audit and content suggestion are based on these data.
		</div>
		<div class="help">
			<strong>Important.</strong> Ahrefs does not store any data from a connected Google Analytics or Google Search Console account.
		</div>

		<hr class="hr-shadow" />

		<div class="no-account-detected">
			<?php
			$view->show_part( 'options-tips/not-detected', $locals );
			?>
		</div>

		<div class="disconnect-wrap">
			<div class="your-account">Your Google Analytics & Search Console accounts are connected</div>
			<div class="your-account your-account-detecting">Autoselecting the best Google Analytics & Search Console profiles...</div>
			<div class="your-account no-account-detected">Your Google account is connected but no suitable profiles were detected</div>
			<a href="<?php echo esc_attr( $locals['disconnect_link'] ); ?>" class="disconnect-button" id="ahrefs_disconnect"><span class="text">Disconnect</span></a>
		</div>
		<div class="accounts-wrap">
			<div id="loader_google_detect">
				<div class="row-loader loader-transparent"><div class="loader"></div></div>
			</div>
			<?php
			if ( $locals['preselect_accounts'] ) {
				?>
				<!-- hide accounts loader -->
				<style type="text/css">#loader_while_accounts_loaded{display:none;}</style>
				<?php
			}
			echo '<!-- padding ' . str_pad( '', 10240, ' ' ) . ' -->'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			flush(); // show loader while settings screen loaded.
			$view->show_part( 'options/include/select-ga', $locals );
			$view->show_part( 'options/include/select-gsc', $locals );
			?>
		</div>
	</div>
	<?php
	// block with error messages, if any happened.
	$messages         = Ahrefs_Seo_Errors::get_current_messages();
	$gsc_disconnected = Ahrefs_Seo_Analytics::get()->get_gsc_disconnect_reason();
	if ( $messages ) {
		$view->show_part( 'messages', [ 'messages' => $messages ] );
		?>
		<script type="text/javascript">
			jQuery('h1').after( jQuery('.ahrefs_messages_block').detach() );
		</script>
		<?php
	}
	if ( ! is_null( $gsc_disconnected ) ) {
		$gsc_disconnected->show();
		?>
		<script type="text/javascript">
			jQuery('h1').after( jQuery('.tip-google').detach() );
		</script>
		<?php
	}
	?>
	<div class="button-wrap">
		<a href="#" class="button button-hero button-primary" id="ahrefs_seo_submit" 
		<?php
		disabled( ! $connected );
		?>
		>
<?php
echo esc_html( $locals['button_title'] );
?>
</a>
	</div>
</form>
<?php
if ( $locals['preselect_accounts'] ) {
	?>
	<script type="text/javascript">
		console.log('autodetect google');
		jQuery(function() {
			ahrefs_settings.autodetect();
		});
	</script>
	<?php
}
