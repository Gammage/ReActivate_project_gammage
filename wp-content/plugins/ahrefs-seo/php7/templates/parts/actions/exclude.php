<?php
declare(strict_types=1);
namespace ahrefs\AhrefsSeo;

$locals    = Ahrefs_Seo_View::get_template_variables();
$view      = Ahrefs_Seo::get()->get_view();
$backlinks = Ahrefs_Seo_Data_Content::get()->content_get_backlinks_for_post( $locals['post_id'] );
$median    = floatval( ( new Snapshot() )->get_traffic_median( Ahrefs_Seo_Data_Content::get()->snapshot_context_get() ) );
?>
<div class="more-wrap">
	<div class="more-column-performance">
		<div class="column-title">Performance</div>
		<?php
		$view->show_part(
			'action-parts/pages-performance',
			[
				'position'       => 21,
				'decent-traffic' => true,
			]
		);
		?>
	</div>
	<div class="more-column-action">
		<div class="column-title">Recommended action</div>
			<p>According to Google Analytics, pages across your website gets a median of <?php echo esc_html( number_format( $median, 1 ) ); ?> traffic every month.</p>
			<p>This page is not ranking high on any keyword, but it is in the top 50th percentile of all pages on your website when it comes to traffic.</p>
			<p>We suggest excluding this article from your content audit so that it doesn’t affect your score.</p>
		<div class="with-button"><a href="#" class="button action-stop"><span></span>Exclude from audit</a></div>
	</div>
</div>
<?php

