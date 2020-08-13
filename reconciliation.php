<link rel="stylesheet" href="https://use.typekit.net/ikj0ive.css">
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

## TODO Add the non-reconciled data, but hide it by default, so reconciliation can be done even if a record is created

## Foreach each label value, check if antibody was ever present and only output if it was
foreach($labelList as $fieldName => $fieldMapping) {
	$outputLabelList[$fieldName] = [];

	foreach($fieldMapping as $rawValue => $label) {
		if($antibodiesPresent[$rawValue]) {
			$outputLabelList[$fieldName][$rawValue] = $label;
		}
	}
}

//require_once \ExternalModules\ExternalModules::getProjectHeaderPath();
?>
<link rel="stylesheet" type="text/css" href="<?=APP_PATH_WEBPACK?>css/bundle.css">
<link rel="stylesheet" type="text/css" href="<?=APP_PATH_CSS?>style.css">

<script src="<?=APP_PATH_WEBPACK?>js/bundle.js"></script>
<script src="<?=APP_PATH_JS?>base.js"></script>

<div class="container-fluid mainwindow">
	<div class="row">
		<div class="col-12">
<?php
echo "<form method='POST'>";
echo "<table class='table-bordered wdgmctable' style='    z-index: 0;position: absolute;'><thead><tr><th rowspan='2'>Form/Instance</th>";
$hh_column = 0;
## Output the field label table headers
foreach($fieldList as $fieldName) {
	if($metadata[$fieldName]["field_type"] == "checkbox") {
		echo "<th class='hdrnum m".$hh_column++."' colspan='".count($outputLabelList[$fieldName])."'>".$metadata[$fieldName]["field_label"]."</th>";
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

## TODO Add link to "Manually reconcile" (send to instance for test date with no data)
## TODO Add button to save current unacceptable antigen list

$matchedKeys = array_merge(array_keys($combinedData[$module::$inputType]),(array_key_exists($module::$reconciledType,$combinedData) ? array_keys($combinedData[$module::$reconciledType]) : []));

sort($matchedKeys);

foreach($matchedKeys as $matchingValue) {
	$doComparison = false;
	$matchedDataDetails = $combinedData[$module::$reconciledType][$matchingValue];

	if(count($matchedDataDetails) == 0) {
		$doComparison = true;
		$matchedDataDetails = $combinedData[$module::$inputType][$matchingValue];
	}
	$c_column = 0;
	$gettr = 0;
	$gettra = 0;
	foreach($matchedDataDetails as $formName => $formDetails) {

		foreach($formDetails as $instanceId => $instanceDetails) {
			//echo "<tr><td class='fcol'>";

			$frmName =  str_replace("_"," ",$formName);
			$frmName =  str_replace("recipient","",$frmName);
			$frmName =  str_replace("blood","bld",$frmName);
			$frmName =  str_replace("single","sgle",$frmName);
			$frmName =  str_replace("group","grp",$frmName);

			$matchedData = explode("~",$matchingValue);
			$matchingString = "$frmName : Instance $instanceId - ";

			foreach($matchedData as $outputValue) {
				$s = $outputValue;
				$date = strtotime($s);
				$matchingString .= date('m/d/y', $date);

			}
			//$gettra = $gettr++;
			echo "<tr class='tr_".str_replace(' ','',$frmName.$instanceId)."'><td class='fcol tfirst-column'><div >";

			if($doComparison) {
				echo "<a href='".APP_PATH_WEBROOT."DataEntry/?pid=".$projectId."&id=".$recordId."&page=".$frmName."&instance=".$instanceId."'>";
				echo $matchingString;
				echo "</a>";
			}
			else {
				echo "<b>".$matchingString."</b>";
			}
			echo "</div></td>";

			$fieldKey = 0;
			$fieldKey2 = 0;
			foreach($outputLabelList as $fieldName => $fieldDetails) {
				foreach($fieldDetails as $rawValue => $label) {
					$style = "";
					if($doComparison && count($comparisonData[$matchingValue][$fieldKey][$rawValue]) > 1) {
						$stylebgred = " bgred";
					} else if(array_sum($comparisonData[$matchingValue][$fieldKey][$rawValue]) <= 1) {
						$stylebgred = " bggreen";
					}
					else {
						$stylebgred = "";
					}
					if($c_column == 0){

					}
					$c_column = $fieldKey2++;
					echo "<td class='ca ca_$c_column $stylebgred'>";
					$doShowbox = "";
					if($stylebgred == ' bgred'){
						$doShowbox = " onclick='toggleAntigen($(this),\".match_$matchingValue\",\".ca_$c_column\");'";
					}

					$startClass = "o";
					if($instanceDetails[$fieldKey][$rawValue]) {
						$startClass = "x";
					}
					echo "<div class='$startClass match_$matchingValue' ".$doShowbox.">
						<input type='hidden' name='$matchingValue|".$rawValue."[]' value='' />
					</div>";

					echo "</td>";
				}
				$fieldKey++;
			}

			echo "<td>";
			if($doComparison) {

//				echo "<a href='".$module->getUrl("reconcile_instance.php?id=$recordId&matchedValue=".urlencode($matchingValue))."'>";
//				echo $matchingString;
//				echo "</a>";
			}
			echo "</td>";

			echo "</tr>";
		}
	}
}
foreach($combinedData[$module::$outputType] as $formName => $formDetails) {
	echo "<tr><td class='tfirst-column'>$formName</td>";

	$fieldKey = 0;
	foreach($outputLabelList as $fieldName => $fieldDetails) {
		foreach($fieldDetails as $rawValue => $label) {
			echo "<td style='text-align:center'>";

			if($formDetails[$fieldKey][$rawValue]) {
				echo "X";
			}
			echo "</td>";
		}
		$fieldKey++;
	}

	echo "</tr>";
}

echo "<tr><td><div style='padding-top:10px'><input type='submit' value='Save Reconciliation' /></div></td></tr>";
echo "</tbody></table>";
echo "</form>";
echo "* <b>bold</b> indicates already reconciled data";

?>
</div>
</div>
</div>
<style>
.tfirst-column {
	display: table-cell;
	vertical-align: inherit;
	z-index: 2;
}
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
.ca{
	width:40px;
	margin: auto;
}
.x{height: 19px;width: 19px;background-color: #083fbb;margin: auto;    margin-top: 2px; margin-bottom: 2px;}
.o{height: 19px;width: 19px;margin: auto;    margin-top: 2px; margin-bottom: 2px;}
.table-bordered td, .table-bordered th {border: unset !important;}
.bgred{background-color: #ff00008c !important;}
.bgred .x{ background-color: #000000 !important;}
.bgred .x:hover{cursor:hand;}
.bggreen{background-color: #00ff008c !important;}
thead{    border-bottom: 1px solid #00000073;}
thead th{    font-weight: 700;}
.table-bordered {border: 0px solid #dee2e6;}
.fcol{font-size:12px;}
.fcol div{display: inline-block;width: 329px;}
</style>
<script type="text/javascript">

jQuery(document).ready(function($){

	$(".bgred").parent().css( "background-color","rgba(255, 0, 0, 0.18)");
	$(".bggreen").parent().css( "background-color","rgba(0, 255, 0, 0.18)");

	$(".hdrnum").each(function( index, element ){
		if(index%2 == 0){
			$(this).addClass("todd");
		}
		$(this).addClass("hdrback");
		$(this).css("padding-left","10px");
		$(this).css("padding-right","10px");
		//$(this).before("<div class='hdrback "+addtclass+"' style='position:absolute; left:" + (offset.left) + "px; top:0px; height:"+$(".wdgmctable").height()+"px; width:"+$(this).width()+"px; z-index: -1;'></div>");
	});
});

	function toggleAntigen(clickedCell,matchingClass,columnClass) {
		var curClass = false;
		if(clickedCell.hasClass("x")) {
			curClass = true;
		}

		$(columnClass).each(function() {
			var matchedCell = $(this).find(matchingClass);
			if(matchedCell.length > 0) {
				if(curClass) {
					matchedCell.removeClass("x");
					matchedCell.addClass("o");
					matchedCell.find("input").val("0");
				}
				else {
					matchedCell.removeClass("o");
					matchedCell.addClass("x");
					matchedCell.find("input").val("1");
				}
			}
		});
	}

</script>