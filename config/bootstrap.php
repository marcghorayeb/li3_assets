<?php

use lithium\core\Libraries;

$library = Libraries::get('li3_assets');

$lessPhp = $library['path'] . DIRECTORY_SEPARATOR . 'libraries' . DIRECTORY_SEPARATOR . 'lessphp' . DIRECTORY_SEPARATOR . 'lessc.inc.php';
require_once $lessPhp;

$jsMinPhp = $library['path'] . DIRECTORY_SEPARATOR . 'libraries' . DIRECTORY_SEPARATOR . 'jsminphp' . DIRECTORY_SEPARATOR . 'JSMin.php';
require_once $jsMinPhp;

?>