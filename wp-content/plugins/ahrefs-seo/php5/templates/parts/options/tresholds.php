<?php

namespace ahrefs\AhrefsSeo;

$locals        = Ahrefs_Seo_View::get_template_variables();
$content       = new Ahrefs_Seo_Content_Settings();
$waiting_weeks = $content->get_waiting_weeks();
?>
<hr class="hr-shadow">
<div class="block-title">Waiting time after publication or update</div>
<div class="block-text">
	Any page that was published or updated below this threshold will be excluded from the analysis.
	For example, if you set a value of 12 here and your page was published less than 12 weeks ago it will be excluded from this analysis.
	<br>
	<input id="waiting_time" type="number" min="1" max="48" name="waiting_weeks" value="<?php echo esc_attr( "{$waiting_weeks}" ); ?>" class="wrapped-input">
	<label class="helper-text">weeks</label>
</div>
<?php
