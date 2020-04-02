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

foreach($records as $thisRecord) {
	$comparisonData = $module->getComparisonData($thisRecord,$module->getProjectId());


}