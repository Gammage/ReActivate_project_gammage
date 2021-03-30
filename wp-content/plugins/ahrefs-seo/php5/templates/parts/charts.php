<?php

namespace ahrefs\AhrefsSeo;

?>
<!-- Charts -->
<div class="content-charts">
	<div class="content-chart chart-score">
		<div class="content-title">Performance Score</div>
		<div class="chart-wrap" id="charts_block_left">
			<?php
			Ahrefs_Seo_Charts::print_content_score_block();
			?>
		</div>
	</div>
	<div class="content-chart chart-actions">
		<div class="content-title">Posts & pages by suggested actions</div>
		<div class="chart-wrap">
			<div class="chart">
				<div id="charts_block_right">
				<?php
				Ahrefs_Seo_Charts::print_svg_donut_chart();
				?>
				</div>
			</div>
			<div class="content-details">
				<div class="content-item"  id="charts_block_right_legend">
				<?php
				Ahrefs_Seo_Charts::print_svg_donut_chart_legend();
				?>
				</div>
			</div>
			<div class="clear"></div>
		</div>
	</div>
</div>
<?php
