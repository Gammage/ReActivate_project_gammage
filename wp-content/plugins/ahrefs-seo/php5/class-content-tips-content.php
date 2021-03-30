<?php

namespace ahrefs\AhrefsSeo;

/**
 * Class for content audit tips.
 * Display first on subsequent content tip if allowed.
 */
class Content_Tips_Content extends Content_Tips_Any {

	const OPTION_TIP_CLOSED    = 'ahrefs-seo-content-tip-closed-content';
	const OPTION_NOT_FIRST_TIP = 'ahrefs-seo-content-tip-first-tip-content';
	protected function show( $already_displayed = null ) {
		if ( $this->is_not_first_tip() ) {
			if ( 'subsequent' !== $already_displayed ) {
				Ahrefs_Seo::get()->get_view()->show_part( 'content-tips/subsequent-tip' );
			}
		} else {
			if ( 'first' !== $already_displayed ) {
				Ahrefs_Seo::get()->get_view()->show_part( 'content-tips/first-tip' );
			}
		}
	}
	/**
	 * Return content of first or subsequent content audit tip, if it is different from already displayed
	 *
	 * @param string $first_or_sub
	 * @return string|null Null if nothing to change, empty string to clean current block, string for replacing tip in current block.
	 */
	public function maybe_return_tip( $first_or_sub ) {
		if ( get_option( $this::OPTION_HAS_SUGGESTED, false ) && ! get_option( $this::OPTION_TIP_CLOSED, false ) ) { // need to show some tip.
			ob_start();
			$this->show( $first_or_sub );
			$result = (string) ob_get_clean();
			return '' === $result ? null : $result;
		} elseif ( '' === $first_or_sub ) { // nothing displayed, nothing to display now = no changes.
			return null;
		}
		return ''; // clean already displayed item.
	}
	public function on_snapshot_created( $snapshot_id ) {
		parent::on_snapshot_created( $snapshot_id );
		$is_not_first_tip = get_option( $this::OPTION_NOT_FIRST_TIP, '' );
		if ( '' === $is_not_first_tip ) {
			update_option( $this::OPTION_NOT_FIRST_TIP, 0 );
		} elseif ( '0' === $is_not_first_tip ) {
			update_option( $this::OPTION_NOT_FIRST_TIP, true );
		}
	}
	/**
	 * This is not first tip
	 *
	 * @return bool
	 */
	private function is_not_first_tip() {
		return (bool) get_option( $this::OPTION_NOT_FIRST_TIP, false );
	}
}
