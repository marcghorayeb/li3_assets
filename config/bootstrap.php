<?php
use lithium\core\Libraries;
use lithium\net\http\Media;

$library = Libraries::get('li3_assets');

$lessPhp = $library['path'].DIRECTORY_SEPARATOR.'lessphp'.DIRECTORY_SEPARATOR.'lessc.inc.php';
require_once $lessPhp;

$cssTidy = $library['path'].DIRECTORY_SEPARATOR.'csstidy'.DIRECTORY_SEPARATOR.'CssTidy.php';
require_once $cssTidy;

$jsMinPhp = $library['path'].DIRECTORY_SEPARATOR.'jsminphp'.DIRECTORY_SEPARATOR.'JSMin.php';
require_once $jsMinPhp;

$packer = $library['path'].DIRECTORY_SEPARATOR.'packer'.DIRECTORY_SEPARATOR.'JavaScriptPacker.php';
require_once $jsMinPhp;
?>