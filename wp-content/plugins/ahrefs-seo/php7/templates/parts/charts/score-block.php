<?php
declare(strict_types=1);
namespace ahrefs\AhrefsSeo;

$count       = Ahrefs_Seo_Data_Content::get_statuses_for_charts();
$in_progress = Ahrefs_Seo_Data_Content::get()->is_updating_now();
$total       = $count[ Ahrefs_Seo_Charts::CHART_WELL_PERFORMING ] + $count[ Ahrefs_Seo_Charts::CHART_UNDERPERFORMING ] + $count[ Ahrefs_Seo_Charts::CHART_DEADWEIGHT ];
// leave as is / total.
$score      = $total > 0 ? $count[ Ahrefs_Seo_Charts::CHART_WELL_PERFORMING ] / $total : 0;
$score      = round( 100 * $score ) / 100;
$score_data = $count[ Ahrefs_Seo_Charts::CHART_WELL_PERFORMING ] . '|' . $total;

$badge_text  = 'Excellent';
$badge_class = 'excellent';
if ( $score <= 0.3 ) {
	$badge_text  = 'Bad';
	$badge_class = 'bad';
} elseif ( $score <= 0.7 ) {
	$badge_text  = 'Medium';
	$badge_class = 'medium';
} elseif ( $score <= 0.9 ) {
	$badge_text  = 'Good';
	$badge_class = 'good';
}
if ( 0 === $total ) {
	if ( $in_progress ) {
		$badge_text  = 'Calculating';
		$badge_class = 'calculating';
	} else {
		$badge_text  = 'N/A';
		$badge_class = 'na';
	}
}
?>
<div class="chart small-bottom">
	<div class="bg">
		<div class="gray-bottom gray-bottom1">
			<div class="gray-pie gray-pie1"></div>
		</div>
		<div class="gray-bottom gray-bottom2">
			<div class="gray-pie gray-pie2"></div>
		</div>
		<div class="white-bottom">
			<div class="white-pie"></div>
		</div>

		<div class="oval">
			<div class="score-number" data-score="<?php echo esc_attr( $score_data ); ?>">
				<?php echo esc_html( (string) round( 100 * $score ) ); ?>
			</div>
		</div>
		<div class="line-3"></div>
		<div class="line-4"></div>
		<div class="line-5"></div>

		<div class="score-text <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_text ); ?></div>
	</div>
	<div class="score-legend">
		<?php echo esc_html( (string) $count[ Ahrefs_Seo_Charts::CHART_WELL_PERFORMING ] ); ?> well performing pages out of <?php echo esc_html( (string) $total ); ?>
	</div>
</div>
<div class="content-details">
	<div class="content-item">
		<p>
			This number estimates the strength of your website’s content on a 100-point scale.
			It considers the proportion of content that performs well as compared to content that needs to be updated or removed.
		</p>
	</div>
</div>
<div class="clear"></div>
<style type="text/css">
	<?php
	// sectors.
	$angle   = 0;
	$items   = 0;
	$sectors = [];
	$sum     = 0;

	// First chart.
	// gray segments.
	$min_angle = -126;
	$end_angle = 180 - 55;
	if ( $score > 0.005 && $score < 0.02 ) {
		$score = 0.02;
	}
	$sector = intval( ceil( ( $end_angle - $min_angle ) * ( 1 - $score ) ) );
	$angle  = $end_angle - $sector;

	if ( $sector < 0.01 ) {
		// no gray sectors.
		?>
		.content-charts .chart .bg .gray-bottom.gray-bottom1, .content-charts .chart .bg .gray-bottom.gray-bottom2 {
			display: none;
		}
		<?php
	} elseif ( $sector > 179 ) {
		// 2 pieces.
		?>
		.content-charts .chart .bg .gray-bottom.gray-bottom1 {
			transform: rotate(<?php echo intval( $angle ) . 'deg'; ?>);
		}
		.content-charts .chart .bg .gray-bottom.gray-bottom1 .gray-pie {
			transform: rotate(180deg);
		}
		.content-charts .chart .bg .gray-bottom.gray-bottom2 {
			transform: rotate(<?php echo intval( $angle + 180 - 1 ) . 'deg'; ?>);
		}
		.content-charts .chart .bg .gray-bottom.gray-bottom2 .gray-pie {
			transform: rotate(<?php echo intval( $sector - 180 ) . 'deg'; ?>);
		}
		<?php
	} else {
		// 1 piece.
		?>
		.content-charts .chart .bg .gray-bottom.gray-bottom1 {
			transform: rotate(<?php echo intval( $angle ) . 'deg'; ?>);
		}
		.content-charts .chart .bg .gray-bottom.gray-bottom1 .gray-pie {
			transform: rotate(<?php echo intval( $sector ) . 'deg'; ?>);
		}
		.content-charts .chart .bg .gray-bottom.gray-bottom2 {
			display: none;
		}
		<?php
	}
	?>
</style>
<?php
