<?php
/**
 * Site settings, stored in one option.
 *
 * @package SwatchCraft
 */

namespace SwatchCraft;

/**
 * Settings are read through get() (defaults merged in) and written through save(), which sanitizes
 * every value against the defaults.
 */
final class Settings {

	const OPTION = 'swatchcraft_settings';

	/**
	 * Allowed values of the choice settings.
	 */
	const CHOICES = array(
		'shape'         => array( 'round', 'rounded', 'square' ),
		'size'          => array( 'small', 'medium', 'large' ),
		'unavailable'   => array( 'cross', 'hide' ),
		'out_of_stock'  => array( 'cross', 'clickable', 'hide' ),
		'loop_position' => array( 'after_title', 'before_cart', 'after_cart' ),
		'table'         => array( 'off', 'after_cart', 'replace_cart' ),
	);

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Attributes without a swatch type show as buttons instead of dropdowns.
			'auto_buttons'   => true,
			'shape'          => 'rounded',
			'size'           => 'medium',
			'unavailable'    => 'cross',
			'out_of_stock'   => 'cross',
			'tooltips'       => true,
			'tooltip_price'  => false,
			'tooltip_stock'  => false,
			'show_selected'  => true,
			'clear_link'     => true,
			// Products with more variations than this look up availability with AJAX.
			'ajax_threshold' => 30,
			'loop'           => true,
			// Attribute shown on product cards ('' picks the first colour or image attribute).
			'loop_attribute' => '',
			'loop_limit'     => 5,
			'loop_position'  => 'after_title',
			'gallery'        => true,
			'table'          => 'off',
		);
	}

	/**
	 * All settings, or one.
	 *
	 * @param string $key Key (optional).
	 * @return mixed
	 */
	public static function get( $key = '' ) {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = self::sanitize( is_array( $stored ) ? $stored : array() );
		}
		if ( '' === $key ) {
			return self::$cache;
		}
		return isset( self::$cache[ $key ] ) ? self::$cache[ $key ] : null;
	}

	/**
	 * Save settings.
	 *
	 * @param array $settings Any subset.
	 * @return array Saved settings.
	 */
	public static function save( array $settings ) {
		update_option( self::OPTION, self::sanitize( array_merge( self::get(), $settings ) ) );
		self::$cache = null;
		return self::get();
	}

	/**
	 * Forget the cached copy.
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Sanitize against the defaults.
	 *
	 * @param array $settings Settings.
	 * @return array
	 */
	public static function sanitize( array $settings ) {
		$out = array();
		foreach ( self::defaults() as $key => $default ) {
			$value = array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
			if ( isset( self::CHOICES[ $key ] ) ) {
				$value = in_array( $value, self::CHOICES[ $key ], true ) ? $value : $default;
			} elseif ( 'ajax_threshold' === $key ) {
				$value = max( 10, min( 500, (int) $value ) );
			} elseif ( 'loop_limit' === $key ) {
				$value = max( 1, min( 30, (int) $value ) );
			} elseif ( 'loop_attribute' === $key ) {
				$value = sanitize_title( is_scalar( $value ) ? (string) $value : '' );
			} else {
				$value = (bool) rest_sanitize_boolean( $value );
			}
			$out[ $key ] = $value;
		}
		return $out;
	}
}
