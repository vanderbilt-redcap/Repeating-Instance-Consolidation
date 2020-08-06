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
$records = REDCap::getData(["project_id" => $module->getProjectId(),"fields" => REDCap::getRecordIdField()]);

$records = array_keys($records);

$questionableRecords = [];
$needsReconciliationRecords = [];
$unreconciledRecords = [];

## TODO What to do with records that have only one entry for a given test date?

foreach($records as $thisRecord) {
	$comparisonData = $module->getComparisonData($module->getProjectId(),$thisRecord)["comparison"];
	$confirmedAntibodies = [];
	$questionableAntibodies = [];

	foreach($comparisonData as $matchingKey => $matchedData) {
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

	if($hasQuestionableAntibodies) {
		$questionableRecords[] = $thisRecord;
	}
	else if($hasUnreconciledIssues) {
		$needsReconciliationRecords[] = $thisRecord;
	}
	else {
		$unreconciledRecords[] = $thisRecord;
	}
}

echo "<h3>Records with questionable antibodies</h3>";
foreach($questionableRecords as $thisRecord) {
	echo "<a href='".$module->getUrl("reconciliation.php?id=".$thisRecord)."'>Record ".$thisRecord."<br />";
}

echo "<br /><br />";
echo "<h3>Records with conflicts</h3>";
foreach($needsReconciliationRecords as $thisRecord) {
	echo "<a href='".$module->getUrl("reconciliation.php?id=".$thisRecord)."'>Record ".$thisRecord."<br />";
}

echo "<br /><br />";
echo "<h3>Records with un-reviewed tests</h3>";
foreach($unreconciledRecords as $thisRecord) {
	echo "<a href='".$module->getUrl("reconciliation.php?id=".$thisRecord)."'>Record ".$thisRecord."<br />";
}