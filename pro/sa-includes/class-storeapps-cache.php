<?php
/**
 * Cache StoreApps Quick Help
 *
 * @category    Class
 * @package     /sa-includes/
 * @author      StoreApps
 * @version     1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'StoreApps_Cache' ) ) {

	// @codingStandardsIgnoreStart //
	/*
	// Example usage
	$cache = new StoreApps_Cache('category', 14 * 86400);
	$val = $cache->get( $key );
	if ( $val === null ) {
		// Not in cache, compute
		$val = doyourprocess();
		$cache->set( $key, $val );
	}
	// Now use the $val

	function cleanup() {
		$cache = new StoreApps_Cache('category', 14 * 86400);
		$cache->cleanup();
	}

	// For cleanup
	wp_schedule_event( time(), 'daily', array($this, 'cleanup') );
	*/
	// @codingStandardsIgnoreEnd //

	/**
	 * Class to handle cache for quick help
	 */
	class StoreApps_Cache {

		/**
		 * Base directory
		 *
		 * @var string
		 */
		public $base_dir;

		/**
		 * Cache Expiration
		 *
		 * @var integer
		 */
		public $expire_after;

		/**
		 * Is cache enabled
		 *
		 * @var bool
		 */
		public $enabled;

		/**
		 * Cache group
		 *
		 * @var string
		 */
		public $_group; // phpcs:ignore

		/**
		 * Cache hashes
		 *
		 * @var array
		 */
		public $_hashes; // phpcs:ignore

		/**
		 * Set cache
		 *
		 * @param string $key Key.
		 * @param mixed  $data Data to cache.
		 */
		public function set( $key, $data ) {
			if ( ! $this->enabled ) {
				return false;
			}
			$res = file_put_contents( $this->file( $key ), wp_json_encode( $data ) ); // phpcs:ignore
			return ( false === $res ) ? false : true;
		}

		/**
		 * Get data from cache
		 *
		 * @param  string $key Key.
		 * @return mixed Cache Data.
		 */
		public function get( $key ) {
			if ( ! $this->enabled ) {
				return null;
			}

			if ( $this->exists( $key ) ) {
				return json_decode( file_get_contents( $this->file( $key ) ) ); // phpcs:ignore
			}
			return null;
		}

		/**
		 * Delete cache
		 *
		 * @param  string $key Key.
		 * @return bool
		 */
		public function delete( $key ) {
			if ( ! $this->enabled ) {
				return true;
			}
			if ( $this->exists( $key ) ) {
				return unlink( $this->file( $key ) ); // phpcs:ignore
			}
			return true;
		}

		/**
		 * Cleanup cache
		 *
		 * @return bool
		 */
		public function cleanup() {
			if ( ! $this->enabled ) {
				return true;
			}

			foreach ( glob( $this->base_dir . $this->_group . '*' ) as $filename ) {
				if ( filemtime( $filename ) < time() - $this->expire_after ) {
					@unlink( $filename ); // phpcs:ignore
				}
			}
			return true;
		}

		/**
		 * Cache key exists
		 *
		 * @param  string $key Key.
		 * @return mixed Cache data.
		 */
		private function exists( $key ) {
			return ( is_file( $this->file( $key ) ) );
		}

		/**
		 * File
		 *
		 * @param  string $key Key.
		 * @return string Cache file.
		 */
		private function file( $key ) {
			return $this->base_dir . $this->_group . '_' . $this->hash( $key );
		}

		/**
		 * Hash
		 *
		 * @param  string $key Key.
		 * @return mixed Hash.
		 */
		private function hash( $key ) {
			if ( ! array_key_exists( $key, $this->_hashes ) ) {
				$this->_hashes[ $key ] = md5( $key );
			}
			return $this->_hashes[ $key ];
		}

		/**
		 * Constructor
		 *
		 * @param string  $group        Cache Group.
		 * @param integer $expire_after Expiration.
		 * @param string  $base_dir     Base directory.
		 */
		public function __construct( $group = '', $expire_after = 86400, $base_dir = '' ) {
			$this->_group       = sanitize_key( $group );
			$this->base_dir     = $base_dir;
			$this->expire_after = $expire_after;
			$this->_hashes      = array();

			if ( empty( $this->base_dir ) ) {
				$uploads          = wp_upload_dir();
				$uploads_base_dir = trailingslashit( $uploads['basedir'] );
				$this->base_dir   = $uploads_base_dir . 'sacache/';
			}

			if ( ! is_dir( $this->base_dir ) ) {
				if ( false === mkdir( $this->base_dir ) ) { // phpcs:ignore
					$this->enabled = false;
					return;
				}
			}
			$this->base_dir = trailingslashit( $this->base_dir );
			$this->enabled  = true;
		}
	}

}
