<?php

declare(strict_types=1);

namespace ahrefs\AhrefsSeo;

/**
 * Dataset implementation for TF_IDF keywords search.
 */
class Keywords_Dataset {

	/**
	 * Target posts' content
	 *
	 * @var string[] Post content, associative array [post_id => post_content].
	 */
	private $targets = [];

	/**
	 * Initialize dataset with posts, samples are all active posts id, targets is array with post id to find keywords for.
	 *
	 * @throws \Exception On empty posts targets.
	 * @param int[] $posts_targets Array of post ID.
	 */
	public function __construct( array $posts_targets ) {
		if ( empty( $posts_targets ) ) {
			throw new \Exception( 'Empty posts targets.' );
		}

		$this->targets = $this->load_posts_content( $posts_targets );
	}

	/**
	 * Get targets
	 *
	 * @return array<int, string> Post content, associative array [post_id => post_content].
	 */
	public function get_targets() : array {
		return $this->targets;
	}

	/**
	 * Words divided by space, all tags and some punctuation replaced by '|'.
	 *
	 * @param int[] $posts
	 * @return array<int, string> Associative array post_id => content
	 */
	private function load_posts_content( array $posts ) : array {
		$result = [];
		foreach ( $posts as $post_id ) {
			$post = get_post( intval( $post_id ) );
			if ( ( $post instanceof \WP_Post ) && $post->ID ) {
				if ( function_exists( 'mb_strtolower' ) ) {
					$html = mb_strtolower( $post->post_title ) . '|' . mb_strtolower( $post->post_content );
				} else {
					$html = strtolower( $post->post_title ) . '|' . strtolower( $post->post_content );
				}
				$html = str_replace( [ '<', '>' ], [ ' |<', '>| ' ], $html ); // add special divider char '|' to all tags.
				$text = wp_strip_all_tags( $html, true ); // remove all html tgs.
				$text = html_entity_decode( $text ); // replace html entities by chars.

				$text = (string) preg_replace( '![ ]{2,}!', ' ', $text );
				$text = (string) preg_replace( '/[,\?!\.\{\}\[\]\(\):;"]+[\s+\|]/', '|', $text );
				$text = (string) preg_replace( '/[\s+\|][,!\?\.\{\}\[\]\(\):;"]+/', '|', $text );

				$text = str_replace( [ '| | |', '| |', '|||', '||', '| ', ' |' ], [ '|', '|', '|', '|', '|', '|' ], $text );

				$substrings         = (array) preg_split( '/[\pZ\pC]+/u', $text, -1, PREG_SPLIT_NO_EMPTY ); // split by any utf-8 space.
				$result[ $post_id ] = implode( ' ', $substrings ); // and make a string again.
			}
		}
		return $result;
	}
}
