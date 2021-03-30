<?php

declare(strict_types=1);

namespace ahrefs\AhrefsSeo;

/**
 * Keywords search implementation using TF_IDF.
 */
class Keywords_Search {

	/**
	 * @var array<int, array> Associative array [ post_id => [[q=>..,f=>..],..]]
	 */
	private $keywords = [];
	/**
	 * @var null|Keywords_Dataset
	 */
	private $dataset = null;
	/**
	 * @var null|Keywords_Tokenizer
	 */
	private $tokenizer = null;
	/**
	 * @var null|Keywords_Vectorizer
	 */
	private $vectorizer = null;
	/**
	 * @var int
	 */
	private $keywords_limit;

	/**
	 * Constructor
	 *
	 * @param int[] $posts_targets
	 * @param int   $keywords_limit
	 */
	public function __construct( array $posts_targets, int $keywords_limit = 10 ) {
		$this->keywords_limit = $keywords_limit;

		$this->run_fast_method( $posts_targets );
		// free everything, save keywords only.
		unset( $this->dataset );
		unset( $this->vectorizer );
	}

	/**
	 * Run faster.
	 *
	 * @param int[] $posts_targets
	 * @return void
	 */
	private function run_fast_method( array $posts_targets ) : void {
		$this->dataset    = new Keywords_Dataset( $posts_targets );
		$this->tokenizer  = new Keywords_Tokenizer();
		$this->vectorizer = new Keywords_Vectorizer( $this->tokenizer, $this->keywords_limit );
		// posts content without html tags.
		$list = $this->dataset->get_targets();
		// return best keywords for each post.
		$this->vectorizer->transform( $list );

		foreach ( $list as $post_id => &$values ) {
			$keywords = [];
			foreach ( $values as $word => $featured ) {
				$keywords[] = [
					'q' => $word, // keyword.
					'f' => $featured, // feature index, some float.
				];
			}
			unset( $list[ $post_id ] );
			$this->keywords[ $post_id ] = $keywords;
		}
	}

	/**
	 * Get all keywords
	 *
	 * @return array<int, array> Associative array [ post_id => [[q=>..,f=>..],..]]
	 */
	public function get_all_keywords() : array {
		return $this->keywords;
	}
}
