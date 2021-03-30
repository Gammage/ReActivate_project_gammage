<?php
declare(strict_types=1);
namespace ahrefs\AhrefsSeo;

$locals = Ahrefs_Seo_View::get_template_variables();
if ( isset( $locals['position'] ) ) {
	$pos = ! is_null( $locals['position'] ) ? floatval( $locals['position'] ) : Ahrefs_Seo_Data_Content::POSITION_MAX;
	if ( $pos <= 3 ) {
		?><div class="position-good"><span></span>Position in top 3</div>
		<?php
	} elseif ( $pos <= 20 ) {
		?>
		<div class="position-bad"><span></span>Position below top 3</div>
		<?php
	} else {
		?>
		<div class="position-bad"><span></span>Position below top 20</div>
		<?php
	}
}

if ( isset( $locals['unique-keyword'] ) ) {
	if ( $locals['unique-keyword'] ) {
		?>
		<div class="position-good"><span></span>Topic is unique</div>
		<?php
	} else {
		?>
		<div class="position-bad"><span></span>Topic is not unique</div>
		<?php
	}
}

if ( isset( $locals['backlinks'] ) ) {
	if ( (int) $locals['backlinks'] > 0 ) {
		?>
		<div class="position-good"><span></span><?php echo esc_html( (string) $locals['backlinks'] ); ?> backlinks were obtained</div>
		<?php
	} else {
		?>
		<div class="position-bad"><span></span>No backlinks</div>
		<?php
	}
}

if ( isset( $locals['decent-traffic'] ) ) {
	?>
	<div class="position-good"><span></span>Decent non-organic traffic</div>
	<?php
}



