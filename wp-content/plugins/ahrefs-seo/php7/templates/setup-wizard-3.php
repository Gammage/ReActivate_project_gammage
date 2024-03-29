<?php
/**
 * Setup Wizard template, step 3.
 */

declare(strict_types=1);
namespace ahrefs\AhrefsSeo;

$locals = Ahrefs_Seo_View::get_template_variables();
$view   = Ahrefs_Seo::get()->get_view();
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
		$view->show_part( 'options/scope', $locals );
		$view->show_part( 'options/schedule', $locals );
		?>
		<hr class="hr-shadow" />
		<div class="block-title">Error diagnostics</div>
		<?php
		$locals['show_additional_line'] = true;
		$view->show_part( 'options/diagnostics', $locals );

		?>
	</div>

	<div class="button-wrap">
		<a href="#" class="button button-hero button-primary wizard-run-button" id="ahrefs_seo_submit"><span></span>Run content audit</a>
	</div>

</form>
