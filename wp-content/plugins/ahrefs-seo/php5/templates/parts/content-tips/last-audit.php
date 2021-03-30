<?php

namespace ahrefs\AhrefsSeo;

/**
* @var array<string, bool> {
*
*   @type bool $no_ahrefs_account
*   @type bool $no_google_account
* }
*/
$locals = Ahrefs_Seo_View::get_template_variables();
// link to the Content Settings.
$link = add_query_arg(
	[
		'page'   => Ahrefs_Seo::SLUG_SETTINGS,
		'tab'    => 'content',
		'return' => add_query_arg(
			'page',
			Ahrefs_Seo::SLUG_CONTENT,
			admin_url( 'admin.php' )
		),
	],
	admin_url( 'admin.php#schedule' )
);
?>
<!-- last audit notice -->
<div class="ahrefs-content-tip" id="last_content_audit_tip">
	<div class="caption">The last content audit was over a month ago</div>
	<div class="text">We suggest running an audit at least once a week. This will ensure all your pages’ metrics and rankings are updated.</div>
	<div class="text">Set up a schedule so the plugin will run audits automatically.</div>
	<div class="buttons">
		<a
			class="run-audit-button button button-primary manual-update-content-link
			<?php
			if ( $locals['no_ahrefs_account'] || $locals['no_google_account'] ) {
				?>
	 disabled
				<?php
			} // phpcs:ignore Squiz.ControlStructures.ControlSignature.NewlineAfterOpenBrace  ?>"
			href="#">Run new audit</a>
		<a class="link content-audit-schedule" href="<?php echo esc_attr( $link ); ?>"><span></span>Set up a schedule</a>
	</div>
</div>
<?php
