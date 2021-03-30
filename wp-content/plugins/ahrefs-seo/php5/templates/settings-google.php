<?php

namespace ahrefs\AhrefsSeo;

$locals = Ahrefs_Seo_View::get_template_variables();
$view   = Ahrefs_Seo::get()->get_view();
if ( ! isset( $locals['button_title'] ) ) {
	$locals['button_title'] = 'Continue';
}
$disconnect_link = 'settings' === $locals['disconnect_link'] ? add_query_arg(
	[
		'page'                 => Ahrefs_Seo::SLUG_SETTINGS,
		'tab'                  => 'analytics',
		'disconnect-analytics' => wp_create_nonce( $locals['page_nonce'] ),
	],
	admin_url( 'admin.php' )
) : add_query_arg(
	[
		'page'                 => Ahrefs_Seo::SLUG,
		'step'                 => 2,
		'disconnect-analytics' => wp_create_nonce( $locals['page_nonce'] ),
	],
	admin_url( 'admin.php' )
);
if ( $locals['token_set'] && ! $locals['no_ga'] && ! $locals['no_gsc'] ) {
	$view->show_part(
		'options/google-connected',
		[
			'page_nonce'         => $locals['page_nonce'],
			'error'              => $locals['error'],
			'button_title'       => $locals['button_title'],
			'preselect_accounts' => $locals['preselect_accounts'],
			'disconnect_link'    => $disconnect_link,
		]
	);
} else {
	$view->show_part( 'options/google-code', $locals );
}
