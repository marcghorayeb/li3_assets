<?php

namespace li3_assets\extensions\helper;

use lessc;
use CssTidy;

use lithium\core\Environment;
use lithium\core\Libraries;
use lithium\net\http\Media;
use lithium\storage\Cache;

use li3_assets\jsminphp\JSMin;
use li3_assets\packer\JavaScriptPacker;

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
	protected function _init() {
		parent::_init();

		$this->_conf = Libraries::get('li3_assets');
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
	 * @todo fix packer
	 */
	public function scripts() {
		$library = $this->_conf;

		// Set Defaults
		$options = $library['config']['js'] + array(
			'compression' => 'packer', // possible values: 'jsmin', 'packer', false (true uses jsmin)
			'output_directory' => 'webroot/js', // directory is from webroot/js if full path is not defined
			'packer_encoding' => 'Normal', // level of encoding (only used for packer), possible values: 0,10,62,95 or 'None', 'Numeric', 'Normal', 'High ASCII'
			'packer_fast_decode' => true, // default: true
			'packer_special_chars' => false, // default: false
			'useWeights' => false,
			'emptyContext' => false,
			'useCacheExistence' => false
		);

		$output = '';
		$webroot = Media::webroot(true);

		foreach ($this->_scripts as $context => $scripts) {

			// Before any compression and combination is done, order the scripts.
			// Sort them all by a "weight" key set like: $this->html->script('myscript.js', array('weight' => 1))
			if ($options['useWeights']) {
				Optimize::_orderWeights($scripts);
			}

			Optimize::_cleanOutputDirectory($options['output_directory']);

			$md5 = '';
			foreach ($scripts as $path => $script) {
				if (preg_match('/lib\/(.*)"/', $script, $matches)) {
					$script = $webroot . Media::asset($matches[1], 'js');
					if(file_exists($script)) {
						$md5 .= $script . filemtime($script);
					}
				}
			}

			$outputHash = md5($md5);
			$outputFile = Media::asset($options['output_directory'] . DIRECTORY_SEPARATOR . $outputHash, 'js');
			$outputFolder = $webroot . strstr($outputFile, $outputHash, true);

			// If the output directory doesn't exist, return the scripts like normal... TODO: also ensure permissions to write here?
			if (!file_exists($outputFolder)) {
				// If it doesn't exist, try to create it
				if (!mkdir($outputFolder, 0777, true)) {
					die('Failed to create folders...');
				}

				// If it still doesn't exist, return the scripts
				if(!file_exists($outputFolder)) {
					return $output;
				}
			}

			if (!empty($options['compression'])) {
				$key = 'ASSETS.Scripts.' . $outputFile;
				$cacheExistence = $options['useCacheExistence'] ? Cache::read('default', $key) : false;

				if (!$options['useCacheExistence'] || empty($cacheExistence)) {
					if (!file_exists($webroot . $outputFile)) {
						$js = '';

						// JSMin
						if ($options['compression'] === true || $options['compression'] == 'jsmin') {
							foreach ($scripts as $file) {
								if(preg_match('/"(http:\/\/.+?)"/', $file, $matches)) {
									$js .= JSMin::minify(file_get_contents($matches[1]));
									continue;
								}

								if (preg_match('/lib\/(.*)"/', $file, $matches)) {
									$script = $webroot . Media::asset($matches[1], 'js');

									// It is possible that a reference to a file that does not exist was passed
									if(file_exists($script)) {
										$js .= JSMin::minify(file_get_contents($script));
									}
								}
							}
						}
						// Dean Edwards Packer
						elseif ($options['compression'] == 'packer') {
							foreach($scripts as $file) {
								if(preg_match('/"(http:\/\/.+?)"/', $file, $matches)) {
									$packer = new JavaScriptPacker(
										file_get_contents($matches[1]),
										$options['packer_encoding'],
										$script,
										$options['packer_fast_decode'],
										$script,
										$options['packer_special_chars']
									);

									$js .= $packer->pack();
									continue;
								}

								if(preg_match('/\/lib\/(.*)"/', $file, $matches)) {
									$script = $webroot . Media::asset($matches[1], 'js');

									// It is possible that a reference to a file that does not exist was passed
									if(file_exists($script)) {
										$packer = new JavaScriptPacker(
											file_get_contents($script),
											$options['packer_encoding'],
											$script,
											$options['packer_fast_decode'],
											$script,
											$options['packer_special_chars']
										);

										$js .= $packer->pack();
									}
								}
							}
						}

						if (file_put_contents($webroot . $outputFile, $js) !== false && $options['useCacheExistence']) {
							Cache::write('default', $key, true, '+1 week');
						}
					}
				}

				// One last safety check to ensure the file is there (reasons why it may not be: primarily, write permissions)
				if($options['useCacheExistence'] && !empty($cacheExistence) || file_exists($webroot . $outputFile)) {
					if ((Environment::is('production') || Environment::is('preproduction')) && !empty($this->_conf['config']['js']['host'])) {
						$output .= '<script type="text/javascript" src="' . $this->_conf['config']['js']['host'] . Media::asset($outputFile, 'js') . '"></script>';
					} else {
						$output .= '<script type="text/javascript" src="' . Media::asset($outputFile, 'js') . '"></script>';
					}
				}
			}
			else {
				$output .= $this->_filterExistence($scripts, 'js');
			}

			if ($options['emptyContext']) {
				$this->_scripts[$context] = array();
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
	 * @todo fix csstidy, paths based on media library, and emptyContext functionality
	 */
	public function styles(array $options = array()) {
		$library = $this->_conf;

		// Set Defaults
		$options = $library['config']['css'] + array(
			'compression' => true, // possible values: "tidy", true, false
			'tidy_template' => 'highest_compression', // possible values: "high_compression", "highest_compression", "low_compression", or "default"
			'less_debug' => false, // sends lessphp error message to a log file, possible values: true, false
			'output_directory' => 'webroot/css',
			'useWeights' => false, // directory is from webroot/css if full path is not defined
			'emptyContext' => false,
			'useCacheExistence' => false
		);

		$output = '';

		foreach ($this->_styles as $context => $styles) {
			// Before any compression and combination is done, order the styles.
			// Sort them all by a "weight" key set like: $this->html->style('mystyle.css', array('weight' => 1))
			if ($options['useWeights']) {
				Optimize::_orderWeights($styles);
			}

			Optimize::_cleanOutputDirectory($options['output_directory']);

			// Run any referenced .less files through lessphp first to retreive their timestamps
			$lessTimestamps = md5(serialize(Optimize::_compileLess($styles)));

			// Set the output path
			$webroot = Media::webroot(true);
			$outputHash = md5(serialize($styles) . serialize($lessTimestamps));
			$outputFile = Media::asset($options['output_directory'] . DIRECTORY_SEPARATOR . $outputHash, 'css');
			$outputFolder = $webroot . strstr($outputFile, $outputHash, true);

			// If the output directory doesn't exist, return the scripts like normal...
			if(!file_exists($outputFolder)) {
				// If it doesn't exist, try to create it
				if (!mkdir($outputFolder, 0777, true)) {
					die('li3_assets: Failed to create folders.');
				}
				// If it still doesn't exist, return the scripts
				if(!file_exists($outputFolder)) {
					return $output;
				}
			}

			// Check compression type and compress/combine
			if(!empty($options['compression'])) {
				$key = 'ASSETS.Styles.' . $outputFile;
				$cacheExistence = $options['useCacheExistence'] ? Cache::read('default', $key) : false;

				if (!$options['useCacheExistence'] || empty($cacheExistence)) {
					if (!file_exists($webroot.$outputFile)) {
						$css = '';

						// true is just basic compression and combination. Basically remove white spaces and line breaks where possible.
						if($options['compression'] === true) {
							$css = Optimize::_compressCss($styles);
						}
						// 'tidy' setting will run the css files through csstidy which not only removes white spaces and line breaks, but also shortens things like #000000 to #000, etc. where possible.
						elseif($options['compression'] == 'tidy') {
							$tidy = new CssTidy();
							$tidy->set_cfg('remove_last_;',TRUE);
							$tidy->load_template($options['tidy_template']);

							// Loop through all the css files, run them through tidy, and combine into one css file
							foreach($styles as $file) {
								if(preg_match('/"(http:\/\/.+?)"/', $file, $matches)) {
									$tidy->parse(file_get_contents($matches[1]));
									$css .= $tidy->print->plain();
									continue;
								}

								if(preg_match('/\/lib\/(.*)"/', $file, $matches)) {
									$sheet = $webroot.Media::asset($matches[1], 'css');
									// It is possible that a reference to a file that does not exist was passed
									if(file_exists($sheet)) {
										$tidy->parse(file_get_contents($sheet));
										$css .= $tidy->print->plain();
									}
								}
							}
						}

						if (file_put_contents($webroot . $outputFile, $css) !== false && $options['useCacheExistence']) {
							Cache::write('default', $key, true, '+1 week');
						}
					}
				}

				// One last safety check to ensure the file is there (reasons why it may not be: primarily, write permissions)
				if ($options['useCacheExistence'] && !empty($cacheExistence) || file_exists($webroot . $outputFile)) {
					if ((Environment::is('production') || Environment::is('preproduction')) && !empty($this->_conf['config']['css']['host'])) {
						$output .= '<link rel="stylesheet" type="text/css" href="' . $this->_conf['config']['css']['host'] . Media::asset($outputFile, 'css') . '"/>';
					} else {
						$output .= '<link rel="stylesheet" type="text/css" href="' . Media::asset($outputFile, 'css') . '"/>';
					}
				}
			}
			else {
				$output .= $this->_filterExistence($styles, 'css');
			}

			if ($options['emptyContext']) {
				$this->_styles[$context] = array();
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
			if(substr($params['path'], 0, 4) == 'http') {
				$file = $params['path'];
			} else {
				$file = Media::webroot(true).Media::asset($params['path'], 'image');
			}
			$data = base64_encode(file_get_contents($file));

			// Set the html options that go within the img tag
			$html_options = '';
			foreach($params['options'] as $k => $v) {
				$html_options .= $k.'="'.htmlspecialchars($v, ENT_QUOTES, 'UTF-8').'" ';
			}

			// Return the image URI
			return '<img src="data:image/'.$format.';base64,'.$data.'" '.$html_options.'/>';
		});
	}

	/**
	 *
	 */
	protected static function _cleanOutputDirectory(&$directory) {
		// Ensure output directory is formatted properly, first remove any beginning slashes
		if($directory[0] == DIRECTORY_SEPARATOR) {
			$directory = substr($directory, 1);
		}
		// ...then any trailing slashes
		if(substr($directory, -1, 1) == DIRECTORY_SEPARATOR) {
			$directory = substr($directory, 0, -1);
		}
	}

	/**
	 *
	 */
	protected static function _orderWeights(&$elements) {
		usort($elements, function($a, $b) {
			$a = (preg_match('/weight="([0-9].*)"/', $a, $matches)) ? $matches[1]:999;
			$b = (preg_match('/weight="([0-9].*)"/', $b, $matches)) ? $matches[1]:999;
			return ($a < $b) ? -1 : 1;
		});
	}

	/**
	 *
	 */
	protected static function _compileLess($scripts) {
		$timestamps = array();
		$webroot = Media::webroot(true);

		foreach ($scripts as $script) {
			// Skip any external css scripts
			if(preg_match('/"(http:\/\/.+?)"/', $script, $matches)) {
				continue;
			}

			// Run any referenced .less files through lessphp first
			if (preg_match('/\/lib\/(.*.less).css"/', $script, $matches)) {
				try {
					// fortunately, the Html::script() helper will automatically append .css, so the output file can just have .css appended too and match.
					$cssFile = $webroot . Media::asset($matches[1], 'css');
					$lessFile = substr($cssFile, 0, -4);

					// Load the cache if it exists
					$cacheFile = $lessFile . ".cache";

					if (file_exists($cacheFile)) {
						$cache = unserialize(file_get_contents($cacheFile));
					} else {
						$cache = $lessFile;
					}

					$newCacheFile = lessc::cexecute($cache);

					if (!is_array($cache) || $newCacheFile['updated'] > $cache['updated']) {
						file_put_contents($cacheFile, serialize($newCacheFile));
						file_put_contents($cssFile, $newCacheFile['compiled']);
					}

					$timestamps[] = $newCacheFile['updated'];
				}
				catch (Exception $ex) {
					if($options['less_debug'] === true) {
						throw $ex;
					}
				}
			}
		}

		return $timestamps;
	}

	/**
	 *
	 */
	protected static function _compressCss($scripts) {
		$css = '';
		$webroot = Media::webroot(true);

		foreach($scripts as $file) {
			if(preg_match('/"(http:\/\/.+?)"/', $file, $matches)) {
				$css .= file_get_contents($matches[1]);
				continue;
			}

			if(preg_match('/\/lib\/(.*)"/', $file, $matches)) {
				$sheet = $webroot.Media::asset($matches[1], 'css');

				// It is possible that a reference to a file that does not exist was passed
				if(file_exists($sheet)) {
					$css .= file_get_contents($sheet);
				}
			}
		}

		// remove comments
		$css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
		// remove tabs, spaces, newlines, etc.
		$css = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $css);
		// remove single spaces next to braces (can't remove single spaces everywhere, but we can in a few places)
		$css = str_replace(array('{ ', ' {', '; }'), array('{', '{', ';}'), $css);

		return $css;
	}

	/**
	 * Filters an array of HTML tags (scripts or css) of unavailable source files.
	 *
	 * @param array $scripts List of <scripts /> or <link />.
	 * @param string $type 'js' or 'css', depending on the type of tags in the $scripts array.
	 * @return string Imploded tags that are valid (ie, the source file exists).
	 */
	protected static function _filterExistence($scripts, $type) {
		$output = '';
		$webroot = Media::webroot(true);

		foreach($scripts as $file) {
			if(preg_match('/"(http:\/\/.+?)"/', $file, $matches)) {
				$output .= $file;
				continue;
			}

			if(preg_match('/\/lib\/(.*)"/', $file, $matches)) {
				$filepath = $webroot.Media::asset($matches[1], $type);

				// It is possible that a reference to a file that does not exist was passed
				if(file_exists($filepath)) {
					$output .= $file;
				}
			}
		}

		return $output;
	}
}
?>