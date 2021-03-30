<?php
/**
 * Buttons template
 */

declare(strict_types=1);
namespace ahrefs\AhrefsSeo;

// which buttons to show?
/** @var array $locals Defined in parent template. */
$buttons = $locals['buttons'] ?? [];

$button_plugins = in_array( 'plugins', $buttons, true );
$button_themes  = in_array( 'themes', $buttons, true );
$button_ahrefs  = in_array( 'ahrefs', $buttons, true );
$button_google  = in_array( 'google', $buttons, true );
$how_to_google  = in_array( 'how_to_google', $buttons, true );
$refresh_page   = in_array( 'refresh_page', $buttons, true );
$close_button   = in_array( 'close', $buttons, true );

$show_buttons = $button_plugins || $button_themes || $button_ahrefs || $button_google || $how_to_google || $refresh_page || $close_button; // show buttons block?
if ( $show_buttons ) {
	?>
	<div class="buttons">
		<?php
		if ( $button_plugins ) {

			?>
		<a id="open_plugins_button" class="button button-primary button-link-delete gear-button" href="<?php echo esc_attr( admin_url( 'plugins.php' ) ); ?>">Go to plugins page</a>
			<?php
		}
		if ( $button_themes ) {
			?>
		<a id="open_plugins_button" class="button button-primary button-link-delete gear-button" href="<?php echo esc_attr( admin_url( 'themes.php' ) ); ?>">Go to themes page</a>
			<?php
		}
		if ( $button_ahrefs || $button_google ) {
			$link = add_query_arg(
				[
					'page' => Ahrefs_Seo::SLUG_SETTINGS,
					'tab'  => $button_ahrefs ? 'account' : 'analytics',
					[ 'return' => add_query_arg( 'page', Ahrefs_Seo::SLUG_CONTENT, admin_url( 'admin.php' ) ) ],
				],
				admin_url( 'admin.php' )
			);
			?>
		<a id="open_account_setting_button" class="button button-primary button-link-delete" href="<?php echo esc_attr( $link ); ?>">Set up connections</a>
			<?php
		}
		if ( $how_to_google ) {
			?>
			<a href="https://help.ahrefs.com/en/articles/4666920-how-do-i-connect-google-analytics-search-console-to-the-plugin" target="_blank" class="learn-more-link">
				<span class="text">How do I connect the right account?</span>
				<img src="<?php echo esc_attr( AHREFS_SEO_IMAGES_URL ); ?>link-open.svg" class="icon">
			</a>
			<?php
		}
		if ( $refresh_page ) {
			?>
			<a class="button button-primary refresh-page-button" href="javascript:document.location.reload();">Refresh the page</a>
			<?php
		}
		if ( $close_button ) {
			?>
			<button type="button" class="notice-dismiss close-current-message"><span class="screen-reader-text">Dismiss this notice.</span></button>
			<?php
		}
		?>
	</div>
	<?php
}
