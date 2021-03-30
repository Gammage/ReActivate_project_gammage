<?php

namespace ahrefs\AhrefsSeo;

$locals      = Ahrefs_Seo_View::get_template_variables();
$snapshot_id = Ahrefs_Seo_Data_Content::snapshot_context_get();
if ( count( $locals['items'] ) ) {
	$subtitle = sprintf( _n( '%d underperforming page that can be merged with the top-performing one', '%d underperforming pages that can be merged with the top-performing one', count( $locals['items'] ) ), count( $locals['items'] ) );
	?>
	<p href="#" class="more-page more-page-under"><span></span> 
	<?php
	echo esc_html( $locals['subtitle'] );
	?>
	</p>
	<div class="more-page-content">
		<?php
		foreach ( $locals['items'] as $item_id => $item_title ) {
			$permalink = (string) get_permalink( $item_id );
			?>
			<div class="details-related-item">
				<a target="_blank" href="<?php echo esc_attr( $permalink ); ?>">
					<?php
					echo esc_html( $item_title );
					?>
				</a>
				<div class="block-row-actions">
					<?php
					if ( current_user_can( 'edit_post', $item_id ) ) {
						?>
						<a class="row-action-item" href="<?php echo esc_attr( (string) get_edit_post_link( $item_id ) ); ?>">Edit</a>
						|
						<?php
					}
					?>
					<a class="row-action-item" href="<?php echo esc_attr( (string) get_permalink( $item_id ) ); ?>">View</a>
				</div>
			</div>
			<?php
		}
		?>
	</div>
	<?php
}
