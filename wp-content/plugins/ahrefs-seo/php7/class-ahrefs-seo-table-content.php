<?php
declare(strict_types=1);

namespace ahrefs\AhrefsSeo;

/**
 * Implement table for Content audit screen.
 */
class Ahrefs_Seo_Table_Content extends Ahrefs_Seo_Table {

	private const DEFAULT_TAB = '';

	/**
	 * Content class instance.
	 *
	 * @var Ahrefs_Seo_Data_Content
	 */
	private $content;

	/**
	 * Current author id.
	 *
	 * @var int
	 */
	private $author = 0;
	/**
	 * Current tab, each tab has one or more post groups by action
	 *
	 * @var string
	 */
	private $tab = '';
	/**
	 * Current date filter value, YYYYMM or empty string.
	 *
	 * @var string
	 */
	private $date = '';
	/**
	 * Current category id (from Categories filter) or empty string.
	 *
	 * @var string
	 */
	private $category = '';
	/**
	 * Current keywords type approved ('1') or not ('0') or empty string.
	 *
	 * @var string
	 */
	private $keywords = '';
	/**
	 * Current post type (from Categories filter) or empty string.
	 *
	 * @var string
	 */
	private $post_type = '';
	/**
	 * Current page id (from Categories filter) or 0.
	 *
	 * @var int
	 */
	private $page_id = 0;
	/**
	 * Return results for those post_id only.
	 *
	 * @var int[]
	 */
	private $ids = [];

	/**
	 * Default orderby value.
	 *
	 * @var string
	 */
	protected $default_orderby = 'created';

	/**
	 * Tabs at the page
	 *
	 * @var array<string, string>
	 */
	protected $tabs = [
		''                 => 'All analyzed',
		'well-performing'  => 'Well-performing',
		'under-performing' => 'Under-performing',
		'deadweight'       => 'Deadweight',
		'excluded'         => 'Excluded',
	];

	public function __construct( $args = [] ) {
		parent::__construct(
			[
				'plural' => 'backlinks',
				'screen' => is_array( $args ) && isset( $args['screen'] ) ? $args['screen'] : null,
				'ajax'   => true,
			]
		);
		$this->content = Ahrefs_Seo_Data_Content::get();

		// phpcs:disable WordPress.CSRF.NonceVerification.NoNonceVerification,WordPress.VIP.SuperGlobalInputUsage.AccessDetected,WordPress.Security.NonceVerification.Recommended -- we create tables on content audit page and load GET parameters, it must work even without nonce.
		// fill from request.
		$this->date     = isset( $_REQUEST['m'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['m'] ) ) : '';
		$cat_value      = isset( $_REQUEST['cat'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['cat'] ) ) : '';
		$this->tab      = isset( $_REQUEST['tab'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['tab'] ) ) : self::DEFAULT_TAB;
		$this->keywords = isset( $_REQUEST['keywords'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['keywords'] ) ) : '';
		$this->author   = isset( $_REQUEST['author'] ) ? absint( $_REQUEST['author'] ) : 0;
		// phpcs:enable WordPress.CSRF.NonceVerification.NoNonceVerification,WordPress.VIP.SuperGlobalInputUsage.AccessDetected,WordPress.Security.NonceVerification.Recommended

		if ( ! isset( $this->tabs[ $this->tab ] ) ) {
			$this->tab = self::DEFAULT_TAB;
		}

		$name  = '';
		$value = $cat_value;
		// parse cat_value at "cat-id", "page-id" or "0" - for all categories.
		if ( false !== strpos( $cat_value, '-' ) ) {
			list( $name, $value ) = explode( '-', $cat_value, 2 );
		}
		switch ( $name ) {
			case 'cat':
				$this->post_type = 'post'; // "cat-0".
				if ( ! empty( $value ) ) {
					$this->category = $value; // "cat-xx".
				} else {
					$this->post_type = 'post'; // "cat-0".
				}
				break;
			case 'page':
				if ( ! empty( $value ) ) {
					$this->page_id = absint( $value ); // "page-xx".
				} else {
					$this->post_type = 'page'; // "page-0".
				}
				break;
		}
		add_filter( 'hidden_columns', [ $this, 'hidden_columns_filter' ], 10, 3 );
	}

	/**
	 * Filter for default columns visibility.
	 *
	 * @param string[]   $hidden An array of hidden columns.
	 * @param \WP_Screen $screen WP_Screen object of the current screen.
	 * @param bool       $use_defaults Whether to show the default columns.
	 * @return string[]
	 */
	public function hidden_columns_filter( $hidden, $screen, $use_defaults = false ) {
		// do not define parameter types for filter function.
		if ( $use_defaults ) {
			if ( $this->screen->id === $screen->id ) {
				$hidden[] = 'categories'; // do not show categories by default.
				$hidden[] = 'author'; // do not show author by default.
			}
		}
		return $hidden;
	}

	/**
	 * Get sortable culumns
	 *
	 * @return array<string, array<string|bool>>
	 */
	protected function get_sortable_columns() : array {
		$result = array(
			'title'     => array( 'title', false ),
			'keyword'   => array( 'keyword', false ),
			'position'  => array( 'position', false ),
			'total'     => array( 'total', false ),
			'organic'   => array( 'organic', false ),
			'backlinks' => array( 'backlinks', false ),
			'date'      => array( 'created', false ),
			'action'    => array( 'action', false ),
		);
		return $result;
	}

	/**
	 * Get columns
	 *
	 * @return array<string, string>
	 */
	public function get_columns() : array {
		$settings_content   = add_query_arg(
			[
				'page' => Ahrefs_Seo::SLUG_SETTINGS,
				'tab'  => 'content',
			],
			admin_url( 'admin.php' )
		);
		$settings_analytics = add_query_arg(
			[
				'page' => Ahrefs_Seo::SLUG_SETTINGS,
				'tab'  => 'analytics',
			],
			admin_url( 'admin.php' )
		);
		$waiting_weeks      = $this->content->get_waiting_weeks();
		$columns            = [
			'cb'         => '<input type="checkbox" />',
			'title'      => 'Title',
			'author'     => 'Author',
			'keyword'    => 'Target Keywords',
			'categories' => 'Categories',
			'position'   => 'Position',
			'total'      => sprintf( '<span title="This metric is retrieved from your Google Analytics account. It is the monthly average traffic from all sources to this page, acquired in the last %d weeks.">Total traffic</span>', $waiting_weeks ),
			'organic'    => sprintf( '<span title="This metric is retrieved from your Google Analytics account. It includes only monthly average traffic from organic search to this page, acquired in the last %d weeks.">Organic traffic</span>', $waiting_weeks ),
			'backlinks'  => '<span title="How many links point to your target in total. Not to be confused with the number of pages linking to your target, as a single page can give multiple backlinks.">Backlinks</span>',
			'date'       => 'Date',
			'action'     => 'excluded' !== $this->tab
			? sprintf( '<span class="show_tooltip" title="" data-tooltip="Recommended based on the traffic, backlinks & waiting time thresholds you\'ve set in the <a href=\'%s\'>content audit settings.</a>">Suggestion</span>', esc_attr( $settings_content ) )
			: '<span class="show_tooltip" title="" data-tooltip="The reason for post being excluded or not analyzed.">Reason</span>',
			// 'more'       => '<span class="vers" title="' . esc_attr__( 'More details' ) . '"><span class="screen-reader-text">' . __( 'More details' ) . '</span></span>',
		];

		return $columns;
	}

	/**
	 * Generates content for a single row of the table
	 *
	 * @param \stdClass $item The current item.
	 * @return void
	 */
	public function single_row( $item ) {
		// Note: can not define type of parameters, because not defined in parent class.
		echo '<tr class="content-item">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Display checkbox column
	 *
	 * @param \stdClass $item Item.
	 */
	protected function column_cb( $item ) : void {
		// Note: can not define type of parameters, because not defined in parent class.
		printf(
			'<input type="checkbox" name="link[]" value="%s" data-id="%d" data-ver="%d" />',
			absint( $item->post_id ),
			absint( $item->post_id ),
			intval( $item->ver )
		);
	}

	/**
	 * Display title column
	 *
	 * @param \stdClass $item Item.
	 */
	protected function column_title( \stdClass $item ) : void {
		if ( current_user_can( 'edit_post', $item->post_id ) ) {
			// post edit link.
			printf(
				'<a href="%s" aria-label="%s">%s</a>',
				esc_attr( (string) get_edit_post_link( $item->post_id ) ),
				/* translators: %s: Post title. */
				esc_attr( sprintf( __( '&#8220;%s&#8221; (Edit)' ), $item->title ) ),
				esc_html( $item->title )
			);
		} else {
			// just a title.
			echo esc_html( $item->title ?: '(Empty title)' );
		}
	}

	/**
	 * Display author column
	 *
	 * @param \stdClass $item Item.
	 */
	protected function column_author( \stdClass $item ) : void {
		$link = add_query_arg(
			[
				'page'     => Ahrefs_Seo::SLUG_CONTENT,
				'author'   => get_the_author_meta( 'ID', $item->author ),
				'tab'      => $this->tab,
				'keywords' => $this->keywords,
			],
			admin_url( 'admin.php' )
		);
		?>
		<a href="<?php echo esc_attr( $link ); ?>" class="author-link"><?php echo esc_html( get_the_author_meta( 'display_name', $item->author ) ); ?></a>
		<?php
	}

	/**
	 * Display target keyword column
	 *
	 * @param \stdClass $item Item.
	 */
	protected function column_keyword( \stdClass $item ) : void {
		?>
		<div class="content-post-keyword">
			<?php
			if ( is_null( $item->keyword ) || '' === $item->keyword ) {
				$this->some_empty_message();
			} else {
				echo esc_html( $item->keyword );
			}
			?>
		</div>
		<?php
		if ( ! $item->is_approved_keyword && empty( $item->keyword ) ) {
			?>
		<a href="#" class="badge-keyword badge-keyword-empty">NO KEYWORD DETECTED</a>
			<?php
		} elseif ( $item->is_approved_keyword ) {
			?>
		<a href="#" class="badge-keyword badge-keyword-approved">✓ APPROVED</a>
			<?php
		} elseif ( ! is_null( $item->keyword ) && '' !== $item->keyword ) {
			?>
		<a href="#" class="badge-keyword badge-keyword-suggested">SUGGESTED KEYWORD</a>
			<?php
		}
	}

	/**
	 * Display categories column
	 *
	 * @param \stdClass $item Item.
	 */
	protected function column_categories( \stdClass $item ) : void {
		if ( ! empty( $item->categories ) ) {
			sort( $item->categories );
			echo implode( ', ', $item->categories ); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped -- $item->categories contains html links.
		}

		$post_type = strtoupper( $item->post_type );
		$edit_link = get_edit_post_link( $item->post_id );
		if ( $edit_link ) {
			?>
			<a href="<?php echo esc_attr( $edit_link ); ?>" class="content-post-button" target="_blank"><?php echo esc_html( $post_type ); ?></a>
			<?php
		}
	}

	/**
	 * Display date column
	 *
	 * @param \stdClass $item Item.
	 */
	protected function column_date( \stdClass $item ) : void {
		$date = $item->created;
		echo esc_html( $date );
	}

	/**
	 * Display total traffic column
	 *
	 * @param \stdClass $item Item.
	 */
	protected function column_total( \stdClass $item ) : void {
		if ( ! is_null( $item->total ) ) {
			if ( intval( $item->total ) >= 0 ) {
				if ( defined( 'AHREFS_SEO_NO_GA' ) && AHREFS_SEO_NO_GA ) {
					$this->some_empty_message();
				} else {
					echo esc_html( $item->total );
				}
			} else {
				$this->some_error_message();
			}
		} else {
				$this->some_empty_message();
		}
	}

	/**
	 * Display Position column
	 *
	 * @param \stdClass $item Item.
	 */
	protected function column_position( \stdClass $item ) : void {
		if ( ! is_null( $item->position ) ) {
			$position = floatval( $item->position );
			if ( $position >= 0 ) {
				if ( $position < Ahrefs_Seo_Data_Content::POSITION_MAX - 1 ) {
					$value = ( round( 10 * $position ) ) / 10;
					echo esc_html( sprintf( '%.1f', $value ) );
				} else { // position not found.
					$this->some_empty_message();
				}
			} else {
				$this->some_error_message();
			}
		} else {
				$this->some_empty_message();
		}
	}

	/**
	 * Display Organic traffic column
	 *
	 * @param \stdClass $item Item.
	 */
	protected function column_organic( \stdClass $item ) : void {
		if ( ! is_null( $item->organic ) ) {
			if ( intval( $item->organic ) >= 0 ) {
				if ( defined( 'AHREFS_SEO_NO_GA' ) && AHREFS_SEO_NO_GA ) {
					$this->some_empty_message();
				} else {
					echo esc_html( $item->organic );
				}
			} else {
				$this->some_error_message();
			}
		} else {
				$this->some_empty_message();
		}
	}

	/**
	 * Display Backlinks column
	 *
	 * @param \stdClass $item Item.
	 */
	protected function column_backlinks( \stdClass $item ) : void {
		if ( ! is_null( $item->backlinks ) ) {
			if ( intval( $item->backlinks ) >= 0 ) {
				echo esc_html( $item->backlinks );
			} else {
				$this->some_error_message();
			}
		} else {
				$this->some_empty_message();
		}
	}

	/**
	 * Display Suggested action column
	 *
	 * @param \stdClass $item Item.
	 */
	protected function column_action( \stdClass $item ) : void {
		$action = $item->action ?? Ahrefs_Seo_Data_Content::ACTION4_ADDED_SINCE_LAST; // Items never added (action is null) to content audit will have ACTION4_ADDED_SINCE_LAST.

		$title = str_replace( '_', ' ', ucfirst( $action ) );
		switch ( $action ) {
			case Ahrefs_Seo_Data_Content::ACTION4_ADDED_SINCE_LAST:
				$title = 'Added since last audit';
				break;
			case Ahrefs_Seo_Data_Content::ACTION4_NOINDEX_PAGE:
				$title = 'Noindex page';
				break;
			case Ahrefs_Seo_Data_Content::ACTION4_MANUALLY_EXCLUDED:
				$title = 'Manually excluded';
				break;
			case Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE:
			case Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE_INITIAL:
			case Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE_ANALYZING:
				$title = 'Out of scope';
				break;
			case Ahrefs_Seo_Data_Content::ACTION4_NEWLY_PUBLISHED:
				$title = 'Newly published';
				break;
			case Ahrefs_Seo_Data_Content::ACTION4_ERROR_ANALYZING:
				$title = 'Error analyzing';
				break;
			case Ahrefs_Seo_Data_Content::ACTION4_DO_NOTHING:
				$title = 'Do nothing';
				break;
			case Ahrefs_Seo_Data_Content::ACTION4_UPDATE_YELLOW:
				$title = 'Update';
				break;
			case Ahrefs_Seo_Data_Content::ACTION4_MERGE:
				$title = 'Merge';
				break;
			case Ahrefs_Seo_Data_Content::ACTION4_EXCLUDE:
				$title = 'Exclude';
				break;
			case Ahrefs_Seo_Data_Content::ACTION4_UPDATE_ORANGE:
				$title = 'Update';
				break;
			case Ahrefs_Seo_Data_Content::ACTION4_DELETE:
				$title = 'Delete';
				break;
			case Ahrefs_Seo_Data_Content::ACTION4_ANALYZING:
			case Ahrefs_Seo_Data_Content::ACTION4_ANALYZING_INITIAL:
			case Ahrefs_Seo_Data_Content::ACTION4_ANALYZING_FINAL:
				$title = 'Analyzing...';
				break;
		}
		?>
		<a class="status-action <?php echo esc_attr( "status-$action" ); ?> content-more-button"><?php echo esc_html( $title ); ?> <span src="<?php echo esc_attr( AHREFS_SEO_IMAGES_URL . 'arrow-down.svg' ); ?>" class="arrow-down"></span></a>
		<?php
	}

	/**
	 * Display More details column
	 *
	 * @param \stdClass $item Item.
	 */
	protected function column_more( \stdClass $item ) : void {
		?>
		<a href="#" class="content-more-button">
		<img src="<?php echo esc_attr( AHREFS_SEO_IMAGES_URL . 'arrow-down.svg' ); ?>" class="arrow-down">
		<?php
	}

	/**
	 * Display error message for total, organic traffic, backlinks column when it has value < 0
	 *
	 * @return void
	 */
	protected function some_error_message() : void {
		?>
		<span class="some-error" title="There was an error retrieving data.">?</span>
		<?php
	}
	/**
	 * Display empty value message like '-'.
	 *
	 * @return void
	 */
	protected function some_empty_message() : void {
		?>
		—
		<?php
	}

	/**
	 * Fill current table page with items
	 *
	 * @return array<\stdClass>
	 */
	protected function fill_items() : array {
		if ( count( $this->ids ) ) {
			return $this->content->data_get_by_ids( $this->ids );
		}

		$page    = $this->get_pagenum();
		$start   = ( $page - 1 ) * (int) $this->per_page;
		$filters = [
			'cat'       => $this->category,
			'post_type' => $this->post_type,
			'page_id'   => $this->page_id,
			's'         => $this->search_string,
			'ids'       => $this->ids,
		];
		if ( ! empty( $this->author ) ) {
			$filters['author'] = $this->author;
		}
		if ( '' !== $this->keywords ) {
			$filters['keywords'] = $this->keywords;
		}
		return $this->content->data_get_clear( $this->tab, $this->date, $filters, $start, $this->per_page, $this->orderby, $this->order );
	}

	/**
	 * Return count of items using current filters
	 *
	 * @return int
	 */
	private function count_data() : int {
		if ( count( $this->ids ) ) {
			return count( $this->ids );
		}
		$filters = [
			'cat'       => $this->category,
			'post_type' => $this->post_type,
			'page_id'   => $this->page_id,
			's'         => $this->search_string,
		];
		if ( ! empty( $this->author ) ) {
			$filters['author'] = $this->author;
		}
		if ( '' !== $this->keywords ) {
			$filters['keywords'] = $this->keywords;
		}
		return $this->content->data_get_clear_count( $this->tab, $this->date, $filters );
	}

	/**
	 * Prepares the list of items for displaying
	 */
	public function prepare_items() : void {
		$columns               = $this->get_columns();
		$hidden                = get_hidden_columns( get_current_screen() ?? $this->screen );
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->items = $this->fill_items();
		$total_items = $this->count_data();

		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $this->per_page,
				'total_pages' => ceil( $total_items / $this->per_page ),
				'orderby'     => $this->orderby,
				'order'       => $this->order,
			]
		);
	}

	/**
	 * Display a table.
	 *
	 * Note: we will not have there any query vars from page url until we added it as parameter at the content.display() JS function.
	 */
	public function display() : void {
		// Adds field order and orderby.
		// Note: nonce field already added before.
		?>
		<input type="hidden" class="table-query" name="page" data-name="paged" value="<?php echo esc_attr( (string) $this->get_pagenum() ); ?>" />
		<input type="hidden" class="table-query" name="order" data-name="order" value="<?php echo esc_attr( $this->_pagination_args['order'] ); ?>" />
		<input type="hidden" class="table-query" name="orderby" data-name="orderby" value="<?php echo esc_attr( $this->_pagination_args['orderby'] ); ?>" />
		<input type="hidden" class="table-query" name="last_search" data-name="s" value="<?php echo esc_attr( $this->search_string ); ?>" />
		<input type="hidden" class="table-query" name="tab" data-name="tab" value="<?php echo esc_attr( $this->tab ); ?>" />
		<input type="hidden" class="table-query" name="m" data-name="m" value="<?php echo esc_attr( $this->date ); ?>" />
		<input type="hidden" class="table-query" name="cat" data-name="cat" value="<?php echo esc_attr( $this->category ); ?>" />
		<input type="hidden" class="table-query" name="author" data-name="author" value="<?php echo ( ! empty( $this->author ) ? esc_attr( (string) $this->author ) : '' ); ?>" />
		<input type="hidden" class="table-query" name="keywords" data-name="keywords" value="<?php echo esc_attr( '' !== $this->keywords ? $this->keywords : '' ); ?>" />
		<input type="hidden" id="has_unprocessed_items" value="<?php echo esc_attr( ( new Content_Audit() )->require_update() ? '1' : '' ); ?>" />
		<?php
		parent::display();
	}

	/**
	 * Get bulk actions list
	 *
	 * @return array<string, string> Associative array ( action id => title )
	 */
	protected function get_bulk_actions() : array {
		$actions = array(
			'stop'  => 'Exclude from analysis',
			'start' => 'Include in analysis',
			'trash' => 'Move to trash',
		);
		return $actions;
	}

	/**
	 * Generates and displays row action links.
	 *
	 * @param \stdClass $item        Item being acted upon.
	 * @param string    $column_name Current column name.
	 * @param string    $primary     Primary column name.
	 * @return string Row actions output for posts.
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) : string {
		// Note: can not define type of parameters, because not defined in parent class.
		if ( $primary === $column_name ) {
			$post_id          = absint( $item->post_id );
			$title            = $item->title ?: '(Empty title)';
			$actions          = [];
			$post_type_object = get_post_type_object( $item->post_type );

			// Note: we are working only with posts & pages already filtered by post_status 'publish' here.
			// So we can skip related checks in row handlers logic.
			if ( current_user_can( 'edit_post', $post_id ) ) {
				$actions['edit'] = sprintf(
					'<a href="%s" aria-label="%s">%s</a>',
					get_edit_post_link( $post_id ),
					/* translators: %s: post title */
					esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;' ), $title ) ),
					__( 'Edit' )
				);
			}

			if ( ! is_null( $post_type_object ) && is_post_type_viewable( $post_type_object ) ) {
				$actions['view'] = sprintf(
					'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
					get_permalink( $post_id ),
					/* translators: %s: post title */
					esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $title ) ),
					__( 'View' )
				);
			}

			// Exclude from audit / Include to audit row action.
			$action = $item->action ?? Ahrefs_Seo_Data_Content::ACTION4_ADDED_SINCE_LAST;
			if ( in_array( $action, [ Ahrefs_Seo_Data_Content::ACTION4_ADDED_SINCE_LAST ], true ) ) {
				$actions['include'] = sprintf(
					'<a href="%s" data-id="%d" class="submit-include" aria-label="%s">%s</a>',
					'#',
					$item->post_id,
					/* translators: %s: post title */
					esc_attr( sprintf( __( 'Run audit' ), $title ) ),
					__( 'Run audit' )
				);
			} elseif ( in_array( $action, [ Ahrefs_Seo_Data_Content::ACTION4_ERROR_ANALYZING, Ahrefs_Seo_Data_Content::ACTION4_NOINDEX_PAGE ], true ) ) {
				$actions['include'] = sprintf(
					'<a href="%s" data-id="%d" class="submit-include" aria-label="%s">%s</a>',
					'#',
					$item->post_id,
					/* translators: %s: post title */
					esc_attr( sprintf( __( 'Analyze page again' ), $title ) ),
					__( 'Analyze page again' )
				);
			} elseif ( in_array( $action, [ Ahrefs_Seo_Data_Content::ACTION4_MANUALLY_EXCLUDED, Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE, Ahrefs_Seo_Data_Content::ACTION4_NEWLY_PUBLISHED ], true ) ) {
				$actions['include'] = sprintf(
					'<a href="%s" data-id="%d" class="submit-include" aria-label="%s">%s</a>',
					'#',
					$item->post_id,
					/* translators: %s: post title */
					esc_attr( sprintf( __( 'Include to audit' ), $title ) ),
					__( 'Include to audit' )
				);
			} elseif ( in_array( $action, [ Ahrefs_Seo_Data_Content::ACTION4_DO_NOTHING, Ahrefs_Seo_Data_Content::ACTION4_UPDATE_YELLOW, Ahrefs_Seo_Data_Content::ACTION4_MERGE, Ahrefs_Seo_Data_Content::ACTION4_EXCLUDE, Ahrefs_Seo_Data_Content::ACTION4_UPDATE_ORANGE, Ahrefs_Seo_Data_Content::ACTION4_DELETE ], true ) ) {
				$actions['exclude'] = sprintf(
					'<a href="%s" data-id="%d" class="submit-exclude" aria-label="%s">%s</a>',
					'#',
					$item->post_id,
					/* translators: %s: post title */
					esc_attr( sprintf( __( 'Exclude from audit' ), $title ) ),
					__( 'Exclude from audit' )
				);
			}
			// additional "Exclude" row action.
			if ( in_array( $action, [ Ahrefs_Seo_Data_Content::ACTION4_NEWLY_PUBLISHED, Ahrefs_Seo_Data_Content::ACTION4_ADDED_SINCE_LAST ], true ) ) {
				$actions['exclude'] = sprintf(
					'<a href="%s" data-id="%d" class="submit-exclude" aria-label="%s">%s</a>',
					'#',
					$item->post_id,
					/* translators: %s: post title */
					esc_attr( sprintf( __( 'Exclude from audit' ), $title ) ),
					__( 'Exclude from audit' )
				);
			}

			return $this->row_actions( $actions );
		} elseif ( 'keyword' === $column_name ) {
			$post_id = absint( $item->post_id );
			$title   = $item->title ?: '(Empty title)';
			$actions = [];
			if ( current_user_can( 'edit_post', $post_id ) ) {
				if ( ! is_null( $item->keyword ) && '' !== $item->keyword && ! (bool) $item->is_approved_keyword ) {
					$actions['approve-keyword'] = sprintf(
						'<a href="#" class="approve-keywords" data-post="%d" aria-label="%s">%s</a>',
						$post_id,
						/* translators: %s: post title */
						esc_attr( sprintf( __( 'Approve keyword for &#8220;%s&#8221;' ), $title ) ),
						__( '✓ Approve' )
					);
				}

				$actions['change-keyword'] = sprintf(
					'<a href="#" class="change-keywords" data-post="%d" aria-label="%s">%s</a>',
					$post_id,
					/* translators: %s: post title */
					esc_attr( sprintf( __( 'Change keyword for &#8220;%s&#8221;' ), $title ) ),
					__( 'Change' )
				);
			}

			return $this->row_actions( $actions );
		} elseif ( 'backlinks' === $column_name ) {
			$actions = [];
			$post_id = absint( $item->post_id );
			$url     = (string) get_permalink( $post_id );
			$link    = 'https://ahrefs.com/site-explorer/overview/v2/exact/live?target=' . rawurlencode( apply_filters( 'ahrefs_seo_search_traffic_url', $url ) );
			if ( ! is_null( $item->backlinks ) ) {
				if ( intval( $item->backlinks ) > 0 ) {
					$actions['ahrefs-open-backlink'] = sprintf(
						'<a href="%s" target="_blank" class="ahrefs-open-content-backlinks" data-post="%d" data-url="%s" aria-label="%s">%s<img src="%s" class="icon"></a>',
						esc_attr( $link ),
						esc_attr( "$post_id" ),
						esc_attr( $url ),
						/* translators: view backlinks for post in Ahrefs */
						esc_attr( __( 'View in Ahrefs' ) ),
						__( 'View in Ahrefs' ),
						esc_attr( AHREFS_SEO_IMAGES_URL . 'link-open.svg' )
					);
				}
			}
			return $this->row_actions( $actions );
		}

		return '';
	}

	/**
	 * Display a monthly dropdown for filtering items
	 *
	 * @global wpdb      $wpdb
	 * @global WP_Locale $wp_locale
	 * @return void
	 */
	protected function months_dropdown_content() : void {
		global $wpdb, $wp_locale;

		$months      = Ahrefs_Seo_Db_Helper::content_data_get_clear_months( $this->content->snapshot_context_get(), $this->tab, $this->category, $this->search_string );
		$month_count = count( $months );

		if ( ! $month_count || ( 1 === $month_count && 0 === $months[0]->month ) ) {
			return;
		}

		$m = $this->date;
		?>
		<label for="filter-by-date" class="screen-reader-text"><?php esc_html_e( 'Filter by date' ); ?></label>
		<select name="m" id="filter-by-date">
			<option<?php selected( $m, 0 ); ?> value="0"><?php esc_html_e( 'All dates' ); ?></option>
			<?php
			foreach ( $months as $arc_row ) {
				if ( ! $arc_row->year ) {
					continue;
				}

				$month = zeroise( $arc_row->month, 2 );
				$year  = $arc_row->year;

				printf(
					"<option %s value='%s'>%s</option>\n",
					selected( $m, $year . $month, false ),
					esc_attr( $arc_row->year . $month ),
					/* translators: 1: month name, 2: 4-digit year */
					esc_html( sprintf( __( '%1$s %2$d' ), $wp_locale->get_month( $month ), $year ) )
				);
			}
			?>
		</select>
		<?php
	}

	/**
	 * Displays a categories drop-down for filtering on the Posts list table.
	 */
	protected function categories_dropdown_content() : void {
		$tax = get_taxonomy( 'category' );

		$dropdown_options = array(
			'show_option_all' => ( $tax instanceof \WP_Taxonomy ) && property_exists( $tax->labels, 'all_items' ) ? $tax->labels->all_items : '',
			'hide_empty'      => 0,
			'hierarchical'    => 1,
			'show_count'      => 0,
			'orderby'         => 'name',
		);
		unset( $tax );
		if ( ! empty( $this->category ) ) {
			$dropdown_options['selected'] = $this->category;
		}

		$categories = get_terms( 'category', $dropdown_options );
		if ( ! is_array( $categories ) ) {
			return;
		}
		?>
		<label class="screen-reader-text" for="cat"><?php __( 'Filter by category' ); ?></label>
		<select name="cat" id="cat" class="postform">
			<option value="0" selected="selected" class="current">All Categories</option>
			<?php

			$selected = isset( $_REQUEST['cat'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['cat'] ) ) : ''; // phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification,WordPress.VIP.SuperGlobalInputUsage.AccessDetected -- load option value.

			$r = [
				'depth'       => 0,
				'orderby'     => 'id',
				'order'       => 'ASC',
				'show_count'  => 0,
				'selected'    => $selected,
				'name'        => 'cat',
				'id'          => '',
				'class'       => 'postform',
				'tab_index'   => 0,
				'value_field' => 'term_id',
			];

			// Posts.
			$item          = new \stdClass();
			$item->term_id = 'cat-0';
			$item->name    = 'Posts';
			$item->parent  = '0';

			$items = [ $item ];

			/** \WP_Term[] $caterories */
			foreach ( $categories as $cat ) {
				$item          = new \stdClass();
				$item->term_id = 'cat-' . $cat->term_id;
				$item->name    = $cat->name;
				$item->count   = $cat->count;
				$item->parent  = 'cat-' . $cat->parent;
				$items[]       = $item;
			}

			// Pages.
			$content = new Ahrefs_Seo_Content_Settings();
			$pages   = $content->get_pages_list();

			$item          = new \stdClass();
			$item->term_id = 'page-0';
			$item->name    = 'Pages';
			$item->parent  = '0';

			$items[] = $item;

			echo walk_category_dropdown_tree( $items, 0, $r ); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
			?>
		</select>
		<?php
	}

	/**
	 * Displays an authors drop-down for filtering on the Posts list table.
	 */
	protected function authors_dropdown_content() : void {
		$list = ( new Content_Db() )->get_all_authors();
		?>
		<label class="screen-reader-text" for="author"><?php __( 'Filter by author' ); ?></label>
		<select name="author" id="author" class="postform">
			<option value="0" class="current"<?php selected( $this->author, 0 ); ?>>All authors</option>
			<?php
			if ( $list ) {
				foreach ( $list as $item ) {
					?>
			<option value="<?php echo esc_attr( $item['id'] ); ?>"<?php selected( $this->author, $item['id'] ); ?>><?php echo esc_html( $item['name'] ); ?></option>
					<?php
				}
			}
			?>
		</select>
		<?php
	}

	/**
	 * Displays a keywords approved drop-down for filtering on the Posts list table.
	 */
	protected function keywords_dropdown_content() : void {
		?>
		<label class="screen-reader-text" for="keywords"><?php __( 'Filter by keywords type' ); ?></label>
		<select name="keywords" id="keywords" class="postform">
			<option value="" class="current"<?php selected( $this->keywords, '' ); ?>>All keywords</option>
			<option value="1" class="current"<?php selected( $this->keywords, '1' ); ?>>Approved by you</option>
			<option value="0" class="current"<?php selected( $this->keywords, '0' ); ?>>Suggested by the plugin</option>
			<option value="2" class="current"<?php selected( $this->keywords, '2' ); ?>>No keyword detected</option>
		</select>
		<?php
	}

	/**
	 * Add follow filter and group by choice
	 *
	 * @param string $which Top or bottom.
	 */
	protected function extra_tablenav( $which ) : void {
		// Note: can not define type of parameters, because not defined in parent class.
		?>
		<div class="alignleft actions">
			<?php
			if ( 'top' === $which ) {
				$this->months_dropdown_content();
				$this->categories_dropdown_content();
				$this->authors_dropdown_content();
				$this->keywords_dropdown_content();

				submit_button( __( 'Filter' ), '', 'filter_action', false, array( 'id' => 'group-filter-submit' ) );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Tabs. Get an associative array ( id => link ) with the list
	 * of views available on this table.
	 *
	 * @return array
	 */
	protected function get_views() {
		$result = [];
		$counts = $this->content->data_get_count_by_status();

		// Called from admin-ajax.php content, need to use correct base link.
		$base_url = add_query_arg( 'page', Ahrefs_Seo::SLUG_CONTENT, admin_url( 'admin.php' ) );

		foreach ( $this->tabs as $id => $title ) {
			$url              = '' === $id ? remove_query_arg( 'tab', $base_url ) : add_query_arg( 'tab', $id, $base_url );
			$count            = $counts[ $id ] ?? 0;
			$result[ $title ] = sprintf( '<a href="%s" data-tab="%s" class="tab-content-item %s">%s <span class="count">(%d)</span></a>', esc_attr( $url ), esc_attr( $id ), ( $id === $this->tab ? 'current' : '' ), esc_html( $title ), $count );
		}
		return $result;
	}

	/**
	 * Replace top navigations by search box and Analysis setting button
	 *
	 * @param string $which Top or bottom.
	 * @return void
	 */
	protected function pagination( $which ) : void {
		// Note: can not define type of parameters, because not defined in parent class.
		if ( 'top' === $which ) {
			?>
			<div class="tablenav-pages">
				<?php
				$this->search_box( 'Search', 'search_to_url' );
				?>
				<div class="clear"></div>
			</div>
			<?php
			return;
		}
		parent::pagination( $which );
	}

	/**
	 * Return updated items as json answer and terminate.
	 * Check current version of items and return all updated items (rows) at the 'data.updated' field (as raw html code) of json answer.
	 *
	 * @param array<int, int>      $ids Associative array [ post_id => ver ]. 'ver' is a resulting field of sql queries of data class.
	 * @see Ahrefs_Seo_Data_Content class: data_get_by_ids(), data_get_clear_count().
	 * @param array<string, mixed> $additional_fields Additional fileds.
	 * @return void
	 */
	public function ajax_response_updated( array $ids, array $additional_fields ) : void {
		if ( ! empty( $ids ) ) {
			$this->ids = $ids;
			$this->prepare_items();

			ob_start();
			$this->display_rows();
			$rows = ob_get_clean();
		}
		ob_start();
		$this->views();
		$tabs = ob_get_clean();

		$response = [ 'tabs' => $tabs ];
		if ( ! empty( $rows ) ) {
			$response['updated'] = $rows;
		}
		if ( ! empty( $additional_fields ) ) {
			$response = array_merge( $response, $additional_fields );
		}

		wp_send_json_success( $response );
	}
}
