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
				'position'       => 20,
				'unique-keyword' => false,
				'backlinks'      => $backlinks,
			]
		);
		?>
	</div>
	<div class="more-column-action">
		<div class="column-title">Recommended action</div>
		<ol>
			<li>
				<p>Consolidate</p>
				<p>There are other articles on your blog targeting the same topic as this one.
				We suggest consolidating the content from all similar articles into the best performing one.</p>

				<?php
				$items = Ahrefs_Seo_Advisor::get()->find_relevant_top_performing_pages( $snapshot_id, $locals['post_id'], 3 );
				if ( ! is_null( $items ) ) {
					$subtitle = sprintf( _n( 'Top-performing page on the same topic', '%d Top-performing pages on the same topic', count( $items ) ), count( $items ) );
					$view->show_part(
						'action-parts/pages-list',
						[
							'items'    => $items,
							'subtitle' => $subtitle,
						]
					);
				}
				$items = Ahrefs_Seo_Advisor::get()->find_relevant_under_performing_pages( $snapshot_id, $locals['post_id'], 100 );
				if ( ! is_null( $items ) ) {
					$subtitle = sprintf( _n( '%d underperforming page that can be merged with the top-performing one', '%d underperforming pages that can be merged with the top-performing one', count( $items ) ), count( $items ) );
					$view->show_part(
						'action-parts/pages-list',
						[
							'items'    => $items,
							'subtitle' => $subtitle,
						]
					);
				}
				?>

			</li>
			<li>
				<p>Republish the post</p>
				<p>Change the publish or updated date of your article to the current date. This lets Google know that your content is fresh.</p>
			</li>
			<li>
				<p>Merge</p>
				<p>Add 301 redirects from poor performing articles to the republished one.</p>
				<p><a class="link-small" href="https://ahrefs.com/blog/301-redirects/" target="_blank">How to add 301 redirects →</a></p>
			</li>
		</ol>

	</div>
</div>
<?php
