<?php

namespace ahrefs\AhrefsSeo;

$locals            = Ahrefs_Seo_View::get_template_variables();
$view              = Ahrefs_Seo::get()->get_view();
$backlinks         = Ahrefs_Seo_Data_Content::get()->content_get_backlinks_for_post( $locals['post_id'] );
$snapshot_id       = Ahrefs_Seo_Data_Content::snapshot_context_get();
$is_keyword_unique = ! Ahrefs_Seo_Advisor::get()->has_active_pages_with_same_keywords( $snapshot_id, $locals['post_id'] );
?>
<div class="more-wrap">
	<div class="more-column-performance">
		<div class="column-title">Performance</div>
		<?php
		$view->show_part(
			'action-parts/pages-performance',
			[
				'position'       => 21,
				'unique-keyword' => $is_keyword_unique,
				'backlinks'      => 0,
			]
		);
		?>
	</div>
	<div class="more-column-action">
		<div class="column-title">Recommended action</div>
		<ol>
			<li>
				<p>Delete</p>
				<?php
				if ( ! $is_keyword_unique ) {
					$items = Ahrefs_Seo_Advisor::get()->find_relevant_top_performing_pages( $snapshot_id, $locals['post_id'], 3 );
					if ( ! is_null( $items ) && count( $items ) ) {
						?>
						<p>There are better performing articles on your blog targeting the same topic.
						Consolidate unique content from this article into a better performing one.</p>
						<?php
						$subtitle = sprintf( _n( '%d better performing page on the same topic', '%d better performing pages on the same topic', count( $items ) ), count( $items ) );
						$view->show_part(
							'action-parts/pages-list',
							[
								'items'    => $items,
								'subtitle' => $subtitle,
							]
						);
					}
				}
				?>
				<p>Since there are no backlinks pointing at this page, you don’t have to redirect it. Feel free to simply delete it.</p>
			</li>
			<li>
				<p>Remove or update internal links</p>
				<p>Make sure to remove or update all your internal links pointing at this page.</p>
				<?php
				$view->show_part( 'action-parts/pages-linking', [ 'post_id' => $locals['post_id'] ] );
				?>
			</li>
		</ol>

	</div>
</div>
<?php
