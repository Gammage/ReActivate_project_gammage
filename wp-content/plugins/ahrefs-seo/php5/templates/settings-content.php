<?php

namespace ahrefs\AhrefsSeo;

$locals = Ahrefs_Seo_View::get_template_variables();
$view   = Ahrefs_Seo::get()->get_view();
if ( ! isset( $locals['button_title'] ) ) {
	$locals['button_title'] = 'Continue';
}
?>
<form method="post" action="" class="ahrefs-seo-wizard ahrefs-audit">
	<input type="hidden" name="ahrefs_audit_options" value="1">
	<?php
	if ( isset( $locals['page_nonce'] ) ) {
		wp_nonce_field( $locals['page_nonce'] );
	}
	?>
	<div class="card-item">
		<?php
		// With last changes (do not query any API for newly published or newly updated items) we should add estimate to Content audit Settings.
		// The estimated value will appear if user changed waiting time option to lower value.
		// Estimate depend of option value and number of Newly updated or published items and their dates.
		$locals['show_estimate_based_on_waiting_time'] = true;
		$view->show_part( 'options/scope', $locals );
		$view->show_part( 'options/tresholds', $locals );
		$view->show_part( 'options/schedule', $locals );
		?>
	</div>

	<div class="button-wrap">
		<a href="#" class="button button-hero button-primary" id="ahrefs_seo_submit">
		<?php
		echo esc_html( $locals['button_title'] );
		?>
		</a>
	</div>
</form>
<?php
