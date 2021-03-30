<?php

namespace ahrefs\AhrefsSeo;

/**
 * Class for content audit popup tips.
 * Display tip at the keywords popup if allowed.
 */
class Content_Tips_Popup extends Content_Tips_Any {

	const OPTION_TIP_CLOSED = 'ahrefs-seo-content-tip-closed-popup';
	protected function show( $already_displayed = null ) {
		Ahrefs_Seo::get()->get_view()->show_part( 'content-tips/keywords-tip' );
	}
}
