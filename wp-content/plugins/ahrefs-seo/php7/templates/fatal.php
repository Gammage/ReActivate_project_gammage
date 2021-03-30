<?php
/**
 * Error page template
 */

declare(strict_types=1);
namespace ahrefs\AhrefsSeo;

$locals = Ahrefs_Seo_View::get_template_variables();
$view   = Ahrefs_Seo::get()->get_view();
?>
<p>Oops, seems like there was an error. Please contact Ahrefs support to get it resolved.</p>
<p><?php echo esc_html( $locals['error'] ); ?></p>
