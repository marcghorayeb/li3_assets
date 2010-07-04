<?php

/**
 * http://pastium.org/view/e34cc24524c233971d98023ce62f78fe
 *
 */

namespace li3_assets\extensions;

use \SplFileInfo;

class AssetHandler extends \lithium\core\StaticObject {

	public static $formats = array('jpeg', 'jpg', 'jpe', 'png', 'gif');

	public static function encodeImage($file) {
		$file = is_object($file) ? $file : new SplFileInfo($file);

		return static::_filter(__FUNCTION__, compact('file'), function($self, $params, $chain) {
			extract($params);
			$path = $file->getPathName();
			$format = substr($path, strrpos($path, '.') + 1);

			if (!in_array($format, $self::$formats)) {
				return false;
			}
			$data = base64_encode(file_get_contents($path));
			return "data:image/{$format};base64,{$data}";
		});
	}
}

?>