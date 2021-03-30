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
				'position'       => 3,
				'unique-keyword' => $is_keyword_unique,
				'backlinks'      => $backlinks,
			]
		);
		?>
	</div>
	<div class="more-column-action">
		<div class="column-title">Recommended action</div>
		<p>This page is performing well in organic search. Great job! We suggest leaving the page as-is.</p>
	</div>
</div>
<?php
