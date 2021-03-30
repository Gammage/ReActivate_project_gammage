<?php

declare(strict_types=1);

namespace ahrefs\AhrefsSeo;

/**
 * Class for content audit tips.
 *
 * Show tip if:
 * - "has suggested" keywords;
 * - not "tip closed" by user;
 *
 * Update "has suggested" keywords on:
 * - skip last step of Wizard;
 * - snapshot created;
 * - keyword approved;
 *
 * Reset "tip closed" on:
 * - new snapshot created;
 *
 * Set "tip closed" on:
 * - tip closed by user;
 */
abstract class Content_Tips_Any {

	protected const OPTION_TIP_CLOSED    = 'ahrefs-seo-content-tip-closed';
	protected const OPTION_HAS_SUGGESTED = 'ahrefs-seo-content-tip-has-keywords';

	/**
	 * Display tip on screen if allowed.
	 *
	 * @return void
	 */
	public function maybe_show_tip() : void {
		if ( get_option( $this::OPTION_HAS_SUGGESTED, false ) && ! get_option( $this::OPTION_TIP_CLOSED, false ) ) {
			$this->show();
		}
	}

	abstract protected function show( ?string $already_displayed = null ) : void;

	/**
	 * Hide tip. Switch from first tip to subsequent tips
	 *
	 * @return void
	 */
	public function on_closed_by_user() : void {
		update_option( $this::OPTION_TIP_CLOSED, true );
	}


	/**
	 * Maybe activate tip if snapshot has suggested keywords
	 * Called on snapshot finished.
	 *
	 * @param int $snapshot_id
	 * @return void
	 */
	public function on_snapshot_created( int $snapshot_id ) : void {
		update_option( $this::OPTION_TIP_CLOSED, false );
		$this->update_has_suggested_keywords( $snapshot_id );
	}

	/**
	 * Maybe hide tip if no suggested keywords exists
	 *
	 * @param int $snapshot_id
	 * @return void
	 */
	public function on_keyword_approved( int $snapshot_id ) : void {
		$this->update_has_suggested_keywords( $snapshot_id );
	}

	/**
	 * Maybe hide tip if no suggested keywords exists
	 *
	 * @return void
	 */
	public function on_wizard_skipped() : void {
		$snapshot_id = Ahrefs_Seo_Data_Content::get()->snapshot_context_get();
		$this->update_has_suggested_keywords( $snapshot_id );
	}

	/**
	 * Update "has suggested" keywords option.
	 *
	 * @param int $snapshot_id
	 * @return void
	 */
	private function update_has_suggested_keywords( int $snapshot_id ) : void {
		update_option( $this::OPTION_HAS_SUGGESTED, $this->snapshot_has_suggested_keywords( $snapshot_id ) );
	}

	/**
	 * The snapshot has suggested keywords
	 *
	 * @param int $snapshot_id
	 * @return bool
	 */
	protected function snapshot_has_suggested_keywords( int $snapshot_id ) : bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->ahrefs_content} WHERE snapshot_id = %d AND is_approved_keyword = 0 AND keyword != '' LIMIT 1", $snapshot_id ) );
	}
}
