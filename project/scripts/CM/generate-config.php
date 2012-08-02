<?php

define("IS_CRON", true);
define('DIR_ROOT', dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR);
require_once DIR_ROOT . 'library/CM/Bootloader.php';
CM_Bootloader::load(array('Autoloader', 'constants', 'exceptionHandler', 'errorHandler', 'defaults'));

try {
	// Create class types and action verbs config PHP
	$fileHeader = <<<EOS
<?php

// This is autogenerated action verbs config file. You should not adjust changes manually.
// You should adjust TYPE constants and regenerate file using scripts/CM/generate-config.php

EOS;
	$path = DIR_ROOT . 'config/internal.php';
	$classTypesConfig  = CM_App::getInstance()->generateClassTypesConfig();
	$actionVerbsConfig = CM_App::getInstance()->generateActionVerbsConfig();
	CM_File::create($path, $fileHeader . $classTypesConfig . PHP_EOL . PHP_EOL . $actionVerbsConfig);
	echo 'create  ' . $path . PHP_EOL;

	// Create model class types and action verbs config JS
	$path = DIR_ROOT . 'config/js/internal.js';
	$modelTypesConfig = 'cm.mode.types = ' . CM_Params::encode(CM_App::getInstance()->getClassTypes('CM_Model_Abstract'), true) . ';';
	$actionVerbsConfig = 'cm.action.verbs = ' . CM_Params::encode(CM_App::getInstance()->getActionVerbs(), true) . ';';
	CM_File::create($path, $modelTypesConfig . PHP_EOL . $actionVerbsConfig);
	echo 'create  ' . $path . PHP_EOL;

} catch (Exception $e) {
	echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}