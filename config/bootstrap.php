<?php

use lithium\core\Libraries;

$library = Libraries::get('li3_assets');
$librariesPath = $library['path'] . DIRECTORY_SEPARATOR . 'libraries' . DIRECTORY_SEPARATOR;

$lessPhp = $librariesPath . 'lessphp' . DIRECTORY_SEPARATOR . 'lessc.inc.php';
require_once $lessPhp;

$cssTidy = $librariesPath . 'csstidy' . DIRECTORY_SEPARATOR . 'CssTidy.php';
require_once $cssTidy;

$jsMinPhp = $librariesPath . 'jsminphp' . DIRECTORY_SEPARATOR . 'JSMin.php';
require_once $jsMinPhp;

$packer = $librariesPath . 'packer' . DIRECTORY_SEPARATOR . 'JavaScriptPacker.php';
require_once $jsMinPhp;

?>