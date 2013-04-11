<?php

namespace li3_assets\extensions\helper;

use lithium\core\Environment;
use lithium\core\Libraries;
use lithium\net\http\Media;
use lithium\storage\Cache;
use lithium\util\String;

use li3_assets\jsminphp\JSMin;
use li3_assets\util\CSS;

/**
 *
 */
class Optimize extends \lithium\template\helper\Html {

	/**
	 *
	 */
	protected $_scripts = array();

	/**
	 *
	 */
	protected $_styles = array();

	/**
	 *
	 */
	protected $_conf = array();

	/**
	 *
	 */
	protected $_webroot;

	/**
	 *
	 */
	protected $_templates = array(
		'style' => '<link rel="stylesheet" type="text/css" href="{:path}"/>',
		'script' => '<script type="text/javascript" src="{:path}"></script>',
	);

	/**
	 *
	 */
	protected function _init() {
		parent::_init();

		$this->_conf = Libraries::get('li3_assets');
		$this->_webroot = Media::webroot(true);
	}

	/**
	 *
	 */
	public function asset($path, array $options = array()) {
		$this->style($path, $options);
		$this->script($path, $options);

		return '';
	}

	/**
	 *
	 */
	public function assets() {
		$styles = $this->styles();
		$scripts = $this->scripts();

		return $styles . $scripts;
	}

	/**
	 *
	 */
	public function script($path, array $options = array()) {
		$defaults = array('context' => 'layout');
		$options += $defaults;

		$options['inline'] = true;
		$context = $options['context'];
		unset($options['context']);

		$this->_scripts[$context][$path] = parent::script($path, $options);

		return '';
	}

	/**
	 * @todo use google closure library
	 */
	public function scripts() {
		$library = $this->_conf;

		// Set Defaults
		$options = $library['config']['js'] + array(
			'compression' => true, // possible values: true, false
			'output_directory' => 'webroot/js', // directory is from webroot/js if full path is not defined
			'useWeights' => false,
			'emptyContext' => false,
			'useCacheExistence' => false
		);

		$output = '';

		$this->_cleanOutputDirectory($options['output_directory']);

		foreach ($this->_scripts as $context => $scripts) {
			// Before any compression and combination is done, order the scripts.
			// Sort them all by a "weight" key set like: $this->html->script('myscript.js', array('weight' => 1))
			if ($options['useWeights']) {
				$this->_orderWeights($scripts);
			}

			$md5 = $this->_timestamps($scripts);

			$outputHash = md5(serialize($md5));
			$outputFile = Media::asset($options['output_directory'] . DIRECTORY_SEPARATOR . $outputHash, 'js');

			$absolutePath = $this->_webroot . $outputFile;
			$outputFolder = dirname($absolutePath) . DIRECTORY_SEPARATOR;

			$this->_checkFolderExistence($outputFolder);

			if (!empty($options['compression'])) {
				$key = 'ASSETS.Scripts.' . $outputFile;
				$cacheExistence = $options['useCacheExistence'] ? Cache::read('default', $key) : false;

				if (!$options['useCacheExistence'] || empty($cacheExistence)) {
					if (!file_exists($absolutePath)) {
						$js = $this->_compressJs($scripts);

						if (file_put_contents($absolutePath, $js) !== false && $options['useCacheExistence']) {
							Cache::write('default', $key, true, '+1 week');
						}
					}
				}

				// One last safety check to ensure the file is there (reasons why it may not be: primarily, write permissions)
				if ($options['useCacheExistence'] && !empty($cacheExistence) || file_exists($absolutePath)) {
					if ((Environment::is('production') || Environment::is('preproduction')) && !empty($options['host'])) {
						$outputFile = $options['host'] . $outputFile;
					}

					$output .= String::insert($this->_templates['script'], array('path' => $outputFile));
				}
			} else {
				$output .= $this->_filterExistence($scripts, 'js');
			}

			if ($options['emptyContext']) {
				unset($this->_scripts[$context]);
			}
		}

		return $output;
	}

	/**
	 *
	 */
	public function style($path, array $options = array()) {
		$defaults = array('context' => 'layout');
		$options += $defaults;

		$context = $options['context'];
		unset($options['context']);

		$options['inline'] = true;
		$info = pathinfo($path);

		if (!isset($info['extension']) || (isset($info['extension']) && strcmp($info['extension'], 'less') !== 0)) {
			$less = $path . '.less';
			$lessFile = str_replace('.css', '.less', Media::webroot(true) . Media::asset($path, 'css'));

			if (file_exists($lessFile)) {
				$path = $less;
			}
		}

		$this->_styles[$context][] = parent::style($path, $options);

		return '';
	}

	/**
	 * Outputs the compressed and combined CSS on the page.
	 * @todo paths based on media library
	 */
	public function styles(array $options = array()) {
		$library = $this->_conf;

		// Set Defaults
		$options = $library['config']['css'] + array(
			'compression' => true, // possible values: true, false
			'debug' => false, // throw less exceptions, possible values: true, false
			'output_directory' => 'webroot/css', // directory is from webroot/css if full path is not defined
			'useWeights' => false,
			'emptyContext' => false,
			'useCacheExistence' => false
		);

		$output = '';

		$this->_cleanOutputDirectory($options['output_directory']);

		foreach ($this->_styles as $context => $styles) {
			// Before any compression and combination is done, order the styles.
			// Sort them all by a "weight" key set like: $this->html->style('mystyle.css', array('weight' => 1))
			if ($options['useWeights']) {
				$this->_orderWeights($styles);
			}

			// Run any referenced .less files through lessphp first to compile them and retreive their timestamps
			$timestamps = $this->_compileLess($styles);

			// Set the output path
			$outputHash = md5(serialize($timestamps));
			$outputFile = Media::asset($options['output_directory'] . DIRECTORY_SEPARATOR . $outputHash, 'css');

			$absolutePath = $this->_webroot . $outputFile;
			$outputFolder = dirname($absolutePath) . DIRECTORY_SEPARATOR;

			$this->_checkFolderExistence($outputFolder);

			// Check compression type and compress/combine
			if (!empty($options['compression'])) {
				$key = 'ASSETS.Styles.' . $outputFile;
				$cacheExistence = $options['useCacheExistence'] ? Cache::read('default', $key) : false;

				if (!$options['useCacheExistence'] || empty($cacheExistence)) {
					if (!file_exists($absolutePath)) {
						$css = $this->_compressCss($styles);

						if (file_put_contents($absolutePath, $css) !== false && $options['useCacheExistence']) {
							Cache::write('default', $key, true, '+1 week');
						}
					}
				}

				// One last safety check to ensure the file is there (reasons why it may not be: primarily, write permissions)
				if ($options['useCacheExistence'] && !empty($cacheExistence) || file_exists($absolutePath)) {
					if ((Environment::is('production') || Environment::is('preproduction')) && !empty($options['host'])) {
						$outputFile = $options['host'] . $outputFile;
					}

					$output .= String::insert($this->_templates['style'], array('path' => $outputFile));
				}
			} else {
				$output .= $this->_filterExistence($styles, 'css');
			}

			if ($options['emptyContext']) {
				unset($this->_styles[$context]);
			}
		}

		return $output;
	}

	/*
	* Call this method at the top of a view template to apply a filter to return all images called with the Html::image() helper
	* in that template as base64 data URIs. Note: IE6 & 7 do not support data URIs.
	*/
	public function images() {
		$this->_context->Html->applyFilter('image', function($self, $params, $chain) {
			$library = $this->_conf;
			// Set defaults
			$library += array('config' => array());
			$library['config'] += array('image' => array());
			$library['config']['image'] += array(
				'compression' => true,
				'allowed_formats' => array('jpeg', 'jpg', 'jpe', 'png', 'gif')
			);

			// If the image is not in the list of allowed formats or compression is false, don't encode it, just display it as normal
			$format = substr($params['path'], strrpos($params['path'], '.') + 1);
			if ((!in_array($format, $library['config']['image']['allowed_formats'])) || ($library['config']['image']['compression'] !== true)) {
				return $chain->next($self, $params, $chain);
			}

			// Encode the image data
			if (substr($params['path'], 0, 4) == 'http') {
				$file = $params['path'];
			} else {
				$file = Media::webroot(true).Media::asset($params['path'], 'image');
			}
			$data = base64_encode(file_get_contents($file));

			// Set the html options that go within the img tag
			$html_options = '';
			foreach ($params['options'] as $k => $v) {
				$html_options .= $k.'="'.htmlspecialchars($v, ENT_QUOTES, 'UTF-8').'" ';
			}

			// Return the image URI
			return '<img src="data:image/'.$format.';base64,'.$data.'" '.$html_options.'/>';
		});
	}

	/**
	 *
	 */
	protected function _cleanOutputDirectory(&$directory) {
		// Ensure output directory is formatted properly, first remove any beginning slashes
		if ($directory[0] == DIRECTORY_SEPARATOR) {
			$directory = substr($directory, 1);
		}

		// ...then any trailing slashes
		if (substr($directory, -1, 1) == DIRECTORY_SEPARATOR) {
			$directory = substr($directory, 0, -1);
		}
	}

	/**
	 *
	 */
	protected function _orderWeights(&$elements) {
		usort($elements, function($a, $b) {
			$a = preg_match('/weight="([0-9].*)"/', $a, $matches) ? $matches[1] : 999;
			$b = preg_match('/weight="([0-9].*)"/', $b, $matches) ? $matches[1] : 999;
			return ($a < $b) ? -1 : 1;
		});
	}

	/**
	 * Filters an array of HTML tags (scripts or css) of unavailable source files.
	 *
	 * @param array $scripts List of <scripts /> or <link />.
	 * @param string $type 'js' or 'css', depending on the type of tags in the $scripts array.
	 * @return string Imploded tags that are valid (ie, the source file exists).
	 */
	protected function _filterExistence($scripts, $type) {
		$output = '';
		$webroot = Media::webroot(true);

		foreach ($scripts as $file) {
			if (preg_match('/"(http:\/\/.+?)"/', $file, $matches)) {
				$output .= $file;
				continue;
			}

			if (preg_match('/\/lib\/(.*)"/', $file, $matches)) {
				$filepath = $webroot.Media::asset($matches[1], $type);

				// It is possible that a reference to a file that does not exist was passed
				if (file_exists($filepath)) {
					$output .= $file;
				}
			}
		}

		return $output;
	}

	/**
	 *
	 */
	protected function _checkFolderExistence($folder) {
		if (!file_exists($folder)) {
			// If it doesn't exist, try to create it
			if (!mkdir($folder, 0777, true)) {
				throw new Exception("Failed to create {$folder}.");
			}

			// If it still doesn't exist, return the scripts
			if (!file_exists($folder)) {
				throw new Exception("{$folder} does not exist.");
			}
		}
	}

	/**
	 *
	 */
	protected function _timestamps($files) {
		$timestamps = array();

		foreach ($files as $path => $html) {
			if (preg_match('/lib\/(.*)"/', $html, $matches)) {
				$absolutePath = $this->_webroot . Media::asset($matches[1], 'js');

				if (file_exists($absolutePath)) {
					$timestamps[$absolutePath] = filemtime($absolutePath);
				}
			}
		}

		return $timestamps;
	}

	/**
	 * @todo do not base search on lib folder
	 */
	protected function _compileLess($scripts) {
		$timestamps = array();

		foreach ($scripts as $script) {
			// Skip any external css scripts
			if (preg_match('/"(http:\/\/.+?)"/', $script, $matches)) {
				continue;
			}

			// Run any referenced .less files through lessphp first
			if (preg_match('/\/lib\/(.*.less).css"/', $script, $matches)) {
				$cssFile = $this->_webroot . Media::asset($matches[1], 'css');
				$lessFile = substr($cssFile, 0, -4);

				$timestamps[$cssFile] = CSS::compileLess($lessFile, $cssFile);
			}
		}

		return $timestamps;
	}

	/**
	 *
	 */
	protected function _compressCss($styles) {
		$css = '';

		foreach ($styles as $file) {
			if (preg_match('/"(http:\/\/.+?)"/', $file, $matches)) {
				$css .= file_get_contents($matches[1]);
				continue;
			}

			if (preg_match('/\/lib\/(.*)"/', $file, $matches)) {
				$sheet = $this->_webroot . Media::asset($matches[1], 'css');

				// It is possible that a reference to a file that does not exist was passed
				if (file_exists($sheet)) {
					$css .= file_get_contents($sheet);
				}
			}
		}

		return CSS::compress($css);
	}

	/**
	 * @todo use google closure library
	 */
	protected function _compressJs($scripts) {
		$js = '';

		foreach ($scripts as $file) {
			if (preg_match('/"(http:\/\/.+?)"/', $file, $matches)) {
				$js .= JSMin::minify(file_get_contents($matches[1]));
				continue;
			}

			if (preg_match('/lib\/(.*)"/', $file, $matches)) {
				$script = $this->_webroot . Media::asset($matches[1], 'js');

				// It is possible that a reference to a file that does not exist was passed
				if (file_exists($script)) {
					$js .= JSMin::minify(file_get_contents($script));
				}
			}
		}

		return $js;
	}
}

?>