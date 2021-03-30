<?php
declare(strict_types=1);

namespace ahrefs\AhrefsSeo;

/**
 * Abstract class for Screen with Table.
 */
abstract class Ahrefs_Seo_Screen_With_Table extends Ahrefs_Seo_Screen {

	/**
	 * Table embedded to screen.
	 *
	 * @var Ahrefs_Seo_Table|null
	 */
	protected $table;

	/**
	 * Set screen id of admin page for this screen.
	 * Register 'process_post_data' method as action.
	 * Initialize table.
	 *
	 * @param string $screen_id Current (WordPress') screen id.
	 */
	public function set_screen_id( string $screen_id ) : void {
		parent::set_screen_id( $screen_id );

		// initialize table here because we want to have screen options.
		add_action( 'load-' . $screen_id, [ $this, 'initialize_table' ] );
	}

	/**
	 * Register AJAX handlers
	 */
	public function register_ajax_handlers() : void {
		$this->register_table_handlers();
	}

	/**
	 * Get prefix for table.
	 *
	 * @return string
	 */
	abstract protected function get_ajax_table_prefix() : string;

	/**
	 * Create new table
	 *
	 * @return Ahrefs_Seo_Table
	 */
	abstract protected function new_table_instance() : Ahrefs_Seo_Table;

	/**
	 * Return exieting table or create new
	 *
	 * @return Ahrefs_Seo_Table
	 */
	public function initialize_table() : Ahrefs_Seo_Table {
		if ( is_null( $this->table ) ) {
			if ( ! isset( $GLOBALS['hook_suffix'] ) ) { // patch for notice (when called using AJAX): Undefined index: hook_suffix in wp-admin\includes\class-wp-screen.php .
				$GLOBALS['hook_suffix'] = ''; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}

			$this->table = $this->new_table_instance();
			$this->table->add_screen_options();
		}
		return $this->table;
	}

	/**
	 * Register ajax handlers for any screen with table.
	 * Actions for update table and initialize table.
	 */
	public function register_table_handlers() : void {
		$prefix = $this->get_ajax_table_prefix();

		add_action( "wp_ajax_{$prefix}_update", [ $this, 'ajax_table_update' ] ); // ahrefs_seo_table_content_update.
		add_action( "wp_ajax_{$prefix}_init", [ $this, 'ajax_table_init' ] ); // ahrefs_seo_table_content_init.
	}

	/**
	 * Print navigation and placeholder for future table
	 */
	abstract public function show_table_placeholder() : void;

	/**
	 * Ajax handler, echo ajax: table parts
	 */
	public function ajax_table_update() : void {
		if ( check_ajax_referer( Ahrefs_Seo_Table::ACTION ) && current_user_can( Ahrefs_Seo::CAPABILITY ) ) {
			if ( ! empty( $_REQUEST['screen_id'] ) ) { // may be POST or GET.
				set_current_screen( sanitize_key( $_REQUEST['screen_id'] ) ); // required for loading table using ajax.
			}

			$this->initialize_table()->ajax_response();
		}
	}

	/**
	 * Action wp_ajax for fetching the first time table structure
	 */
	public function ajax_table_init() : void {
		if ( check_ajax_referer( Ahrefs_Seo_Table::ACTION ) && current_user_can( Ahrefs_Seo::CAPABILITY ) ) {
			if ( ! empty( $_POST['screen_id'] ) ) {
				set_current_screen( sanitize_key( $_POST['screen_id'] ) ); // required for loading table using ajax.
			}

			$this->initialize_table()->prepare_items();

			ob_start();
			if ( ! is_null( $this->table ) ) {
				$this->table->display();
			}
			$display = ob_get_clean();

			wp_send_json_success( [ 'display' => $display ] );
		}
	}
}
