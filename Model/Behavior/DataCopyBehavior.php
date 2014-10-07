<?php

App::uses('DataCopyDebug', 'DataCopy.Lib');
App::uses('DebugTimer', 'DebugKit.Lib');

class DataCopyBehavior extends ModelBehavior {

	public function setup(Model $Model, $config = array()) {
		if (!isset($config['expire'])) {
			$config['expire'] = 60;
		}
		$this->settings[$Model->alias] = $config;

		$Model->OriginModel = ClassRegistry::init($config['origin']);

		$Model->primaryKey = $Model->OriginModel->primaryKey;
		$Model->displayField = $Model->OriginModel->displayField;
	}

	public function beforeFind(Model $Model, $query) {
		if (!isset($query['copy'])) {
			$query['copy'] = null;
		}
		if (is_array($query['fields'])) {
			$query['fields'][] = $Model->alias . '.data_copy_modified';
		}

		$Model->query = $query;

		return $query;
	}

	public function afterFind(Model $Model, $results, $primary = false) {
		if (CakePlugin::loaded('DebugKit')) {
			DebugTimer::start(
				'data-copy-after-find',
				__d('data_copy', 'DataCopy: Running after find on %1$s with origin %2$s', $Model->alias, $Model->OriginModel->alias)
			);
		}

		$query = $Model->query;

		if (CakePlugin::loaded('DebugKit')) {
			DebugTimer::start(
				'data-copy-list-old',
				__d('data_copy', 'DataCopy: Checking for old data from %1$s', $Model->alias)
			);
		}
		$oldestItems = array_map('strtotime', array_values($Model->find('list', array(
			'fields' => array(
				$Model->alias . '.' . $Model->primaryKey,
				$Model->alias . '.data_copy_modified',
			),
			'order' => array(
				$Model->alias . '.data_copy_modified' => 'ASC',
			),
			'limit' => 10,
			'copy' => true,
			'callbacks' => false
		))));
		if (CakePlugin::loaded('DebugKit')) {
			DebugTimer::stop('data-copy-list-old');
		}
		$oldestData = null;
		foreach ($oldestItems as $oldestItemInArray) {
			if ($oldestItemInArray < $oldestData) {
				$oldestData = $oldestItemInArray;
			}
		}

		if ($query['copy'] === false) {
			DataCopyDebug::logExpiredLookup($Model, $Model->OriginModel, $query, __d('data_copy', 'Force update'));

			$results = false;
		}

		if (($query['copy'] === null) && ($oldestData !== null) && (time() - $oldestData > $this->settings[$Model->alias]['expire'])) {
			DataCopyDebug::logExpiredLookup($Model, $Model->OriginModel, $query, __d('data_copy', 'Regular expire. Oldest data: %1$s', date('r', $oldestData)));

			$results = false;
		}

		if (is_array($results)) {
			DataCopyDebug::logLookup($Model, $Model->OriginModel, $query);

			if (CakePlugin::loaded('DebugKit')) {
				DebugTimer::stop('data-copy-after-find');
			}

			return $results;
		}

		$Model->OriginModel->recursive = $Model->recursive;

		$queryData = $query;
		unset($queryData['conditions']);
		if (is_array($query['conditions'])) {
			foreach ($query['conditions'] as $field => $condition) {
				$queryData['conditions'][$this->convertField($Model, $field)] = $condition;
			}
		}
		if (is_array($query['order'][0])) {
			foreach ($query['order'][0] as $field => $direction) {
				unset($queryData['order'][0][$field]);
				$queryData['order'][$this->convertField($Model, $field)] = $direction;
			}
		}
		unset($queryData['order'][0]);

		if (is_array($queryData['fields'])) {
			foreach ($queryData['fields'] as $index => &$field) {
				$startQuote = isset($Model->getDataSource()->startQuote) ? $Model->getDataSource()->startQuote : null;
				$endQuote = isset($Model->getDataSource()->endQuote) ? $Model->getDataSource()->endQuote : null;
				$field = str_replace(array($startQuote, $endQuote), '', $field);

				$field = $this->convertField($Model, $field);

				if ($field === $Model->OriginModel->alias . '.data_copy_modified') {
					unset($queryData['fields'][$index]);
				}
			}
		}

		$originResults = $Model->OriginModel->find('all', $queryData);

		foreach ($originResults as $index => &$result) {
			$result[$Model->alias] = $result[$Model->OriginModel->alias];
			unset($result[$Model->OriginModel->alias]);

			foreach ($result as $association => $data) {
				foreach ($data as $field => &$value) {
					$value = $this->convertValue($Model, $field, $value);

					$originResults[$index][$association][$field] = $value;
				}

				$originResults[$index][$association]['data_copy_modified'] = date($Model->getDataSource()->columns['datetime']['format']);
			}
		}

		foreach ($Model->getAssociated('belongsTo') as $association) {
			if (!isset($Model->belongsTo[$association]['origin'])) {
				continue;
			}

			$origin = $Model->belongsTo[$association]['origin'];

			foreach ($originResults as $index => &$result) {
				if (!isset($result[$origin])) {
					continue;
				}

				$data = $result[$origin];

				unset($originResults[$index][$origin]);
				$originResults[$index][$association] = $data;
			}
		}

		if (CakePlugin::loaded('DebugKit')) {
			DebugTimer::start(
				'data-copy-save-all',
				__d('data_copy', 'DataCopy: Saving data in %1$s from origin %2$s', $Model->alias, $Model->OriginModel->alias)
			);
		}
		$Model->saveAll($originResults, array('deep' => true));
		if (CakePlugin::loaded('DebugKit')) {
			DebugTimer::stop('data-copy-save-all');
		}

		if (!is_array($query['order'][0])) {
			$query['order'] = array();
		} else {
			$query['order'] = $query['order'][0];
		}

		$query['copy'] = true;
		$results = $Model->find('all', $query);

		if (CakePlugin::loaded('DebugKit')) {
			DebugTimer::stop('data-copy-after-find');
		}

		return $results;
	}

	public function convertValue(Model $Model, $field, $value) {
		if ($value === null) {
			return null;
		}

		if ($Model->OriginModel->schema($field)['type'] === 'date') {
			return date($Model->getDataSource()->columns['date']['format'], strtotime($value));
		}
		if ($Model->OriginModel->schema($field)['type'] === 'time') {
			return date($Model->getDataSource()->columns['time']['format'], strtotime($value));
		}

		return $value;
	}

	public function convertField(Model $Model, $field) {
		$fieldParts = explode('.', $field);
		if (isset($fieldParts[1])) {
			if ($fieldParts[0] === $Model->alias) {
				$fieldParts[0] = $Model->OriginModel->alias;
			}
		} else {
			$fieldName = $fieldParts[0];
			$fieldParts[0] = $Model->OriginModel->alias;
			$fieldParts[1] = $fieldName;
		}

		return implode('.', $fieldParts);
	}

}