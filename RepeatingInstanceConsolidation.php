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