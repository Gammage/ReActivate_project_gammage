<?php
/**
 * Setup Wizard template, last step.
 */

declare(strict_types=1);
namespace ahrefs\AhrefsSeo;

$locals = Ahrefs_Seo_View::get_template_variables();

/**
* Call action 'ahrefs_progress' from js code until finish status received.
*/

// current progress.
$progress = Ahrefs_Seo_Data_Wizard::get()->get_progress( true );
$percents = $progress['percents'];
$finish   = $progress['finish'];
?>
<form method="post" action="<?php echo esc_attr( add_query_arg( 'page', Ahrefs_Seo::SLUG, admin_url( 'admin.php' ) ) ); ?>" class="ahrefs-seo-wizard ahrefs-audit">
	<input type="hidden" name="ahrefs_audit_skip_wizard" value="1">
	<?php
	if ( isset( $locals['page_nonce'] ) ) {
		wp_nonce_field( $locals['page_nonce'] );
	}
	?>
	<div class="card-item">
		<div class="block-title">Running your first audit</div>

		<div class="block-text">
			We’re hard at work reviewing all your pages, analyzing your site, and preparing recommendations.
			Feel free to check back in a few minutes’ time. In the future, this process will run in the background.
		</div>

		<div id="progressbar" <?php if ( $finish ) { // phpcs:ignore Squiz.ControlStructures.ControlSignature.NewlineAfterOpenBrace ?> class="completed"<?php } ?>>
			<div class="position"
				<?php if ( $percents > 0 ) { ?> style="width:<?php echo esc_attr( $percents . '%' ); // phpcs:ignore Squiz.ControlStructures.ControlSignature.NewlineAfterOpenBrace ?>"<?php } ?>>
			</div>
			<div class="progress">In progress</div>
			<div class="progress-completed">Completed</div>
		</div>
	</div>

	<div class="button-wrap">
		<a href="#" class="button button-hero button-primary"
			id="ahrefs_seo_submit">View report</a>
	</div>
</form>
