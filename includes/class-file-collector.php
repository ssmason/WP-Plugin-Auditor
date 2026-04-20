<?php
/**
 * Collects PHP and JS files from a plugin directory.
 *
 * @package PluginAuditor
 */

declare( strict_types=1 );

namespace PluginAuditor;

defined( 'ABSPATH' ) || exit;

/**
 * Recursively collects PHP and JS files, skipping vendor paths.
 */
class FileCollector {

	/**
	 * Directory names to skip during collection.
	 */
	private const SKIP_DIRS = array( 'vendor', 'node_modules', '.git', 'tests' );

	/**
	 * Returns all PHP files under the given directory.
	 *
	 * @param string $dir Absolute directory path.
	 * @return string[]
	 */
	public function php_files( string $dir ): array {
		return $this->collect( $dir, 'php' );
	}

	/**
	 * Returns all non-minified JS files under the given directory.
	 *
	 * @param string $dir Absolute directory path.
	 * @return string[]
	 */
	public function js_files( string $dir ): array {
		return array_values(
			array_filter(
				$this->collect( $dir, 'js' ),
				static fn( string $f ) => ! str_ends_with( $f, '.min.js' )
			)
		);
	}

	/**
	 * Recursively collects files of a given extension, skipping excluded directories.
	 *
	 * @param string $dir Absolute directory path.
	 * @param string $ext File extension without dot.
	 * @return string[]
	 */
	private function collect( string $dir, string $ext ): array {
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS )
		);

		$files = array();
		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() || $ext !== $file->getExtension() ) {
				continue;
			}
			$path = $file->getPathname();
			foreach ( self::SKIP_DIRS as $skip ) {
				if ( str_contains( $path, '/' . $skip . '/' ) ) {
					continue 2;
				}
			}
			$files[] = $path;
		}

		return $files;
	}
}
