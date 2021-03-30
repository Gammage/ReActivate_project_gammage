<?php
declare(strict_types=1);
namespace ahrefs\AhrefsSeo;

$content = new Ahrefs_Seo_Content_Settings();

$pages_disabled = $content->is_disabled_for_pages();
$posts_disabled = $content->is_disabled_for_posts();


$pages_list = $content->get_pages_list();
// By default, we should tick all the boxes for 'Pages' & 'Posts' on the set up wizard page.
$posts_checked = $content->get_posts_categories_checked(); // load from options.
if ( is_null( $posts_checked ) ) {
	$posts_checked = get_categories(
		[
			'fields'     => 'ids',
			'hide_empty' => false,
		]
	);
}

$page_ids = $content->get_pages_checked();
if ( is_null( $page_ids ) ) {
	$page_ids       = []; // check nothing by default.
	$pages_disabled = true;
}
?>

<div class="block-title">Scope of audit</div>

<div class="block-text">
	Uncheck the categories below that don't need organic search performance improvement, like "Contacts" or "Privacy Policy". This will give you a more accurate analysis and better recommendations.
</div>

<div class="columns-wrap">
	<div class="one-column">
		<ul class="checkbox-group">
			<li>
				<input type="checkbox" id="posts_enabled" value="1" name="posts_enabled" class="checkbox-main" <?php checked( ! $posts_disabled ); ?>>
				<label for="posts_enabled">Posts</label>

				<ul class="subitems">
				<?php wp_category_checklist( 0, 0, $posts_checked ); ?>
				</ul>
			</li>
		</ul>
	</div>
	<div class="one-column">
		<ul class="checkbox-group">
			<li>
				<input type="checkbox" id="pages_enabled" value="1" name="pages_enabled" class="checkbox-main" <?php checked( ! $pages_disabled ); ?>>
				<label for="pages_enabled">Pages</label>
				<?php
				if ( ! empty( $pages_list ) ) {
					?>
					<ul class="subitems">
						<?php
						$i = 0;
						foreach ( $pages_list as $id => $title ) {
							if ( 5 === ++$i ) {
								?>
								<li>
									<label class="selectit">
										<a href="#" class="scope-show-more">Show more</a>
									</label>
								</li>
								<?php
							}
							?>
							<li
							<?php
							if ( $i >= 5 ) {
								?>
								class="hidden"<?php } ?>>
								<label class="selectit">
									<input type="checkbox" name="pages[]" value="<?php echo esc_attr( "$id" ); ?>" id="<?php echo esc_attr( 'page_' . $id ); ?>" <?php checked( in_array( "$id", $page_ids, true ) ); ?><label for="<?php echo esc_attr( 'page_' . $id ); ?>"><?php echo esc_html( $title ); ?></label>
								</label>
							</li>
							<?php
						}
						?>
					</ul>
					<?php
				}
				?>
			</li>
		</ul>
	</div>
</div>
