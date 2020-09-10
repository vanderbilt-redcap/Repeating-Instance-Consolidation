<?php
/**
 * Created by PhpStorm.
 * User: mcguffk
 * Date: 3/24/2020
 * Time: 1:54 PM
 */

/** @var \Vanderbilt\RepeatingInstanceConsolidation\RepeatingInstanceConsolidation $module */
## Save any provided reconciliation data
$module->saveReconciliationData();

$recordId = $_GET['id'];
$projectId = $_GET['pid'];

$tableData = $module->getComparisonData($projectId,$recordId);

$combinedData = $tableData["combined"];
$comparisonData = $tableData["comparison"];
$antibodiesPresent = $tableData["antibodies"];
$fieldList = $tableData["fields"];

$metadata = $module->getMetadata($projectId);
$outputLabelList = [];

$labelList = [];
foreach($fieldList as $fieldName) {
	if($metadata[$fieldName]["field_type"] == "checkbox") {
		$labelList[$fieldName] = $module->getChoiceLabels($fieldName,$projectId);
	}
}

$outputHeaders = [];
$outputHeadersCheckboxes = [];

## Foreach each label value, check if antibody was ever present and only output if it was
foreach($labelList as $fieldName => $fieldMapping) {
	$outputLabelList[$fieldName] = [];

	foreach($fieldMapping as $rawValue => $label) {
		if($antibodiesPresent[$rawValue]) {
			$outputLabelList[$fieldName][$rawValue] = $label;
		}
	}
	if($metadata[$fieldName]["field_type"] == "checkbox") {
		$outputHeaders[] = $metadata[$fieldName]["field_label"];

		$checkValues = [];

		foreach($outputLabelList[$fieldName] as $rawValue => $label) {
			$checkValues[] = $label;
		}

		$outputHeadersCheckboxes[] = $checkValues;
	}
}

$matchedKeys = array_merge(array_keys($combinedData[$module::$inputType]),(array_key_exists($module::$reconciledType,$combinedData) ? array_keys($combinedData[$module::$reconciledType]) : []));

$matchedKeys = array_unique($matchedKeys);
sort($matchedKeys);
$outputDetails = [];

## Pull data for
foreach($matchedKeys as $matchingValue) {
	$matchedData = explode("~",$matchingValue);
	$matchingString = "";

	foreach($matchedData as $outputValue) {
		$s = $outputValue;
		$date = strtotime($s);
		$matchingString .= date('m/d/y', $date);
	}

	$wasReconciled = array_key_exists($matchingValue,$combinedData[$module::$reconciledType]);

	foreach([$module::$reconciledType,$module::$inputType] as $thisType) {
		$matchedDataDetails = $combinedData[$thisType][$matchingValue];

		$mismatchedValues = false;
		if($thisType == $module::$inputType) {
			$mismatchedValues = $module->getMismatchedValues($matchedDataDetails);
		}

		foreach($matchedDataDetails as $formName => $formDetails) {
			foreach($formDetails as $instanceId => $instanceDetails) {
				$outputRow = [
					"type" => $thisType,
					"form" => $formName,
					"instance" => $instanceId,
					"record" => $recordId,
					"reconciled" => $wasReconciled,
					"start-hidden" => ($wasReconciled && $thisType == $module::$inputType),
					"matched-string" => $matchingString,
					"matched-value" => $matchingValue,
					"data" => []
				];

				$fieldKey = 0;
				foreach($outputLabelList as $fieldName => $fieldDetails) {
					foreach($fieldDetails as $rawValue => $label) {
						$outputRow["data"][$fieldKey][$rawValue] = [
							"issue" => ($mismatchedValues && $mismatchedValues[$fieldKey][$rawValue]),
							"value" => $instanceDetails[$fieldKey][$rawValue],
							"unmatched" => array_sum($comparisonData[$matchingValue][$fieldKey][$rawValue]) <= 1
						];
					}
					$fieldKey++;
				}

				$outputDetails[] = $outputRow;
			}
		}
	}
}

$unacceptableList = [];

## Pull unacceptable antigen list
foreach($combinedData[$module::$outputType] as $formName => $formDetails) {
	$fieldKey = 0;
	foreach($outputLabelList as $fieldName => $fieldDetails) {
		foreach($fieldDetails as $rawValue => $label) {
			$unacceptableList[$fieldKey][$rawValue] = 0;
			if($formDetails[$fieldKey][$rawValue]) {
				$unacceptableList[$fieldKey][$rawValue] = 1;
			}
		}
		$fieldKey++;
	}
}

$twigLoader = new Twig_Loader_Filesystem(__DIR__."/templates");
$twig = new Twig_Environment($twigLoader);

$renderVars = [
	"fieldList" => $outputHeaders,
	"fieldDetails" => $outputHeadersCheckboxes,
	"outputData" => $outputDetails,
	"unacceptableList" => $unacceptableList
];

$html = $twig->render("reconciliation.twig",$renderVars);

echo $html;
