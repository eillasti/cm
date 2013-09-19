<?php

class CM_App_Cli extends CM_Cli_Runnable_Abstract {

	public function setup() {
		$this->_getOutput()->writeln('Setting up filesystem…');
		CM_App::getInstance()->setupFilesystem();
		$this->_getOutput()->writeln('Setting up database…');
		CM_App::getInstance()->setupDatabase();
	}

	public function fillCaches() {
		$this->_getOutput()->writeln('Warming up caches…');
		CM_App::getInstance()->fillCaches();
	}

	public function deploy() {
		$this->setup();

		$dbCli = new CM_Db_Cli($this->_getInput(), $this->_getOutput());
		$dbCli->runUpdates();
	}

	public function generateConfig() {
		// Create class types and action verbs config PHP
		$fileHeader = '<?php' . PHP_EOL;
		$fileHeader .= '// This is autogenerated action verbs config file. You should not adjust changes manually.' . PHP_EOL;
		$fileHeader .= '// You should adjust TYPE constants and regenerate file using `config generate` command' . PHP_EOL;
		$path = DIR_ROOT . 'resources/config/internal.php';
		$classTypesConfig = CM_App::getInstance()->generateConfigClassTypes();
		$actionVerbsConfig = CM_App::getInstance()->generateConfigActionVerbs();
		CM_File::create($path, $fileHeader . $classTypesConfig . PHP_EOL . PHP_EOL . $actionVerbsConfig . PHP_EOL);
		$this->_getOutput()->writeln('Created `' . $path . '`');

		// Create model class types and action verbs config JS
		$path = DIR_ROOT . 'resources/config/js/internal.js';
		$modelTypesConfig = 'cm.model.types = ' . CM_Params::encode(CM_App::getInstance()->getClassTypes('CM_Model_Abstract'), true) . ';';
		$actionTypesConfig = 'cm.action.types = ' . CM_Params::encode(CM_App::getInstance()->getClassTypes('CM_Action_Abstract'), true) . ';';
		$actionVerbs = array();
		foreach (CM_App::getInstance()->getActionVerbs() as $verb) {
			$actionVerbs[$verb['name']] = $verb['value'];
		}
		$actionVerbsConfig = 'cm.action.verbs = ' . CM_Params::encode($actionVerbs, true) . ';';
		CM_File::create($path, $modelTypesConfig . PHP_EOL . $actionTypesConfig . PHP_EOL . $actionVerbsConfig . PHP_EOL);
		$this->_getOutput()->writeln('Created `' . $path . '`');
	}

	public function generateLocalConfig($configName) {
		$configName = (string) $configName;
		$configPath = DIR_ROOT . 'resources' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR;
		$sourceFile = new CM_File($configPath . $configName . '.json');
		$generator = new CM_Config_Generator($sourceFile);
		$sourceCode = $generator->generateOutput();
		CM_File::create($configPath . $configName . '.php', $sourceCode);
	}

	public static function getPackageName() {
		return 'app';
	}
}
