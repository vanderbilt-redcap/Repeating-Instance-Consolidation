<?php
/**
 * Created by PhpStorm.
 * User: mcguffk
 * Date: 3/24/2020
 * Time: 1:54 PM
 */

 $recordId = $_GET['id'];

/** @var \Vanderbilt\RepeatingInstanceConsolidation\RepeatingInstanceConsolidation $module */
$dataMapping = $module->getProjectSetting("existing-json");
$dataMapping = json_decode($dataMapping,true);

$recordData = $module->getData($module->getProjectId(),$recordId);
$metadata = $module->getMetadata($module->getProjectId());

$combinedData = [];
$antibodiesPresent = [];

$fieldList = reset($dataMapping[$module::$inputType])[$module::$dataFields];

$labelList = [];
foreach($fieldList as $fieldName) {
	if($metadata[$fieldName]["field_type"] == "checkbox") {
		$labelList[$fieldName] = $module->getChoiceLabels($fieldName,$module->getProjectId());
	}
}

## Add instanced data to the $combinedData
foreach($recordData[$recordId]["repeat_instances"] as $eventId => $eventDetails) {
	foreach($dataMapping as $dataType => $mappingDetails) {
		foreach($mappingDetails as $formName => $formDetails) {
			foreach($eventDetails[$formName] as $instanceId => $instanceDetails) {
				$matchingValue = [];
				foreach($formDetails[$module::$matchedFields] as $fieldKey => $fieldName) {
					if($instanceDetails[$fieldName] == "") {
						continue 2;
					}
					$matchingValue[] = str_replace("~","",$instanceDetails[$fieldName]);
				}
				$matchingValue = implode("~",$matchingValue);

				foreach($formDetails[$module::$dataFields] as $fieldKey => $fieldName) {
					## Add data to the combined array for display later
					$combinedData[$dataType][$matchingValue][$formName][$instanceId][$fieldKey] = $instanceDetails[$fieldName];

					## Also mark every antibody present, so that non-present antibodies don't have to be displayed
					foreach($instanceDetails[$fieldName] as $rawValue => $checked) {
						if($checked == 1) {
							$antibodiesPresent[$rawValue] = true;
						}
					}
				}
			}
		}
	}
}

## Add non-instanced data to $combinedData
foreach($recordData[$recordId] as $eventId => $eventDetails) {
	foreach($dataMapping[$module::$outputType] as $formName => $formDetails) {
		foreach($formDetails[$module::$dataFields] as $fieldKey => $fieldName) {
			## Add the non-repeating data from the output to the combined data to be displayed
			$combinedData[$module::$outputType][$formName][$fieldKey] = $eventDetails[$fieldName];

			## Also mark raw values from the output form to be displayed
			foreach($eventDetails[$fieldName] as $rawValue => $checked) {
				if($checked == 1) {
					$antibodiesPresent[$rawValue] = true;
				}
			}

		}
	}
}

## Output the data into a table
$outputLabelList = [];

foreach($labelList as $fieldName => $fieldMapping) {
	$outputLabelList[$fieldName] = [];

	foreach($fieldMapping as $rawValue => $label) {
		if($antibodiesPresent[$rawValue]) {
			$outputLabelList[$fieldName][$rawValue] = $label;
		}
	}
}

require_once \ExternalModules\ExternalModules::getProjectHeaderPath();

echo "<table class='table-bordered'><thead><tr><th rowspan='2'>Form/Instance</th>";

## Output the field label table headers
foreach($fieldList as $fieldName) {
	if($metadata[$fieldName]["field_type"] == "checkbox") {
		echo "<th colspan='".count($outputLabelList[$fieldName])."'>".$metadata[$fieldName]["field_label"]."</th>";
	}
	else {
		echo "<th rowspan='2'>".$metadata[$fieldName]["field_label"]."</th>";
	}
}

echo "</tr><tr>";

## Output the antibody field list header row
foreach($fieldList as $fieldName) {
	if($metadata[$fieldName]["field_type"] == "checkbox") {
		foreach($outputLabelList[$fieldName] as $rawValue => $label) {
			echo "<th style='transform:rotate(-90deg);height:90px'>".$label."</th>";
		}
	}
}

echo "</tr></thead>";
echo "<tbody>";

## TODO Need to find a way to sort the matching values
## TODO What to do with the reconciled version of the data
## TODO How to find the discrepancies between same instances


foreach($combinedData[$module::$inputType] as $matchingValue => $matchedDataDetails) {
	foreach($matchedDataDetails as $formName => $formDetails) {
		foreach($formDetails as $instanceId => $instanceDetails) {
			echo "<tr><td>$formName : Instance $instanceId<br />";

			$matchedData = explode("~",$matchingValue);

			foreach($matchedData as $outputValue) {
				echo "$outputValue ";
			}

			echo "</td>";

			foreach($instanceDetails as $fieldName => $fieldData) {
				foreach($fieldData as $rawValue => $checked) {
					if($antibodiesPresent[$rawValue]) {
						echo "<td>";
						if($checked == 1) {
							echo "X";
						}
						echo "</td>";
					}
				}
			}

			echo "</tr>";
		}
	}
}

echo "</tbody></table>";

echo "<pre>";var_dump($combinedData);echo "</pre>";echo "<br />";

