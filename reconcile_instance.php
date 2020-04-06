<?php
/**
 * Created by PhpStorm.
 * User: mcguffk
 * Date: 4/3/2020
 * Time: 1:44 PM
 */

$recordId = $_GET['id'];
$projectId = $_GET['pid'];
$matchingValue = $_GET['matchedValue'];

/** @var \Vanderbilt\RepeatingInstanceConsolidation\RepeatingInstanceConsolidation $module */
$dataMapping = $module->getProjectSetting("existing-json",$projectId);
$dataMapping = json_decode($dataMapping,true);

$comparisonData = $module->getComparisonData($recordId,$projectId);
$matchingValues = explode("~",$matchingValue);
$instanceData = [];
$existingRecordData = $module->getData($projectId,$recordId);
$formToStart = false;
$instanceToStart = false;
$eventToStart = false;

foreach($dataMapping[$module::$reconciledType] as $thisForm => $formDetails) {
	foreach($formDetails[$module::$matchedFields] as $fieldKey => $matchedField) {
		$instanceData[$matchedField] = $matchingValues[$fieldKey];
	}

	foreach($comparisonData["comparison"][$matchingValue] as $fieldKey => $fieldDetails) {
		$fieldData = [];
		foreach($fieldDetails as $rawValue => $rawData) {
			if(array_key_exists(1,$rawData)) {
				$fieldData[$rawValue] = 1;
			}
			else {
				$fieldData[$rawValue] = 0;
			}
		}
		$instanceData[$formDetails[$module::$dataFields][$fieldKey]] = $fieldData;
	}

	if(count($instanceData) == 0) {
		continue;
	}

	## Find next instance for data reconciliation (and make sure an instance doesn't already exist
	foreach($existingRecordData[$recordId]["repeat_instances"] as $eventId => $eventDetails) {
		if(count($eventDetails[$thisForm]) == 0) {
			$maxInstance = 0;
		}
		else {
			$maxInstance = max(array_keys($eventDetails[$thisForm]));
		}

		foreach($eventDetails[$thisForm] as $instanceId => $instanceDetails) {
			$recordMatches = true;

			## Verify that instance doesn't already match
			foreach($formDetails[$module::$matchedFields] as $fieldKey => $matchedField) {
				$cleanedValue = str_replace("~","",$instanceDetails[$matchedField]);
				if($cleanedValue != $matchingValues[$fieldKey]) {
					$recordMatches = false;
					break;
				}
			}

			if($recordMatches) {
				$maxInstance = $instanceId - 1;
				break;
			}
		}

		$newRecordData = [
			$recordId => [
				"repeat_instances" => [
					$eventId => [
						$thisForm => [
							$maxInstance + 1 => $instanceData
						]
					]
				]
			]
		];
		
		
		$results = REDCap::saveData($projectId,"array",$newRecordData);

		if(count($results["errors"]) != 0) {
			echo "Error: Unable to copy data into the new instance<br />";
			var_dump($results["errors"]);
			break 2;
		}

		if(empty($formToStart)) {
			$formToStart = $thisForm;
			$instanceToStart = $maxInstance + 1;
		}
	}
}

header("Location: ".APP_PATH_WEBROOT_FULL.substr(APP_PATH_WEBROOT,1)."DataEntry/index.php?pid=$projectId&id=$recordId&page=$formToStart&instance=$instanceToStart&event_id=$eventToStart");