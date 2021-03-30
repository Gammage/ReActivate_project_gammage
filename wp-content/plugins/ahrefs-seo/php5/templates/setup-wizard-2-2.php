<?php

namespace ahrefs\AhrefsSeo;

$locals = Ahrefs_Seo_View::get_template_variables();
$view   = Ahrefs_Seo::get()->get_view();
$view->show_part(
	'options/google-connected',
	[
		'page_nonce'         => $locals['page_nonce'],
		'error'              => $locals['error'],
		'button_title'       => 'Continue',
		'preselect_accounts' => true,
		'disconnect_link'    => add_query_arg(
			[
				'page'                 => Ahrefs_Seo::SLUG,
				'disconnect-analytics' => wp_create_nonce( $locals['page_nonce'] ),
			]
		),
	]
);
