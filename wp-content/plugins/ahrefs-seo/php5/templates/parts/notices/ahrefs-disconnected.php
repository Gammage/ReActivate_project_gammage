<?php

namespace ahrefs\AhrefsSeo;

// link to Settings: Account.
$link = add_query_arg(
	[
		'page' => Ahrefs_Seo::SLUG_SETTINGS,
		'tab'  => 'account',
	],
	admin_url( 'admin.php' )
);
?>
<div class="notice notice-warning">
	<p><strong>Ahrefs disconnected.</strong></p>
	<p>Please insert your Ahrefs token on the settings page again.</p>
	<p><a href="<?php echo esc_attr( $link ); ?>" class="button button-primary">Connect Ahrefs</a></p>
</div>
<?php
