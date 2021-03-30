<?php

namespace ahrefs\AhrefsSeo;

/**
* @var array<string, bool> {
*
*   @type bool $incompatibility
*   @type bool $new_audit
*   @type string $message
* }
*/
$locals = Ahrefs_Seo_View::get_template_variables();
$last   = Ahrefs_Seo_Compatibility::get_current_incompatibility();
if ( ! is_null( $last ) ) {
	$last->show();
}
