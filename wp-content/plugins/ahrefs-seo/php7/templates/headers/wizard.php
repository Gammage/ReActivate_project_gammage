<?php
/**
 * Setup Wizard header template
 *
 * @var string $token_link
 * @var string $token
 * @var bool $is_valid
 * @var string $error
 * @var int $step
 */

declare(strict_types=1);
namespace ahrefs\AhrefsSeo;

$locals = Ahrefs_Seo_View::get_template_variables();

function step_header( int $step, int $current_step, string $title ) : void {
	$href    = '';
	$classes = [ 'group', "group-$step" ];
	if ( $current_step > $step ) {
		$classes[] = 'finished';
		$href      = add_query_arg(
			[
				'page' => Ahrefs_Seo::SLUG,
				'step' => $step,
			],
			admin_url( 'admin.php' )
		);
	}
	if ( $current_step < $step ) {
		$classes[] = 'inactive';
	}
	if ( $href ) {
		printf( '<a class="%s" href="%s">', esc_attr( implode( ' ', $classes ) ), esc_attr( $href ) );
	} else {
		printf( '<div class="%s">', esc_attr( implode( ' ', $classes ) ) );
	}
	?>
	<span class="icon-ok"><span class="oval"><span class="number"><?php echo esc_html( "$step" ); ?></span><span class="icon">&#10004;</span></span></span>
	<span class="account"><?php echo esc_html( $title ); ?></span>
	<?php

	echo $href ? '</a>' : '</div>';
}

?>
<div id="ahrefs_seo_screen" class="setup-wizard <?php echo isset( $locals['header_class'] ) ? esc_attr( '' . implode( ' ', $locals['header_class'] ) ) : ''; ?>">

<div class="ahrefs-header">

	<div>
		<img src="<?php echo esc_attr( AHREFS_SEO_IMAGES_URL . 'logo-content-audit.svg' ); ?>" class="logo">
	</div>
	<div class="steps">
		<?php
		step_header( 1, $locals['step'], 'Ahrefs account' );
		step_header( 2, $locals['step'], 'Google account' );
		step_header( 3, $locals['step'], 'Content audit' );
		?>
	</div>

	<div class="right">
	</div>
</div>

<h1><?php echo esc_html( $locals['title'] ); ?></h1>
<?php
// Check for compatibility issues.
if ( ! Ahrefs_Seo_Compatibility::quick_compatibility_check() ) {
	Ahrefs_Seo::get()->get_view()->show_part( 'content-tips/compatibility' );
}
