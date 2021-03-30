<?php

namespace ahrefs\AhrefsSeo;

$locals           = Ahrefs_Seo_View::get_template_variables();
$content          = new Ahrefs_Seo_Content_Settings();
$content_schedule = new Content_Schedule();
$enabled          = $content_schedule->is_enabled();
$options          = $content_schedule->get_options();
$frequency        = (string) $options['frequency'];
$day_of_week      = (int) $options['day_of_week'];
$day_of_month     = (int) $options['day_of_month'];
$hour             = (int) $options['hour'];
$timezone         = (string) $options['timezone'];
function render_options( $current, array $items ) {
	foreach ( $items as $key => $title ) {
		?><option value="<?php echo esc_attr( $key ); ?>"
		<?php
		selected( $current, $key );
		?>
		>
		<?php
		echo esc_html( $title );
		?>
		</option>
		<?php
	}
}
?>


<hr class="hr-shadow"><a id="schedule"></a>
<div class="block-title">Schedule content audits</div>
<div class="block-text">
	Regularly check your website’s content to have all pages’ metrics, rankings and content suggestions updated.<br>
</div>
<div class="block-schedule-checkbox">
	<label><input type="checkbox" name="schedule_enabled" value="1"
	<?php
	checked( $enabled );
	?>
	>Run scheduled content audits</label>
	<input type="hidden" id="schedule_content_audits" value="1">
</div>
<div class="block-schedule-time">
	<div>
		<select name="schedule_frequency" id="schedule_frequency">
			<?php
			// same keys as Cron_Scheduled_Audit::cron_schedules_add_interval_new(), Content_Schedule::get_options() used.
			$values = [
				'ahrefs_daily'   => 'Daily',
				'ahrefs_weekly'  => 'Weekly',
				'ahrefs_monthly' => 'Monthly',
			];
			render_options( $frequency, $values );
			?>
		</select>
	</div>
	<div id="schedule_day_wrap" style="display: none;">
		<span id="schedule_each" style="display: none;">each</span>
		<select name="schedule_day_of_week" id="schedule_day_of_week" style="display: none;">
			<?php
			$values = [
				0 => 'Sunday',
				1 => 'Monday',
				2 => 'Tuesday',
				3 => 'Wednesday',
				4 => 'Thursday',
				5 => 'Friday',
				6 => 'Saturday',
			];
			render_options( "{$day_of_week}", $values );
			?>
		</select>
		<span class="schedule_every">every</span>
		<select name="schedule_day_of_month" id="schedule_day_of_month" style="display: none;">
			<?php
			$values = [];
			for ( $i = 1; $i <= 31; $i++ ) {
				$values[ $i ] = 1 === $i % 10 ? "{$i}st" : ( 2 === $i % 10 ? "{$i}nd" : ( 3 === $i % 10 ? "{$i}rd" : "{$i}th" ) );
			}
			render_options( "{$day_of_month}", $values );
			?>
		</select>
		<span class="schedule_every">of the month</span>
	</div>
	<div>
		<span>at</span>
		<select name="schedule_hour" class="margin-right">
			<?php
			$values = [];
			for ( $i = 0; $i <= 23; $i++ ) {
				$values[ $i ] = date( 'g:00 A', $i * 60 * 60 );
			}
			render_options( "{$hour}", $values );
			?>
		</select>
		<select name="schedule_timezone" class="schedule_timezone">
			<?php
			echo $content_schedule->filter_timezone_values( wp_timezone_choice( $timezone ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- this is html with option tags.
			?>
		</select>
	</div>
</div>
<!-- next audit in -->
<div>
	<?php
	$next     = $content_schedule->next_run_time();
	$time_now = time();
	if ( $enabled ) {
		?>
		<p>The next content audit run is scheduled in: 
		<?php
		echo esc_html( human_time_diff( $time_now, $next['time'] ) );
		?>
	.</p>
		<?php
	}
	?>
</div>
<?php
