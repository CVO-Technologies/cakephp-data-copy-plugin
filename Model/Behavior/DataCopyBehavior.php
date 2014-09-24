<?php

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
			$query['copy'] = true;
		}

		$Model->query = $query;

		return $query;
	}

	public function afterFind(Model $Model, $results, $primary = false) {
		$query = $Model->query;

		$newestData = null;
		foreach ($results as $result) {
			foreach ($result as $association => $data) {
				if (!isset($data['data_copy_modified'])) {
					continue;
				}
				if (($newestData === null) || (strtotime($data['data_copy_modified']) > $newestData)) {
					$newestData = strtotime($data['data_copy_modified']);
				}
			}
		}

		foreach ($results as $index => $result) {
			foreach ($result as $association => $data) {
				foreach ($data as $field => $value) {
					$unserializedValue = @unserialize($value);
					if ($unserializedValue === false) {
						continue;
					}
					$results[$index][$association][$field] = $unserializedValue;
				}
			}
		}

		if ($query['copy'] === false) {
			$results = false;
		}

		if (($newestData !== null) && (time() - $newestData > $this->settings[$Model->alias]['expire'])) {
			$results = false;
		}

		if (($newestData !== null) && (time() - $newestData > 60)) {
			$results = false;
		}

		if ($results) {
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
			foreach ($queryData['fields'] as &$field) {
				$startQuote = isset($Model->getDataSource()->startQuote) ? $Model->getDataSource()->startQuote : null;
				$endQuote = isset($Model->getDataSource()->endQuote) ? $Model->getDataSource()->endQuote : null;
				$field = str_replace(array($startQuote, $endQuote), '', $field);

				$field = $this->convertField($Model, $field);
			}
		}

//		debug($Model->alias);
//		debug($queryData);

		$originResults = $Model->OriginModel->find('all', $queryData);
//		debug($originResults);

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

		$Model->saveAll($originResults, array('deep' => true));

		if (!is_array($query['order'][0])) {
			$query['order'] = array();
		} else {
			$query['order'] = $query['order'][0];
		}

		$query['copy'] = true;
		$results = $Model->find('all', $query);

		foreach ($results as $index => $result) {
			foreach ($result as $association => $data) {
				foreach ($data as $field => $value) {
					$unserializedValue = @unserialize($value);
					if ($unserializedValue === false) {
						continue;
					}
					$results[$index][$association][$field] = $unserializedValue;
				}
			}
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
		if ((is_array($value)) || (is_object($value))) {
			return serialize($value);
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