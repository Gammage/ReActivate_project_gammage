<?php

namespace ahrefs\AhrefsSeo;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Promise as GuzzlePromize;
use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
use GuzzleHttp\RequestOptions as GuzzleRequestOptions;
/**
 * Detect is page noindex.
 */
class Ahrefs_Seo_Noindex extends Ahrefs_Seo_Abstract_Api {

	/**
	 * Load pages content and return result, is page noindex.
	 *
	 * @since 0.7.3
	 *
	 * @param array<int, string> $page_id_to_url_list Associative array, page_id => url.
	 * @return array Associave array post_id => int: 0 - index, 1 - noindex, -1 - error.
	 */
	public function is_noindex( array $page_id_to_url_list ) {
		$pages_details = $this->load_pages( $page_id_to_url_list );
		return $this->detect_is_noindex( $pages_details, $page_id_to_url_list );
	}
	/**
	 * Load and return pages content or errors
	 *
	 * @since 0.7.3
	 *
	 * @param array<int, string> $page_id_to_url_list Associative array, page_id => url.
	 * @return array<int, array<string, mixed>> Results, associative array
	 * page_id => ['success'=>true,'code'=>int, 'headers'=>array, 'body'=>string]
	 * or
	 * page_id => ['success'=>false,'error'=>string, 'exception'=>GuzzleConnectException]
	 */
	public function load_pages( array $page_id_to_url_list ) {
		$results = [];
		try {
			$options = [
				GuzzleRequestOptions::ALLOW_REDIRECTS => false, // disable redirects.
				GuzzleRequestOptions::CONNECT_TIMEOUT => 15,
				GuzzleRequestOptions::READ_TIMEOUT    => 20,
				GuzzleRequestOptions::TIMEOUT         => 30,
			];
			$path    = Ahrefs_Seo_Analytics::get_cert_path();
			if ( ! empty( $path ) ) { // use verify path.
				$options[ GuzzleRequestOptions::VERIFY ] = $path;
			}
			$client   = new GuzzleClient( $options );
			$promises = [];
			foreach ( $page_id_to_url_list as $post_id => $url ) {
				// Initiate each request but do not block.
				$promises[ $post_id ] = $client->getAsync( $url );
			}
			$responses = [];
			try {
				// Wait for the requests to complete, even if some of them fail.
				$responses = GuzzlePromize\Utils::settle( $promises )->wait();
				do_action_ref_array( 'ahrefs_seo_api_load_pages', [ &$responses ] );
			} catch ( GuzzleConnectException $e ) {
				Ahrefs_Seo::notify( $e, 'noindex' );
				$this->on_error_received( $e, array_keys( $page_id_to_url_list ) ); // assume this is a rate error.
				// return result filled with errors.
				return array_map(
					function ( $value ) use ( $e ) {
						return [
							'success'   => false,
							'error'     => $e->getMessage(),
							'exception' => $e,
						];
					},
					$page_id_to_url_list
				);
			}
			foreach ( $responses as $post_id => $response ) {
				if ( 'fulfilled' === $response['state'] ) {
					$results[ (int) $post_id ] = [
						'success' => true,
						/** \Psr\Http\Message\ResponseInterface response['value']  */
						'code'    => $response['value']->getStatusCode(),
						'headers' => $response['value']->getHeaders(),
						'body'    => (string) $response['value']->getBody(),
					];
				} else {
					$e                         = $response['reason'];
					$results[ (int) $post_id ] = [
						'success'   => false,
						'error'     => $e->getMessage(),
						'exception' => $e,
					];
					Ahrefs_Seo::breadcrumbs( isset( $page_id_to_url_list[ $post_id ] ) ? $page_id_to_url_list[ $post_id ] : "post id: {$post_id}" );
					Ahrefs_Seo::notify( $e, 'noindex' );
					$this->on_error_received( $e, [ (int) $post_id ] );
				}
			}
		} catch ( \Error $e ) {
			$error = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__, 'Unexpected error on noindex load_pages' );
		} catch ( \Exception $e ) {
			Ahrefs_Seo_Errors::save_message( 'noindex', $e->getMessage() );
			Ahrefs_Seo::notify( $e, 'Unexpected error on noindex load_pages' );
		}
		return $results;
	}
	/**
	 * Update is noindex value using loaded pages.
	 * Set has_rate_error if http 5xx code received.
	 *
	 * @param array<int, array<string, mixed>> $pages_details Associative array, page_id => results array.
	 * @param array<int, string>               $page_id_to_url_list Associative array, page_id => url.
	 * @return array Associave array post_id => int: 0 - index, 1 - noindex, -1 - error.
	 */
	public function detect_is_noindex( array $pages_details, array $page_id_to_url_list ) {
		$results = [];
		foreach ( $pages_details as $post_id => $values ) {
			$link = isset( $page_id_to_url_list[ $post_id ] ) ? $page_id_to_url_list[ $post_id ] : "post ID {$post_id}";
			$code = isset( $values['code'] ) ? $values['code'] : null;
			if ( ! $values['success'] ) {
				Ahrefs_Seo_Errors::save_message( 'noindex', sprintf( 'Url: %s Unable to load [%s]', $link, $values['error'] ) );
				$results[ $post_id ] = -1;
			} elseif ( ! is_null( $code ) && 200 !== $code ) {
				Ahrefs_Seo_Errors::save_message( 'noindex', sprintf( 'Url: %s Incorrect response code: %d', $link, $code ) );
				$results[ $post_id ] = -1;
				if ( is_int( $code ) && 500 <= $code && 600 > $code ) {
					$this->on_error_received( new Ahrefs_Seo_Exception( sprintf( 'WordPress error. Url: %s Incorrect response code: %d', $link, $code ) ), [ $post_id ] ); // 5xx code detected as rate error.
				}
			} else {
				$is_noindex = false;
				// check headers.
				$x_robots_tag_header = null;
				array_walk(
					$values['headers'],
					function ( $_values, $header_name ) use ( &$x_robots_tag_header ) {
						if ( 'x-robots-tag' === strtolower( $header_name ) ) {
							$x_robots_tag_header = $_values;
						}
					}
				);
				if ( ! is_null( $x_robots_tag_header ) ) {
					$is_noindex = $this->check_header_x_robots_tag( $x_robots_tag_header );
				}
				// check content.
				if ( ! $is_noindex ) {
					$is_noindex = $this->check_html_meta( isset( $values['body'] ) ? $values['body'] : '' );
				}
				$results[ $post_id ] = $is_noindex ? 1 : 0;
			}
		}
		return $results;
	}
	/**
	 * Search noindex or none in header value
	 *
	 * @param string[] $headers
	 * @return bool True if is noindex.
	 */
	protected function check_header_x_robots_tag( array $headers ) {
		// search noindex or none in x-robots-tag header.
		foreach ( $headers as $header ) {
			if ( ! empty( $header ) && ( false !== stripos( $header, 'noindex' ) || false !== stripos( $header, 'none' ) ) ) {
				return true;
			}
		}
		return false;
	}
	/**
	 * Search noindex in html meta
	 *
	 * @param string $body
	 * @return bool True if is noindex.
	 */
	protected function check_html_meta( $body ) {
		$head_position = stripos( $body, '</head>' );
		if ( false !== $head_position ) {
			$body = substr( $body, 0, $head_position );
		}
		// check meta tags in content for "noindex".
		if ( preg_match_all( '!<meta(.*?)>!msi', $body, $mm ) ) {
			foreach ( $mm[1] as $string ) {
				if ( false !== stripos( $string, 'content=' ) && false !== stripos( $string, 'noindex' ) ) {
					if ( preg_match( '!content=[\'"](.*?)[\'"]!i', $string, $m ) ) {
						if ( false !== stripos( $m[1], 'noindex' ) ) {
							return true;
						}
					}
				}
			}
		}
		return false;
	}
}
