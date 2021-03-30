<?php
declare(strict_types=1);
namespace ahrefs\AhrefsSeo;

$locals = Ahrefs_Seo_View::get_template_variables();
if ( $locals['no_ga'] && $locals['no_gsc'] ) {
	?>
	<!-- no ga & gsc accounts tip -->
	<div class="ahrefs-content-tip tip-notice">
		<div class="caption">Google Analytics & Search Console accounts were not found</div>
		<div class="text">There are no Google Analytics & Search Console accounts connected to the Google profile. Please create GA and GSC accounts for your website or connect another Google profile.</div>
	</div>
	<?php
} elseif ( $locals['no_ga'] ) {
	if ( $locals['ga_has_account'] ) {
		?>
	<!-- no usable ga account tip -->
	<div class="ahrefs-content-tip tip-notice">
		<div class="caption">Google Analytics account was found but has no details</div>
		<div class="text">Google Analytics account does not have any profiles, suitable for using with Analytics API.</div>
	</div>
		<?php
	} else {
		?>
	<!-- no ga account tip -->
	<div class="ahrefs-content-tip tip-notice">
		<div class="caption">Google Analytics account was not found</div>
		<div class="text">There isn’t a Google Analytics account connected to the Google profile.
			Please create a Google Analytics account for your website or connect another Google profile.
		</div>
	</div>
		<?php
	}
} else {
	?>
	<!-- no gsc account tip -->
	<div class="ahrefs-content-tip tip-notice">
		<div class="caption">Google Search Console account was not found</div>
		<div class="text">There isn’t a Google Search Console account connected to the Google profile.
			Please create a Google Search Console account for your website or connect another Google profile.
		</div>
	</div>
	<?php
}
