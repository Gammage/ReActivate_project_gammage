<?php

namespace ahrefs\AhrefsSeo;

$locals = Ahrefs_Seo_View::get_template_variables();
?>
<div class="block-text">
	Help us improve your plugin experience by automatically sending diagnostic reports to our server when an error occurs.
	This will help with plugin stability and other improvements.
	We take privacy seriously - we do not send any other information regarding your website when an error does not occur.
</div>
<?php
if ( ! empty( $locals['show_additional_line'] ) ) {
	?>
	<div class="block-text">
		You can change this under Settings at any point in time.
	</div>
	<?php
}
?>
<hr class="shadow">

<div class="input-wrap">
	<input id="allow_reports" type="checkbox" name="allow_reports" value="1" class="checkbox-input" 
	<?php
	checked( isset( $locals['enabled'] ) ? $locals['enabled'] : true );
	?>
	>
	<label for="allow_reports" class="help">Send error diagnostic reports to Ahrefs</label>
</div>
<?php
