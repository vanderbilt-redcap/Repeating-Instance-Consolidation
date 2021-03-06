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
$eventId = $module->getFirstEventId($projectId);

$tableData = $module->getComparisonData($projectId,$recordId);

$combinedData = $tableData["combined"];
$comparisonData = $tableData["comparison"];
$antibodiesPresent = $tableData["antibodies"];
$fieldList = $tableData["fields"];

$displayData = reset(reset($module->getData($projectId,$recordId)));

$displayString = $displayData["rec_id"].": ".$displayData["rec_name"]." - ".$displayData["rec_dob"];

$metadata = $module->getMetadata($projectId);
$outputLabelList = [];

$labelList = [];
foreach($fieldList[$module::$inputType] as $fieldName) {
	if($metadata[$fieldName]["field_type"] == "checkbox") {
		$labelList[$fieldName] = $module->getChoiceLabels($fieldName,$projectId);
	}
}
//unreconciled type doesn't exist in settings so copy fields from actual reconciled fields
$fieldList[$module::$unreconciledType] = $fieldList[$module::$reconciledType];

$outputHeaders = [];
$outputHeadersCheckboxes = [];

## Foreach each label value, check if antibody was ever present and only output if it was
foreach($labelList as $fieldName => $fieldMapping) {
	$outputLabelList[$fieldName] = [];

	foreach($fieldMapping as $rawValue => $label) {
		## Leaving if statement present to allow filtering again in future if needed
//		if($antibodiesPresent[$rawValue]) {
			$outputLabelList[$fieldName][$rawValue] = $label;
//		}
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
$crossMatches = [];
$existingCrossMatches = [];
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
    $notReconciled = array_key_exists($matchingValue,$combinedData[$module::$unreconciledType]);
    if ($wasReconciled || $notReconciled && !in_array($matchingValue, $existingCrossMatches)) {
        $existingCrossMatches[] = $matchingValue;
    }
    $inputMatchedDataDetails = $combinedData[$module::$inputType][$matchingValue];
    
    
    
	foreach([$module::$unreconciledType, $module::$reconciledType,$module::$inputType] as $thisType) {
		$matchedDataDetails = $combinedData[$thisType][$matchingValue];
		
        $mismatchedValues = false;
		if ($thisType != $module::$reconciledType) {
            $mismatchedValues = $module->getMismatchedValues($inputMatchedDataDetails);
        }
		
		foreach($matchedDataDetails as $formName => $formDetails) {
			$cleanFormName = str_replace("_"," ",$formName);

			foreach($formDetails as $instanceId => $instanceDetails) {
				$outputRow = [
					"type" => $thisType,
					"form" => $cleanFormName,
					"form_full" => $formName,
					"instance" => $instanceId,
					"record" => $recordId,
					"pid" => $projectId,
					"event" => $eventId,
					"reconciled" => $wasReconciled && !$notReconciled,
					"start-hidden" => ($wasReconciled && $thisType == $module::$inputType),
					"matched-string" => $matchingString,
					"matched-value" => $matchingValue,
					"data" => []
				];
				
                $preMatch = !in_array($matchingValue, $crossMatches) && $thisType == $module::$inputType && !in_array($matchingValue, $existingCrossMatches);
                if($preMatch) {
                    $crossMatch = [
                        "type" => 'pre-match',
                        "form" => 'cross matching',
                        "record" => $recordId,
                        "pid" => $projectId,
                        "event" => $eventId,
                        "matched-string" => $matchingString,
                        "matched-value" => $matchingValue,
                        "data" => []
                    ];
                }
                $fieldKey = 0;
				foreach($outputLabelList as $fieldName => $fieldDetails) {
				    $actualFieldName = $fieldList[$thisType][$fieldKey];
					foreach($fieldDetails as $rawValue => $label) {
						$outputRow["data"][$actualFieldName][$rawValue] = [
							"issue" => ($mismatchedValues && $mismatchedValues[$fieldKey][$rawValue]),
							"value" => $instanceDetails[$fieldKey][$rawValue],
							"unmatched" => array_sum($comparisonData[$matchingValue][$fieldKey][$rawValue]) <= 1
						];

						if ($preMatch) {
						    $crossMatchFieldName = $fieldList[$module::$unreconciledType][$fieldKey];
                            $crossMatch['data'][$crossMatchFieldName][$rawValue] = [
                                "issue" => ($mismatchedValues && $mismatchedValues[$fieldKey][$rawValue]),
                                "value"     => ($mismatchedValues && $mismatchedValues[$fieldKey][$rawValue]) ? "1" : $instanceDetails[$fieldName][$rawValue],
                                "unmatched" => array_sum($comparisonData[$matchingValue][$fieldKey][$rawValue]) <= 1
                            ];
                        }
					}
                    $fieldKey++;
				}
    
				if ($preMatch) {
                    $outputDetails[] = $crossMatch;
                    $crossMatches[] = $matchingValue;
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
	    $actualFieldName = $fieldList[$module::$outputType][$fieldKey];
		foreach($fieldDetails as $rawValue => $label) {
			$unacceptableList[$actualFieldName][$rawValue] = 0;
			if($formDetails[$fieldKey][$rawValue]) {
				$unacceptableList[$actualFieldName][$rawValue] = 1;
			}
		}
		$fieldKey++;
	}
}

$reportUrl = $module->getUrl("report.php");
$logUrl = APP_PATH_WEBROOT_FULL . APP_PATH_WEBROOT . 'Logging/index.php?pid='.$projectId.'&record='.$recordId;
$twigLoader = new Twig_Loader_Filesystem(__DIR__."/templates");
$twig = new Twig_Environment($twigLoader);

$renderVars = [
	"fieldList" => $outputHeaders,
	"fieldDetails" => $outputHeadersCheckboxes,
	"outputData" => $outputDetails,
	"unacceptableList" => $unacceptableList,
	"reportUrl" => $reportUrl,
	"displayName" => $displayString,
	"logUrl" => $logUrl
];

$html = $twig->render("reconciliation.twig",$renderVars);

echo $html;
