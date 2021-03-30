<?php
declare(strict_types=1);
namespace ahrefs\AhrefsSeo;

$locals    = Ahrefs_Seo_View::get_template_variables();
$view      = Ahrefs_Seo::get()->get_view();
$backlinks = Ahrefs_Seo_Data_Content::get()->content_get_backlinks_for_post( $locals['post_id'] );

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
				'unique-keyword' => $is_keyword_unique,
				'backlinks'      => $backlinks,
			]
		);
		?>
	</div>
	<div class="more-column-action">
		<div class="column-title">Recommended action</div>
		<ol>
			<li>
				<p>Match search intent</p>
				<p>This article is targeting a unique topic, but is currently underperforming.
				Check the SERPs to see if your post is aligned with your target keyword’s search intent.</p>
				<p><a class="link-small" href="https://ahrefs.com/blog/search-intent/" target="_blank">How to optimize for search intent →</a></p>
			</li>
			<li>
				<p>Update</p>
				<p>Optimize your post by rewriting or updating it with new and unique content.</p>
				<p><a class="link-small" href="https://ahrefs.com/blog/republishing-content/" target="_blank">How to update your content →</a></p>
			</li>
			<li>
				<p>Republish the post</p>
				<p>When you’ve updated your post with new content, change the publish or updated date of your article to the current date. This lets Google know your content is fresh.</p>
			</li>
			<li>
				<p>Promote the post</p>
				<p>Re-promote the article. You can also consider doing some outreach to get more backlinks.</p>
				<p><a class="link-small" href="https://www.youtube.com/watch?v=PoVYweKH4ck" target="_blank">Content promotion checklist →</a></p>
				<p><a class="link-small" href="https://ahrefs.com/blog/how-to-get-backlinks/" target="_blank">How to get more backlinks →</a></p>
			</li>
		</ol>

	</div>
</div>
