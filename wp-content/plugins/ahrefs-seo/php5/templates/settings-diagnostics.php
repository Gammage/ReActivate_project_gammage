<?php

namespace ahrefs\AhrefsSeo;

$locals            = Ahrefs_Seo_View::get_template_variables();
$view              = Ahrefs_Seo::get()->get_view();
$locals['enabled'] = Ahrefs_Seo::allow_reports();
?>

<form method="post" action="" class="ahrefs-seo-settings">
	<?php
	if ( isset( $locals['page_nonce'] ) ) {
		wp_nonce_field( $locals['page_nonce'] );
	}
	?>

	<div class="card-item card-notifications">
		<?php
		$view->show_part( 'options/diagnostics', $locals );
		?>
	</div>

	<div class="button-wrap">
		<a href="#" class="button button-hero button-primary" id="ahrefs_diagnostics_submit">Save</a>
	</div>
</form>
<?php
