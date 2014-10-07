<?php

class DataCopyDebug {

	public static $expiredLookups = array();
	public static $copiedLookups  = array();

	public static function logLookup(Model $Model, Model $OriginModel, $query) {
		DataCopyDebug::$copiedLookups[] = array(
			'model' => $Model,
			'origin' => $OriginModel,
			'query' => $query,
		);
	}

	public static function logExpiredLookup(Model $Model, Model $OriginModel, $query, $reason) {
		DataCopyDebug::$expiredLookups[] = array(
			'model' => $Model,
			'origin' => $OriginModel,
			'query' => $query,
			'reason' => $reason,
		);
	}

}