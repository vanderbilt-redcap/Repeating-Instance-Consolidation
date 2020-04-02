<?php
namespace Vanderbilt\RepeatingInstanceConsolidation;

class RepeatingInstanceConsolidation extends \ExternalModules\AbstractExternalModule {
	public static $inputType = "input";
	public static $reconciledType = "reconciled";
	public static $outputType = "output";
	public static $matchedFields = "matching";
	public static $dataFields = "fields";

	public function __construct() {
		parent::__construct();


	}

	public function getComparisonData($recordId, $projectId) {
		$dataMapping = $this->getProjectSetting("existing-json",$projectId);
		$dataMapping = json_decode($dataMapping,true);

		$recordData = $this->getData($projectId,$recordId);

		$fieldList = reset($dataMapping[self::$inputType])[self::$dataFields];

		## Output the data into a table
		$combinedData = [];
		$comparisonData = [];
		$antibodiesPresent = [];

		## Add instanced data to the $combinedData
		foreach($recordData[$recordId]["repeat_instances"] as $eventId => $eventDetails) {
			foreach($dataMapping as $dataType => $mappingDetails) {
				foreach($mappingDetails as $formName => $formDetails) {
					foreach($eventDetails[$formName] as $instanceId => $instanceDetails) {
						$matchingValue = [];
						foreach($formDetails[self::$matchedFields] as $fieldKey => $fieldName) {
							if($instanceDetails[$fieldName] == "") {
								continue 2;
							}
							$matchingValue[] = str_replace("~","",$instanceDetails[$fieldName]);
						}
						$matchingValue = implode("~",$matchingValue);

						foreach($formDetails[self::$dataFields] as $fieldKey => $fieldName) {
							foreach($instanceDetails[$fieldName] as $rawValue => $checked) {
								## Add data to the combined array for display later
								$combinedData[$dataType][$matchingValue][$formName][$instanceId][$fieldKey][$rawValue] = $checked;

								if($dataType == self::$inputType) {
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
			foreach($dataMapping[self::$outputType] as $formName => $formDetails) {
				foreach($formDetails[self::$dataFields] as $fieldKey => $fieldName) {

					## Also mark raw values from the output form to be displayed
					foreach($eventDetails[$fieldName] as $rawValue => $checked) {
						## Add the non-repeating data from the output to the combined data to be displayed
						$combinedData[self::$outputType][$formName][$fieldKey][$rawValue] = $checked;

						if($checked == 1) {
							$antibodiesPresent[$rawValue] = true;
						}
					}

				}
			}
		}

		return ["combined" => $combinedData, "comparison" => $comparisonData, "antibodies" => $antibodiesPresent, "fields" => $fieldList];
	}

	public function refactorDropdownsToJson($newForms,$newTypes,$newFields,$newMatchingFields) {
		$combinedJson = [
			self::$inputType => [],
			self::$reconciledType => [],
			self::$outputType => []
		];

		## Combine the separate fields so they can be compared to the JSON version
		foreach($newForms as $inputKey => $formName) {
			$type = $newTypes[$inputKey];
			$newRow = [];

			if(in_array($type,[self::$inputType,self::$reconciledType])) {
				$newRow = [
					self::$matchedFields => $newMatchingFields[$inputKey],
					self::$dataFields => $newFields[$inputKey]];
			}
			else if($type == self::$outputType) {
				$newRow = [self::$dataFields => $newFields[$inputKey]];
			}
			else {
				continue;
			}

			$combinedJson[$type][$formName] = $newRow;
		}

		$combinedJson = json_encode($combinedJson);

		return $combinedJson;
	}

	public function redcap_module_save_configuration( $project_id ) {
		$oldJson = $this->getProjectSetting('existing-json');
		$newJson = $this->getProjectSetting('input-json');
		$newForms = $this->getProjectSetting('input-forms');
		$newTypes = $this->getProjectSetting('input-types');
		$newFields = $this->getProjectSetting('input-fields');
		$newMatchingFields = $this->getProjectSetting('input-matching-fields');

		$combinedJson = $this->refactorDropdownsToJson($newForms,$newTypes,$newFields,$newMatchingFields);
		$tempJson = json_decode($newJson, true);

		$updateFromCombined = $combinedJson != $oldJson;
		$updateFromJson = $newJson != $oldJson;

		if($_SERVER['SERVER_NAME'] == "localhost") {
			error_log("Instance: $updateFromJson ~ $updateFromCombined ~ $combinedJson");
		}

		if($updateFromJson) {
			if($tempJson !== NULL) {
				## Update the dropdowns from JSON because it's valid JSON
				$updatedForms = [];
				$updatedTypes = [];
				$updatedFields = [];
				$updatedMatchingFields = [];

				foreach($tempJson as $type => $typeDetails) {
					foreach($typeDetails as $formName => $formDetails) {
						if(count($formDetails[self::$dataFields]) == 0) {
							$formDetails[self::$dataFields] = [null];
						}
						if(count($formDetails[self::$matchedFields]) == 0) {
							$formDetails[self::$matchedFields] = [null];
						}

						$updatedForms[] = $formName;
						$updatedTypes[] = $type;
						$updatedFields[] = $formDetails[self::$dataFields];
						$updatedMatchingFields[] = $formDetails[self::$matchedFields];
					}
				}

				$this->setProjectSetting('input-forms',$updatedForms);
				$this->setProjectSetting('input-types',$updatedTypes);
				$this->setProjectSetting('input-fields',$updatedFields);
				$this->setProjectSetting('input-matching-fields',$updatedMatchingFields);
				$this->setProjectSetting('form-settings',array_fill(0,count($updatedForms),"true"));

				## Refactor JSON so it matches the next time it's re-saved
				$newJson = $this->refactorDropdownsToJson($updatedForms,$updatedTypes,$updatedFields,$updatedMatchingFields);

				$this->setProjectSetting("input-json",$newJson);
				$this->setProjectSetting("existing-json",$newJson);
			}
		}
		else if($updateFromCombined) {
			## Update the actual JSON and the input JSON fields from the dropdown values
			$this->setProjectSetting('input-json',$combinedJson);
			$this->setProjectSetting('existing-json',$combinedJson);
		}
	}

	public function redcap_module_link_check_display( $project_id, $link ) {
		if($link['name'] == "Reconcile Data") {
			if(!empty($_GET['id'])) {
				$link['url'] = $link['url']."&id=".$_GET['id'];
			}
			else {
				return false;
			}
		}

		return parent::redcap_module_link_check_display($project_id,$link);
	}
}