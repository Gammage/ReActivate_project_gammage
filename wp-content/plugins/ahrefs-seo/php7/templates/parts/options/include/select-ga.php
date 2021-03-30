<?php
declare(strict_types=1);
namespace ahrefs\AhrefsSeo;

$locals    = Ahrefs_Seo_View::get_template_variables();
$analytics = Ahrefs_Seo_Analytics::get();

$ua_list        = $analytics->load_accounts_list();
$ua_id_selected = $analytics->get_ua_id();
$ua_name        = $analytics->get_ua_name();
$ua_url         = $analytics->get_ua_url();
?>
<div class="new-token-button">
	<label class="label" for="analytics_account">Google Analytics profile:</label>

	<select class="account" name="ua_id" id="analytics_account">
		<option value="" <?php selected( $ua_id_selected, '', true ); ?>>Please select</option>
		<?php
		foreach ( $ua_list as $account ) {
			$account_label = $account['label'];
			?>
			<optgroup label="<?php echo esc_attr( $account_label ); ?>">
				<?php

				if ( isset( $account['values'] ) ) {
					foreach ( $account['values'] as $profile_label => $items ) {
						?>
						<option value="<?php echo esc_attr( "group-$profile_label" ); ?>" disabled="disabled" class="ga-item-group">[<?php echo esc_html( $profile_label ); ?>]</option>
						<?php
						if ( isset( $items['views'] ) ) {
							foreach ( $items['views'] as $choice ) {
								$ua_id   = $choice['ua_id'];
								$website = $choice['website'];
								$label   = isset( $choice['view'] ) ? "view: {$choice['view']}" : ( isset( $choice['stream'] ) ? "stream: {$choice['stream']}" : 'Default' );
								$label  .= " | site: {$website}";
								?>
								<option value="<?php echo esc_attr( $ua_id ); ?>"
									<?php selected( $ua_id_selected, $ua_id ); ?>
									data-url="<?php echo esc_attr( $website ); ?>"
									>&nbsp;&nbsp;&nbsp;&nbsp;<?php echo esc_html( $label ); ?></option>
								<?php
							}
						}
						if ( isset( $items['streams'] ) ) { // single choice for all streams.
							$sites = [];
							$ua_id = $items['streams'][0]['ua_id']; // ua_id is same for all streams.
							foreach ( $items['streams'] as $choice ) {
								$website = $choice['website'];
								$sites[] = $website;
							}
							$sites = array_unique( $sites );
							sort( $sites );
							$label = count( $sites ) ? sprintf( _n( 'site: %s', 'sites: %s', count( $sites ) ), implode( ' | ', $sites ) ) : 'Default';
							?>
							<option value="<?php echo esc_attr( $ua_id ); ?>"
								<?php selected( $ua_id_selected, $ua_id ); ?>
								data-url="<?php echo esc_attr( implode( '|', $sites ) ); ?>"
								>&nbsp;&nbsp;&nbsp;&nbsp;<?php echo esc_html( $label ); ?></option>
							<?php
						}
					}
				}
				?>
			</optgroup>
			<?php
		}
		?>
	</select>

	<input type="hidden" id="ua_name" name="ua_name" value="<?php echo esc_attr( $ua_name ); ?>">
	<input type="hidden" id="ua_url" name="ua_url" value="<?php echo esc_attr( $ua_url ); ?>">
</div>
<?php