<?php
/**
 * Siloq — Rank Math SEO Integration
 * @package Siloq
 * @since 1.5.269
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Siloq_RankMath_Integration {

	const CACHE_KEY = 'siloq_redirect_post_ids';
	const CACHE_TTL = 300;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_register_hooks' ), 20 );
		add_action( 'siloq_redirect_added',   array( __CLASS__, 'bust_caches' ) );
		add_action( 'siloq_redirect_updated', array( __CLASS__, 'bust_caches' ) );
		add_action( 'siloq_redirect_deleted', array( __CLASS__, 'bust_caches' ) );
		add_action( 'siloq_redirect_toggled', array( __CLASS__, 'bust_caches' ) );
		add_action( 'save_post',              array( __CLASS__, 'bust_caches' ) );
	}

	public static function maybe_register_hooks() {
		if ( ! self::rankmath_active() ) { return; }
		add_filter( 'rank_math/sitemap/exclude_posts', array( __CLASS__, 'exclude_redirected_posts' ) );
	}

	public static function exclude_redirected_posts( $excluded_ids = array() ) {
		$redirect_post_ids = self::get_redirect_source_post_ids();
		if ( empty( $redirect_post_ids ) ) { return $excluded_ids; }
		return array_values( array_unique( array_merge( array_map( 'intval', (array) $excluded_ids ), $redirect_post_ids ) ) );
	}

	public static function get_redirect_source_post_ids() {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) { return $cached; }
		if ( ! class_exists( 'Siloq_Redirect_Manager' ) ) { return array(); }
		$redirects = Siloq_Redirect_Manager::get_instance()->get_all_redirects( true );
		if ( empty( $redirects ) ) { set_transient( self::CACHE_KEY, array(), self::CACHE_TTL ); return array(); }
		$home_url = trailingslashit( home_url() );
		$post_ids = array();
		foreach ( $redirects as $redirect ) {
			$source = $redirect->source_url;
			if ( strpos( $source, 'http' ) !== 0 ) { $source = $home_url . ltrim( $source, '/' ); }
			$post_id = url_to_postid( $source );
			if ( $post_id > 0 ) { $post_ids[] = $post_id; }
		}
		$post_ids = array_values( array_unique( array_map( 'intval', $post_ids ) ) );
		set_transient( self::CACHE_KEY, $post_ids, self::CACHE_TTL );
		return $post_ids;
	}

	public static function bust_caches() {
		delete_transient( self::CACHE_KEY );
		if ( class_exists( '\RankMath\Sitemap\Cache' ) && method_exists( '\RankMath\Sitemap\Cache', 'invalidate' ) ) {
			\RankMath\Sitemap\Cache::invalidate();
			return;
		}
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rank_math_sitemap_%' OR option_name LIKE '_transient_timeout_rank_math_sitemap_%'" );
	}

	private static function rankmath_active() { return defined( 'RANK_MATH_VERSION' ); }
}
