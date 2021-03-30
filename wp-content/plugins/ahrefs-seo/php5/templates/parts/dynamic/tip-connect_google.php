<?php

namespace ahrefs\AhrefsSeo;

/**
 * Tip with 'how to connect Google...' button template
 */
$locals  = Ahrefs_Seo_View::get_template_variables();
$id      = isset( $locals['id'] ) ? $locals['id'] : '';
$title   = $locals['title'];
$message = $locals['message'];
?>
<div class="ahrefs-content-tip tip-notice" id="<?php echo esc_attr( $id ); ?>" data-id="<?php echo esc_attr( $id ); ?>">
	<div class="caption">
	<?php
	echo esc_html( $title );
	?>
	</div>
	<div class="text">
	<?php
	echo esc_html( $message );
	?>
	</div>
	<div class="text">
		<a href="https://help.ahrefs.com/en/articles/4666920-how-do-i-connect-google-analytics-search-console-to-the-plugin" target="_blank" class="learn-more-link">
			<span class="text">How do I connect the right Google Analytics &amp; Search Console accounts?</span>
			<img src="
			<?php
			echo esc_attr( AHREFS_SEO_IMAGES_URL );
			?>
			link-open.svg" class="icon">
		</a>
	</div>
</div>
<?php
