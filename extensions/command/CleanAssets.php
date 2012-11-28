<?php

namespace li3_assets\extensions\command;

use lessc;

use lithium\core\Libraries;
use lithium\net\http\Media;

/**
 * @todo
 */
class CleanAssets extends \lithium\console\Command {

	/**
	 *
	 */
	public function run() {
		$this->_resultNb = 0;

		$webroot = Media::webroot(true);

		$this->_cleanCss($webroot, '.less.css');
		$this->_cleanCss($webroot . '/' . $this->_getOptimizedDirectory(), '.css');
		$this->_processDirectory($webroot);

		$this->out('Compiled ' . $this->_resultNb . ' files.');

		return true;
	}

	/**
	 *
	 */
	public function flushOptimized() {
		$webroot = Media::webroot(true) . '/lib';
		$this->_cleanCss($webroot . '/' . $this->_getOptimizedDirectory(), '.css');
		return true;
	}

	/**
	 *
	 */
	protected function _cleanCss($dir, $suffix) {
		$this->out('Cleaning ' . $dir);

		$files = array_diff(scandir($dir), array('..', '.', '.DS_Store', 'optimized'));
		foreach ($files as $file) {
			if (is_dir($dir . '/' . $file)) {
				$this->_cleanCss($dir . '/' . $file, $suffix);
			} elseif(strpos($file, $suffix) !== false) {
				unlink($dir . '/' . $file);
			}
		}
	}

	/**
	 *
	 */
	protected function _processDirectory($dir) {
		$this->out('Scanning ' . $dir);

		$files = array_diff(scandir($dir), array('..', '.', '.DS_Store', 'optimized'));
		foreach ($files as $file) {
			if (is_dir($dir . '/' . $file)) {
				$this->_processDirectory($dir . '/' . $file);
			} elseif($this->_isLessFile($file)) {
				$this->_compileFile($dir . '/' . $file);
			}
		}
	}

	/**
	 *
	 */
	protected function _compileFile($file) {
		$this->out('Compiling ' . $file);

		$cacheFile = $file . '.cache';
		$cssFile = $file . '.css';

		$less = lessc::cexecute($file);

		file_put_contents($cacheFile, serialize($less));
		file_put_contents($cssFile, $less['compiled']);

		$this->_resultNb++;
	}

	/**
	 *
	 */
	protected function _isLessFile($file) {
		return strpos($file, '.less') !== false && strpos($file, '.less.cache') === false && strpos($file, 'less.css') === false && strpos($file, '.dist') === false;
	}

	/**
	 * @todo
	 */
	protected function _getOptimizedDirectory() {
		$config = Libraries::get('li3_assets');
		return $config['css']['output_directory'];
	}
}

?>