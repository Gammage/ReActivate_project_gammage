<?php

namespace ahrefs\AhrefsSeo;

$locals = Ahrefs_Seo_View::get_template_variables();
// link to the Content Settings.
$link   = add_query_arg(
	[
		'page'   => Ahrefs_Seo::SLUG_SETTINGS,
		'tab'    => 'content',
		'return' => add_query_arg(
			'page',
			Ahrefs_Seo::SLUG_CONTENT,
			admin_url( 'admin.php' )
		),
	],
	admin_url( 'admin.php' )
);
$stat   = Ahrefs_Seo_Data_Content::get()->get_statictics();
$paused = Content_Audit::audit_is_paused();
?>
<div id="ahrefs_seo_screen" class="<?php echo isset( $locals['header_class'] ) ? esc_attr( '' . implode( ' ', $locals['header_class'] ) ) : ''; ?>">

<div class="ahrefs-header-wrap">
	<div class="ahrefs-header">

		<div>
			<img src="<?php echo esc_attr( AHREFS_SEO_IMAGES_URL . 'logo-content-audit.svg' ); ?>" class="logo">
			<span class="header-hint">
			<?php
			echo esc_html( $stat['last_time'] );
			?>
			</span>
		</div>
		<div class="content-right">
			<div>
				<a class="content-hint-how" href="https://help.ahrefs.com/en/articles/3901720-how-does-the-ahrefs-seo-wordpress-plugin-work" target="_blank">How it works</a>
			</div>
			<div id="content_audit_status" class="
			<?php
			echo esc_attr( $stat['in_progress'] ? 'in-progress' : '' );
			echo esc_attr( $paused ? ' paused' : '' ); ?>">
				<a
					class="run-audit-button button-orange manual-update-content-link
					<?php
					if ( $locals['no_ahrefs_account'] || $locals['no_google_account'] || $locals['ahrefs_limited'] ) {
						?>
	 disabled
						<?php
					} // phpcs:ignore Squiz.ControlStructures.ControlSignature.NewlineAfterOpenBrace  ?>"
					href="#">Run new audit</a>
				<a
					class="paused-audit-button button-dark 
					<?php
					if ( $locals['no_ahrefs_account'] || $locals['no_google_account'] || $locals['ahrefs_limited'] ) {
						?>
	 disabled
						<?php
					} // phpcs:ignore Squiz.ControlStructures.ControlSignature.NewlineAfterOpenBrace  ?>"
					href="#">Audit paused</a>
				<div class="audit-progressbar">
					<div class="position"
						<?php
						if ( $stat['percents'] > 0 ) {
							?>
	 style="width:
							<?php
							echo esc_attr( $stat['percents'] . '%' ); // phpcs:ignore Squiz.ControlStructures.ControlSignature.NewlineAfterOpenBrace  ?>"
							<?php
						}
						?>
						>
					</div>
					<div class="progress">Analyzing...</div>
				</div>
			</div>
			<div>
				<a id="analysis_setting_button" class="button" href="<?php echo esc_attr( $link ); ?>"></a>
			</div>
		</div>
	</div>
</div>
<?php
