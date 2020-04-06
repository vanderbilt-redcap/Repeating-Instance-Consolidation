<link rel="stylesheet" href="https://use.typekit.net/ikj0ive.css">
<?php
/**
 * Created by PhpStorm.
 * User: mcguffk
 * Date: 3/24/2020
 * Time: 1:54 PM
 */

$recordId = $_GET['id'];
$projectId = $_GET['pid'];

/** @var \Vanderbilt\RepeatingInstanceConsolidation\RepeatingInstanceConsolidation $module */
$tableData = $module->getComparisonData($recordId,$projectId);

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

echo "<table class='table-bordered wdgmctable' style='    z-index: 0;position: absolute;'><thead><tr><th rowspan='2'>Form/Instance</th>";
$hh_column = 0;
## Output the field label table headers
foreach($fieldList as $fieldName) {
	if($metadata[$fieldName]["field_type"] == "checkbox") {
		echo "<th class='hdrnum m".$hh_column++."' colspan='".(count($outputLabelList[$fieldName])-1)."'>".$metadata[$fieldName]["field_label"]."</th>";
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
			//echo "<tr><td class='fcol'>";

			$frmName =  str_replace("_"," ",$formName);
			$frmName =  str_replace("recipient","",$frmName);
			$frmName =  str_replace("blood","bld",$frmName);
			$frmName =  str_replace("single","sgle",$frmName);
			$frmName =  str_replace("group","grp",$frmName);
			echo "<tr><td class='fcol'><div><a href='".$module->getUrl("reconcile_instance.php?id=$recordId&matchedValue=".urlencode($matchingValue))."'>$frmName : Instance $instanceId - ";

			$matchedData = explode("~",$matchingValue);

			foreach($matchedData as $outputValue) {
				$s = $outputValue;
				$date = strtotime($s);
				echo date('m/d/y', $date);
				
			}

			echo "</a></div></td>";

			$fieldKey = 0;
			$fieldKey2 = 0;
			foreach($outputLabelList as $fieldName => $fieldDetails) {
				foreach($fieldDetails as $rawValue => $label) {
					$style = "";
					if($doComparison && count($comparisonData[$matchingValue][$fieldKey][$rawValue]) > 1) {
						$stylebgred = " bgred";
					} else {
						$stylebgred = "";
					}
					if($c_column == 0){

					}
					$c_column = $fieldKey2++;
					echo "<td class='ca ca_$c_column $stylebgred'>";

					if($instanceDetails[$fieldKey][$rawValue]) {
						echo "<div class='x'></div>";
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

.theader{transform: rotate(-90deg);
    height: 20px;
    display: inherit;
    position: absolute;
    width: 0px;
    /* padding: 4px; */
    margin-top: 33px;}	
	tbody tr:nth-child(even) {background: #0000000a;}
	tbody tr:nth-child(odd) {background: #ffffff52;}

	.todd {background-color: #5555551c;}

.initrow{    position: relative;}
.initrow td.ca:nth-child(even) {background: #0000000a;}
.initrow td.ca:nth-child(odd) {background: unset;}

.initrow td.ca {padding: 16px;}
tbody td.ca:nth-child(odd) {background: #0000000a;}
tbody td.ca:nth-child(even) {background: unset;}
.table-bordered thead td, .table-bordered thead th {border-bottom-width: 0px;height: 98px;}
table *{font-family: proxima-nova, sans-serif;}
.ca{width:40px;text-align: center;}
.x{height: 19px;width: 19px;background-color: #083fbb;margin: auto;    margin-top: 2px; margin-bottom: 2px;}
.table-bordered td, .table-bordered th {border: unset !important;}
.bgred{background-color: #ff00008c !important;}
.bgred .x{ background-color: #000000 !important;}
thead{    border-bottom: 1px solid #00000073;}
thead th{    font-weight: 700;}
.table-bordered {border: 0px solid #dee2e6;}
.fcol{font-size:12px;}
.fcol div{display: inline-block;width: 329px;}
</style>
<script type="text/javascript">

jQuery(document).ready(function($){
	$(".bgred").parent().css( "background-color","rgba(255, 0, 0, 0.18)");

	$(".hdrnum").each(function( index, element ){
		//var p = $(this).last();
		var addtclass = "";
		var addtwidth = "";
		if(index%2 == 0){
			addtclass = " todd";
		}
		if(index == 0){
			addtwidth = 32;
		}
		console.log(addtwidth);
		var offset = $(this).offset();
		var fcolwidth = $(".wdgmctable>thead>tr>th:first-of-type").width();
		$(this).before("<div class='hdrback "+addtclass+"' style='position:absolute; left:" + (offset.left-fcolwidth) + "px; top:0px; height:"+$(".wdgmctable").height()+"px; width:"+($(".m"+index).width()+addtwidth)+"px; z-index: -1;'></div>");
		//$(this).before("<div class='hdrback "+addtclass+"' style='position:absolute; left:" + (offset.left) + "px; top:0px; height:"+$(".wdgmctable").height()+"px; width:"+$(this).width()+"px; z-index: -1;'></div>");
	});
});

</script>




