<?php

App::uses('DebugPanel', 'DebugKit.Lib');
App::uses('DataCopyDebug', 'DataCopy.Lib');

/**
* My Custom Panel
*/
class DataCopyStatsPanel extends DebugPanel {

	public $plugin = 'DataCopy';

	public function beforeRender(Controller $controller) {
		$variables = array();
		$variables['expired'] = DataCopyDebug::$expiredLookups;
		$variables['copied'] = DataCopyDebug::$copiedLookups;

		return $variables;
	}


}