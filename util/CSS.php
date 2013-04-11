<?php

namespace li3_assets\util;

use Exception;
use lessc;

/**
 *
 */
class CSS {

	/**
	 *
	 */
	public static $debug = false;

	/**
	 *
	 */
	public static function compileLess($input, $output) {
		try {
			// Load the cache if it exists
			$cacheFile = $input . ".cache";

			if (file_exists($cacheFile)) {
				$cache = unserialize(file_get_contents($cacheFile));
			} else {
				$cache = $input;
			}

			$newCacheFile = lessc::cexecute($cache);

			if (!is_array($cache) || $newCacheFile['updated'] > $cache['updated']) {
				file_put_contents($cacheFile, serialize($newCacheFile));
				file_put_contents($output, $newCacheFile['compiled']);
			}

			return $newCacheFile['updated'];
		} catch (Exception $ex) {
			if (self::$debug) {
				throw $ex;
			}
		}
	}

	/**
	 *
	 */
	public static function compress($input) {
		$css = '';

		// remove comments
		$css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $input);
		// remove tabs, spaces, newlines, etc.
		$css = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $css);
		// remove single spaces next to braces (can't remove single spaces everywhere, but we can in a few places)
		$css = str_replace(array('{ ', ' {', '; }'), array('{', '{', ';}'), $css);

		return $css;
	}
}

?>