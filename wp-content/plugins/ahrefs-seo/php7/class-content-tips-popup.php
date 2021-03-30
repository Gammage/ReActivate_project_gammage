<?php

declare(strict_types=1);

namespace ahrefs\AhrefsSeo;

/**
 * Class for content audit popup tips.
 * Display tip at the keywords popup if allowed.
 */
class Content_Tips_Popup extends Content_Tips_Any {

	protected const OPTION_TIP_CLOSED = 'ahrefs-seo-content-tip-closed-popup';

	protected function show( ?string $already_displayed = null ) : void {
		Ahrefs_Seo::get()->get_view()->show_part( 'content-tips/keywords-tip' );
	}
}
