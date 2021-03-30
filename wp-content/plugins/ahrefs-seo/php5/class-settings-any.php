<?php

namespace ahrefs\AhrefsSeo;

/**
 * Abstract class for settings.
 */
abstract class Settings_Any {

	/**
	 * Load options from request.
	 *
	 * @param Ahrefs_Seo_Screen $screen
	 * @return void
	 */
	abstract public function apply_options( Ahrefs_Seo_Screen $screen);
	/**
	 * Show options block.
	 *
	 * @param Ahrefs_Seo_Screen $screen
	 * @param Ahrefs_Seo_View   $view
	 * @return void
	 */
	abstract public function show_options( Ahrefs_Seo_Screen $screen, Ahrefs_Seo_View $view);
}
