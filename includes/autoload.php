<?php
/**
 * PSR-4 autoloader for the Chagency plugin.
 *
 * Maps the `Chagency\` namespace to files under `includes/`. Mirrors the
 * structure used by the WordPress AI plugin.
 *
 * @package Chagency
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix   = 'Chagency\\';
		$base_dir = __DIR__ . '/';

		$len = strlen( $prefix );
		if ( strncmp( $class_name, $prefix, $len ) !== 0 ) {
			return;
		}

		$relative = substr( $class_name, $len );
		$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

		if ( ! file_exists( $file ) ) {
			return;
		}

		require $file;
	}
);
