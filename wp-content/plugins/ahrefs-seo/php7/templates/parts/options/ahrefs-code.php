<?php
declare(strict_types=1);
namespace ahrefs\AhrefsSeo;

$locals       = Ahrefs_Seo_View::get_template_variables();
$ahrefs_token = Ahrefs_Seo_Token::get();

$token     = $ahrefs_token->token_get();
$link_user = $ahrefs_token->token_link();
$link_new  = $ahrefs_token->token_free_link();
?>
<form method="post" action="" class="ahrefs-seo-wizard ahrefs-token">
	<input type="hidden" name="ahrefs_step" value="1">
	<?php
	if ( isset( $locals['page_nonce'] ) ) {
		wp_nonce_field( $locals['page_nonce'] );
	}
	?>
	<div class="card-item">
		<div class="image-wrap">
			<img src="<?php echo esc_attr( AHREFS_SEO_IMAGES_URL ); ?>ahrefs-wp-plugin-2-o.jpg"
				srcset="<?php echo esc_attr( AHREFS_SEO_IMAGES_URL ); ?>ahrefs-wp-plugin-2-o@2x.jpg 2x,
				<?php echo esc_attr( AHREFS_SEO_IMAGES_URL ); ?>ahrefs-wp-plugin-2-o@3x.jpg 3x"
				class="ahrefs-wp-plugin_2x">
		</div>

		<div class="two-rows-wrap">
			<div class="single-row-wrap">
				<p class="help-title">I’m new to Ahrefs | I'm a Webmaster Tools User</p>
				<p class="help">You can still perform content audits, but you will be required to authorise your token every few weeks. Just click the link below and connect your WordPress dashboard to Ahrefs.</p>

				<a href="<?php echo esc_attr( $link_new ); ?>" target="_blank" class="get-code-button" id="ahrefs_get">
					<span class="text">Get my authorization code</span>
					<img src="<?php echo esc_attr( AHREFS_SEO_IMAGES_URL . 'link-open.svg' ); ?>" class="icon">
				</a>
			</div>

			<div class="single-row-wrap">
				<p class="help-title">I’m a paying Ahrefs customer</p>
				<p class="help">Track new backlinks, monitor your backlink growth and perform content audits, right in your WordPress dashboard. Just click the link below and connect your Ahrefs account with this plugin.</p>

				<a href="<?php echo esc_attr( $link_user ); ?>" target="_blank" class="get-code-button" id="ahrefs_get">
					<span class="text">Get my authorization code</span>
					<img src="<?php echo esc_attr( AHREFS_SEO_IMAGES_URL . 'link-open.svg' ); ?>" class="icon">
				</a>
			</div>
		</div>

		<div class="new-token-button">
			<label class="label">Authorization code</label>
			<div class="input_button">
				<input type="text" class="input-input-default-s-default <?php if ( ! empty( $locals['error'] ) ) { // phpcs:ignore Squiz.ControlStructures.ControlSignature.NewlineAfterOpenBrace ?> error<?php } ?>"
				value="<?php echo esc_attr( $token ); ?>" name="ahrefs_code" id="ahrefs_code">
				<input type="submit" name="submit" id="submit" class="button button-primary" value="Connect with Ahrefs">
			</div>
			<div class="ahrefs-seo-error">
				<?php
				if ( '' !== $locals['error'] ) {
					echo esc_html( $locals['error'] );
				}
				?>
			</div>
		</div>
	</div>

	<?php if ( ! empty( $locals['show_button'] ) ) { ?>
		<div class="button-wrap">
			<input type="submit" name="submit" id="submit" class="button button-primary button-hero" value="Continue">
		</div>
	<?php } ?>
</form>
