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
$comparisonData = [];
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
					foreach($instanceDetails[$fieldName] as $rawValue => $checked) {
						## Add data to the combined array for display later
						$combinedData[$dataType][$matchingValue][$formName][$instanceId][$fieldKey][$rawValue] = $checked;

						if($dataType == $module::$inputType) {
							## Also add to comparison data, so unmatched data can be found later
							$comparisonData[$matchingValue][$fieldKey][$rawValue][$checked] = 1;
						}

						## Also mark every antibody present, so that non-present antibodies don't have to be displayed
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

			## Also mark raw values from the output form to be displayed
			foreach($eventDetails[$fieldName] as $rawValue => $checked) {
				## Add the non-repeating data from the output to the combined data to be displayed
				$combinedData[$module::$outputType][$formName][$fieldKey][$rawValue] = $checked;

				if($checked == 1) {
					$antibodiesPresent[$rawValue] = true;
				}
			}

		}
	}
}

## Output the data into a table
$outputLabelList = [];

## Foreach each label value, check if antibody was ever present and only output if it was
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

echo "</tr><tr class='initrow'>";

## Output the antibody field list header row
$c_antibody = 0;
foreach($fieldList as $fieldName) {
	if($metadata[$fieldName]["field_type"] == "checkbox") {
		foreach($outputLabelList[$fieldName] as $rawValue => $label) {
			echo "<td class='ca ca_".$c_antibody++."'><div class='theader' style=''>".$label."</div></td>";
		}
	}
}

echo "</tr></thead>";
echo "<tbody>";

$matchedKeys = array_keys($combinedData[$module::$inputType]);

sort($matchedKeys);

foreach($matchedKeys as $matchingValue) {
	$doComparison = false;
	$matchedDataDetails = $combinedData[$module::$reconciledType][$matchingValue];

	if(count($matchedDataDetails) == 0) {
		$doComparison = true;
		$matchedDataDetails = $combinedData[$module::$inputType][$matchingValue];
	}
$c_column = 0;
	foreach($matchedDataDetails as $formName => $formDetails) {
		
		foreach($formDetails as $instanceId => $instanceDetails) {
			echo "<tr><td>$formName : Instance $instanceId<br />";

			$matchedData = explode("~",$matchingValue);

			foreach($matchedData as $outputValue) {
				echo "$outputValue ";
			}

			echo "</td>";

			$fieldKey = 0;
			$fieldKey2 = 0;
			foreach($outputLabelList as $fieldName => $fieldDetails) {
				foreach($fieldDetails as $rawValue => $label) {
					$style = "";
					if($doComparison && count($comparisonData[$matchingValue][$fieldKey][$rawValue]) > 1) {
						$style = "style='background-color:red'";
					}
					if($c_column == 0){

					}
					$c_column = $fieldKey2++;
					echo "<td class='ca ca_$c_column' $style>";

					if($instanceDetails[$fieldKey][$rawValue]) {
						echo "X";
					}

					echo "</td>";
				}
				$fieldKey++;
			}

			echo "</tr>";
		}
	}
}

foreach($combinedData[$module::$outputType] as $formName => $formDetails) {
	echo "<tr><td>$formName</td>";

	$fieldKey = 0;
	foreach($outputLabelList as $fieldName => $fieldDetails) {
		foreach($fieldDetails as $rawValue => $label) {
			echo "<td>";

			if($formDetails[$fieldKey][$rawValue]) {
				echo "X";
			}
			echo "</td>";
		}
		$fieldKey++;
	}

	echo "</tr>";
}

echo "</tbody></table>";

?>
<style>

.theader{transform:rotate(-90deg);height:22px}	
tbody tr:nth-child(even) {background: #0000000a;}
tbody tr:nth-child(odd) {background: #FFF}

.initrow td.ca:nth-child(even) {background: #0000000a;}
.initrow td.ca:nth-child(odd) {background: unset;}

tbody td.ca:nth-child(odd) {background: #0000000a;}
tbody td.ca:nth-child(even) {background: unset;}
.table-bordered thead td, .table-bordered thead th {
    border-bottom-width: 0px;
    height: 98px;
}
</style>
<script type="text/javascript">

jQuery(document).ready(function($){

});

</script>
