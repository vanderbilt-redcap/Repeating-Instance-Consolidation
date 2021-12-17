<?php
/**
 * Created by PhpStorm.
 * User: mcguffk
 * Date: 4/2/2020
 * Time: 12:58 PM
 */

/** @var \Vanderbilt\RepeatingInstanceConsolidation\RepeatingInstanceConsolidation $module */

require_once \ExternalModules\ExternalModules::getProjectHeaderPath();

$startTime = microtime(true);
$recordData = REDCap::getData(["project_id" => $module->getProjectId(),"fields" => [REDCap::getRecordIdField(),"rec_name","rec_dob","rec_id", "rec_listing_status"]]);

$records = array_keys($recordData);

$questionableRecords = [];
$needsReconciliationRecords = [];
$unreconciledRecords = [];
$noDataRecords = [];
$reviewedRecords = [];
$unmatchedRecords = [];
$recordDisplay = [];

## TODO What to do with records that have only one entry for a given test date?
foreach($records as $thisRecordId) {
    $thisRecord = reset($recordData[$thisRecordId]);
    $recordDisplay[$thisRecordId] = $thisRecord["rec_id"].": ".$thisRecord["rec_name"]." - ".
        $thisRecord["rec_dob"];
    if ($thisRecord['rec_listing_status'] == 7) {
        continue;
    }
    
    $comparisonData = $module->getComparisonData($module->getProjectId(),$thisRecordId);
	$confirmedAntibodies = [];
	$questionableAntibodies = [];

	if(count($comparisonData["comparison"]) == 0) {
		$noDataRecords[] = $thisRecordId;
		continue;
	}

	foreach($comparisonData["comparison"] as $matchingKey => $matchedData) {
		foreach($matchedData as $fieldKey => $fieldData) {
			foreach($fieldData as $rawValue => $checkedList) {
				if(count($checkedList) > 1) {
					$questionableAntibodies[$fieldKey][$rawValue] = 1;
				}
				else if(array_key_exists(1,$checkedList) && ($checkedList[1] > 1)) {
					$confirmedAntibodies[$fieldKey][$rawValue] = 1;
				}
			}
		}
	}

	$hasQuestionableAntibodies = false;
	$hasUnreconciledIssues = (count($questionableAntibodies) > 0);
	foreach($questionableAntibodies as $fieldKey => $fieldValues) {
		foreach($fieldValues as $rawValue => $confirmed) {
			if(!array_key_exists($rawValue,$confirmedAntibodies[$fieldKey])) {
				$hasQuestionableAntibodies = true;
				break 2;
			}
		}
	}

	$hasUnreconciledTests = false;
	foreach($comparisonData["combined"]["input"] as $matchingValue => $matchedData) {
		if(!array_key_exists($matchingValue,$comparisonData["combined"]["reconciled"])) {
			$hasUnreconciledTests = true;
			break;
		}
	}

	foreach($comparisonData["combined"]["input"] as $matchingValue => $matchedData) {
		if(count($matchedData) <= 1 && count(reset($matchedData)) <= 1) {
			$unmatchedRecords[] = $thisRecordId;
			break;
		}
	}

	if($hasQuestionableAntibodies) {
		$questionableRecords[] = $thisRecordId;
	}
	else if($hasUnreconciledIssues) {
		$needsReconciliationRecords[] = $thisRecordId;
	}
	else if($hasUnreconciledTests){
		$unreconciledRecords[] = $thisRecordId;
	}
	else {
		$reviewedRecords[] = $thisRecordId;
	}
}

echo "<h3>Records with questionable antibodies</h3>";
foreach($questionableRecords as $thisRecordId) {
	echo "<a href='".$module->getUrl("reconciliation.php?id=".$thisRecordId)."'>".$recordDisplay[$thisRecordId]."</a><br />";
}

echo "<br /><br />";
echo "<h3>Records with conflicts</h3>";
foreach($needsReconciliationRecords as $thisRecordId) {
	echo "<a href='".$module->getUrl("reconciliation.php?id=".$thisRecordId)."'>".$recordDisplay[$thisRecordId]."</a><br />";
}

echo "<br /><br />";
echo "<h3>Records with un-reviewed tests</h3>";
foreach($unreconciledRecords as $thisRecordId) {
	echo "<a href='".$module->getUrl("reconciliation.php?id=".$thisRecordId)."'>".$recordDisplay[$thisRecordId]."</a><br />";
}

echo "<br /><br />";
echo "<h3>Records with un-matching test dates</h3>";
foreach($unmatchedRecords as $thisRecordId) {
	echo "<a href='".$module->getUrl("reconciliation.php?id=".$thisRecordId)."'>".$recordDisplay[$thisRecordId]."</a><br />";
}

echo "<br /><br />";
echo "<h3>Records with no data</h3>";
foreach($noDataRecords as $thisRecordId) {
	echo "<a href='".$module->getUrl("reconciliation.php?id=".$thisRecordId)."'>".$recordDisplay[$thisRecordId]."</a><br />";
}

echo "<br /><br />";
echo "<h3>Fully Reviewed Records</h3>";
foreach($reviewedRecords as $thisRecordId) {
	echo "<a href='".$module->getUrl("reconciliation.php?id=".$thisRecordId)."'>".$recordDisplay[$thisRecordId]."</a><br />";
}