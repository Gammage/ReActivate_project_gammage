<?php
declare(strict_types=1);
namespace ahrefs\AhrefsSeo;

$locals = Ahrefs_Seo_View::get_template_variables();

/*
(1) If we have at least one cached suggestion:
- show cached version of suggested keywords in the popup immediately;
- no need to show loader;
- if received suggestions are different from existing: replace all items at the table with updated suggestions.
(2) But what to do if we have no cached suggestions exists (when we tried to load it, but nothing found, example: any new short post)?
- show popup dialog with empty keywords table and description as for no keywords found ("We couldn’t find any keyword recommendations for (post title) because the content length is too short. Try improving the content by increasing the amount of words or just go ahead and add your own keywords.").
- show loader.
- If new search will return something - update description and keywords table.
(3) Last case, when this post does not have cached suggestions and we do not run research in the past (example: user added a lot of posts to analysis and clicked on "Change keywords" link for one of them).
- show popup dialog with description is: ‘We are generating a list of recommended keywords for [post name].’ and empty keywords table.
- show loader
- update description and suggested keywords table with new data.
*/

$post = get_post( $locals['post_id'] );
if ( ! is_null( $post ) ) {

	/** @var \WP_Post $post */
	$post_title  = $post->post_title;
	$url         = (string) get_permalink( $post );
	$data        = Ahrefs_Seo_Keywords::get()->get_suggestions( Ahrefs_Seo_Data_Content::snapshot_context_get(), $locals['post_id'], true );
	$is_approved = Ahrefs_Seo_Data_Content::get()->is_keyword_approved( $locals['post_id'] );
	$post_url    = get_permalink( $post );

	$link_to_google_settings = add_query_arg(
		[
			'page' => Ahrefs_Seo::SLUG_SETTINGS,
			'tab'  => 'analytics',
		],
		admin_url( 'admin.php' )
	);
	?>



	<div class="ahrefs-seo-modal-keywords" id="ahrefs_seo_modal_keywords" data-id="<?php echo esc_attr( $locals['post_id'] ); ?>">
		<div class="keyword-header">Select target keyword for <a class="keyword-post-title" href="<?php echo esc_attr( (string) $post_url ); ?>"><?php echo esc_html( $post_title ); ?></a></div>

		<?php
		( new Content_Tips_Popup() )->maybe_show_tip();
		?>
		<div class="keyword-save-error"></div>
		<div class="keyword-table-wrap">
			<table id="keyword_results" class="keyword-results-table" style="width:100%" data-id="<?php echo esc_attr( $locals['post_id'] ); ?>">
			</table>
			<div class="row-loader loader-transparent loader_suggested_keywords" id="loader_suggested_keywords"><div class="loader"></div></div>
			<div class="keywords-buttons">
				<a href="#" class="button button-hero button-primary" id="ahrefs_seo_keyword_submit">Apply</a>
				<a href="#" class="button button-hero button-cancel" id="ahrefs_seo_keyword_cancel">Cancel</a>
			</div>

		</div>

		<script type="text/javascript">
			console.log('loaded');
			content.keyword_data_set = <?php echo wp_json_encode( $data['keywords'] ); ?>;
			content.keyword_data_total_clicks = <?php echo wp_json_encode( $data['total_clicks'] ); ?>;
			content.keyword_data_total_impr = <?php echo wp_json_encode( $data['total_impr'] ); ?>;
			content.keyword_data_not_approved = <?php echo wp_json_encode( ! $is_approved ); ?>;

			content.keyword_popup_update_table(); // initialize table with existing suggestions.
			content.keyword_popup_update_suggestions( <?php echo wp_json_encode( $locals['post_id'] ); ?> ); // run update for new suggestions.
		</script>
	</div>
	<?php
	// show errors inside popup dialog.
	if ( ! is_null( $data['errors'] ) ) {
		?>
		<script type="text/javascript">
			content.keyword_show_error( <?php echo wp_json_encode( $data['errors'] ); ?> );
		</script>
		<?php
	}
} else {
	( Message::create(
		[
			'type'    => 'error-single',
			'title'   => '',
			'message' => 'This post cannot be found. It is possible that you’ve archived the post or changed the post ID. Please reload the page & try again.',
		]
	) )->show();
	?>
	<script type="text/javascript">
		jQuery( '#TB_ajaxContent' ).css( 'height','120px' );
	</script>
	<?php
}
