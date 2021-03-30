<?php

namespace ahrefs\AhrefsSeo;

$locals = Ahrefs_Seo_View::get_template_variables();
$items  = Ahrefs_Seo_Advisor::get()->find_internal_links( $locals['post_id'] );
if ( ! empty( $items ) ) {
	$subtitle = sprintf( _n( '%d page linking to this', '%d pages linking to this', count( $items ) ), count( $items ) );
	?>
<p href="#" class="more-page more-page-good"><span></span> 
	<?php
	echo esc_html( $subtitle );
	?>
	</p>
<div class="more-page-content">
	<?php
	foreach ( $items as $item ) {
		$permalink = $item['url'];
		$title     = $item['title'];
		?>
		<a class="details-related-item" target="_blank" href="<?php echo esc_attr( $permalink ); ?>">
			<?php
			echo esc_html( $title );
			?>
		</a>
		<?php
	}
	?>
</div>
	<?php
}
