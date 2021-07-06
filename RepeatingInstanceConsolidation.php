<?php
namespace Vanderbilt\RepeatingInstanceConsolidation;

class RepeatingInstanceConsolidation extends \ExternalModules\AbstractExternalModule {
	public static $inputType = "input";
	public static $reconciledType = "reconciled";
    public static $unreconciledType = "unreconciled";
	public static $outputType = "output";
	public static $matchedFields = "matching";
	public static $dataFields = "fields";

	public static $dataMapping = [];
	public static $recordData = [];

	public function __construct() {
		parent::__construct();

		include_once(__DIR__."/vendor/autoload.php");
		define("REP_INSTANCE_MODULE_CSS_PATH",$this->getUrl("css/style.css"));
	}

	## Cache the pulling and decoding of the data mapping
	public function getDataMapping($projectId) {
		if(!array_key_exists($projectId,self::$dataMapping)) {
			self::$dataMapping[$projectId] = $this->getProjectSetting("existing-json",$projectId);
			self::$dataMapping[$projectId] = json_decode(self::$dataMapping[$projectId],true);
		}

		return self::$dataMapping[$projectId];
	}

	## Cache the record data that comes back because we'll be making a lot of
	## getData requests for the same record's data within different functions
	public function getData($projectId,$record) {
		if(!array_key_exists($record,self::$recordData[$projectId])) {
			self::$recordData[$projectId][$record] = parent::getData($projectId,$record);
		}
		return self::$recordData[$projectId][$record];
	}

	public function getMismatchedValues($matchingData) {
		$fieldValueChecked = [];
		$mismatchedValues = [];

		foreach($matchingData as $formName => $formDetails) {
			foreach($formDetails as $instanceId => $instanceDetails) {
				foreach($instanceDetails as $fieldKey => $fieldDetails) {
					foreach($fieldDetails as $rawValue => $isChecked) {
						if(!array_key_exists($fieldKey,$fieldValueChecked)) {
							$fieldValueChecked[$fieldKey] = [];
						}

						if(!array_key_exists($rawValue,$fieldValueChecked[$fieldKey])) {
							$fieldValueChecked[$fieldKey][$rawValue] = $isChecked;
						}
						else if($fieldValueChecked[$fieldKey][$rawValue] != $isChecked) {
							$mismatchedValues[$fieldKey][$rawValue] = 1;
						}
					}
				}
			}
		}

		return $mismatchedValues;
	}

	public function getComparisonData($projectId, $recordId, $matchingValues = false) {
		$dataMapping = $this->getDataMapping($projectId);

		$recordData = $this->getData($projectId,$recordId);

        $fieldList = [];
        foreach ($dataMapping as $dataType => $item) {
            $fieldList[$dataType] = reset($item)[self::$dataFields];
        }
		## Manually set order so that reconciliation can be set first for comparison data
		$dataTypeOrder = [self::$reconciledType,self::$inputType];

		## Output the data into a table
		$combinedData = [];
		$comparisonData = [];
		$antibodiesPresent = [];
		$antibodiesConfirmed = [];
		$reconciledTests = [];

		## Add instanced data to the $combinedData
		foreach($recordData[$recordId]["repeat_instances"] as $eventId => $eventDetails) {
			foreach($dataTypeOrder as $dataType) {
				$mappingDetails = $dataMapping[$dataType];
				foreach($mappingDetails as $formName => $formDetails) {
					foreach($eventDetails[$formName] as $instanceId => $instanceDetails) {
						## Pull all the data from matching fields so that instances can be
						## matched between any input forms and also any reconciled data
						$matchingValue = [];
						foreach($formDetails[self::$matchedFields] as $fieldKey => $fieldName) {
							if($instanceDetails[$fieldName] == "") {
								continue 2;
							}
							$matchingValue[] = str_replace("~","",$instanceDetails[$fieldName]);
						}
						$matchingValue = implode("~",$matchingValue);
						$acceptedReconciliation = $dataType == self::$reconciledType && $instanceDetails['cross_matching_complete'] == 2;
                        $unAcceptedReconciliation = $dataType == self::$reconciledType && $instanceDetails['cross_matching_complete'] != 2;
						foreach($formDetails[self::$dataFields] as $fieldKey => $fieldName) {
							foreach($instanceDetails[$fieldName] as $rawValue => $checked) {
                                ## Add data to the combined array for display later if it was accepted as done
                                
								if ($acceptedReconciliation) {
                                    $combinedData[$dataType][$matchingValue][$formName][$instanceId][$fieldKey][$rawValue] = $checked;
                                    unset($combinedData[self::$unreconciledType][$matchingValue][$formName][$instanceId][$fieldKey][$rawValue]);
                                } else if ($unAcceptedReconciliation) {
                                    $combinedData[self::$unreconciledType][$matchingValue][$formName][$instanceId][$fieldKey][$rawValue] = $checked;
                                    unset($combinedData[$dataType][$matchingValue][$formName][$instanceId][$fieldKey][$rawValue]);
                                } else {
                                    $combinedData[$dataType][$matchingValue][$formName][$instanceId][$fieldKey][$rawValue] = $checked;
                                }

								## For raw input data, only add to comparison data if no reconciled data exists
								## All reconciled data should be added however
								if(($dataType == self::$inputType
										&& !array_key_exists($matchingValue,$reconciledTests))
										|| $dataType == self::$reconciledType) {
									## Also add to comparison data, so unmatched data can be found later
									if(!is_array($comparisonData[$matchingValue][$fieldKey][$rawValue])) {
										$comparisonData[$matchingValue][$fieldKey][$rawValue] = [];
									}

									if(array_key_exists($checked,$comparisonData[$matchingValue][$fieldKey][$rawValue])) {
										$comparisonData[$matchingValue][$fieldKey][$rawValue][$checked]++;
									}
									else {
										$comparisonData[$matchingValue][$fieldKey][$rawValue][$checked] = ($dataType == self::$reconciledType ? 2 : 1);
									}

									## Also mark every antibody present, so that non-present antibodies don't have to be displayed
									if($checked == 1) {
										$antibodiesPresent[$rawValue] = true;

										## If test is reconciled, and matching value is included in the list
										## any antibodies count as confirmed
										if($dataType == self::$reconciledType && (!$matchingValues || in_array($matchingValue,$matchingValues))) {
											$antibodiesConfirmed[$rawValue] = true;
										}
									}
								}

								## Track tests that have been reconciled
								if($dataType == self::$reconciledType && $acceptedReconciliation) {
									$reconciledTests[$matchingValue] = 1;
								}
							}

							## Add None to comparison data manually
							$foundChecked = false;
							foreach($combinedData[$dataType][$matchingValue][$formName][$instanceId][$fieldKey] as $rawValue => $checkedValue) {
								if($checkedValue == 1) {
									$foundChecked = true;
									break;
								}
							}

							if(!array_key_exists(0,$comparisonData[$matchingValue][$fieldKey])) {
								$comparisonData[$matchingValue][$fieldKey][0] = [];
							}

							if(!$foundChecked) {
							    if (!empty($combinedData[$dataType][$matchingValue][$formName][$instanceId][$fieldKey])) {
                                    $combinedData[$dataType][$matchingValue][$formName][$instanceId][$fieldKey][0] = 1;
                                }
								if(!array_key_exists(1,$comparisonData[$matchingValue][$fieldKey][0])) {
									$comparisonData[$matchingValue][$fieldKey][0][1] = ($dataType == self::$reconciledType ? 2 : 1);
								}
								else {
									$comparisonData[$matchingValue][$fieldKey][0][1]++;
								}
							}
							else {
                                if (!empty($combinedData[$dataType][$matchingValue][$formName][$instanceId][$fieldKey])) {
                                    $combinedData[$dataType][$matchingValue][$formName][$instanceId][$fieldKey][0] = 0;
                                }
								if(!array_key_exists(0,$comparisonData[$matchingValue][$fieldKey][0])) {
									$comparisonData[$matchingValue][$fieldKey][0][0] = ($dataType == self::$reconciledType ? 2 : 1);
								}
								else {
									$comparisonData[$matchingValue][$fieldKey][0][0]++;
								}
							}
						}
					}
				}
			}
		}

		## Add non-instanced data to $combinedData
		foreach($recordData[$recordId] as $eventId => $eventDetails) {
		    if ($eventId != 'repeat_instances') {
                foreach ($dataMapping[self::$outputType] as $formName => $formDetails) {
                    foreach ($formDetails[self::$dataFields] as $fieldKey => $fieldName) {
                        $foundChecked = false;
                        ## Also mark raw values from the output form to be displayed
                        foreach ($eventDetails[$fieldName] as $rawValue => $checked) {
                            ## Add the non-repeating data from the output to the combined data to be displayed
                            $combinedData[self::$outputType][$formName][$fieldKey][$rawValue] = $checked;
                        
                            if ($checked == 1) {
                                $foundChecked                   = true;
                                $antibodiesPresent[$rawValue]   = true;
                                $antibodiesConfirmed[$rawValue] = true;
                            }
                        }
                    }
                }
            }
		}

		## Parse the comparison data to find new confirmed antibodies
		foreach($antibodiesPresent as $rawValue => $alwaysTrue) {
			## Previously confirmed
			if($antibodiesConfirmed[$rawValue]) continue;

			foreach($comparisonData as $matchingValue => $dateDetails) {
				foreach($dateDetails as $fieldKey => $fieldDetails) {
					## Check that no conflicts for this test and that at least 2 entries show the antibody
					if(count($fieldDetails[$rawValue]) == 1 && $fieldDetails[$rawValue][1] >= 2
							&& (!$matchingValues || in_array($matchingValue,$matchingValues))) {
						$antibodiesConfirmed[$rawValue] = true;
						continue 3;
					}
				}
			}
		}

		return ["combined" => $combinedData, "comparison" => $comparisonData, "antibodies" => $antibodiesPresent, "confirmed" => $antibodiesConfirmed, "fields" => $fieldList];
	}


## TODO Pass through to clarify and remove similar sounding variable names
	## Get the POST data pushed by the reconciliation.php page and save it to the necessary instances
	## Or create new instances if a reconciled instance doesn't exist
	public function saveReconciliationData() {
		$recordId = $_GET['id'];
		$projectId = $_GET['pid'];

		$dataMapping = self::getDataMapping($projectId);
		$eventId = $this->getFirstEventId($projectId);
		$recordData = $this->getData($projectId,$recordId);
		$recordDataRepeating = $recordData[$recordId]["repeat_instances"][$eventId];
		$newRepeatingData = [];
		$newData = [];
		$matchedValues = [];
		$acceptedTests = [];
		$newInstanceForDate = [];

		## $_POST data from reconciliation form is passed in matchValue|rawValue => [postedValues] form
		foreach($_POST as $postField => $postValue) {
			if(substr($postField,0,7) == "accept_") {
				## Create reconciled instances for accepted matching tests
				$matchingValue = $postValue;

				$acceptedTests[$matchingValue] = 1;

				$matchedInstances = $this->findMatchingInstances($projectId,$recordId,$matchingValue,self::$reconciledType);
				foreach($dataMapping[self::$reconciledType] as $formName => $formDetails) {
					## Only add all data if this reconciled instance doesn't exist yet
					if(!array_key_exists($formName,$matchedInstances)) {
                        if (array_key_exists($matchingValue, $newInstanceForDate)) {
                            $thisInstance = $newInstanceForDate[$matchingValue];
                        } else if (empty($newInstanceForDate)){
                            $thisInstance = $this->findNextInstance($projectId,$recordId,$formName);
						} else {
                            $thisInstance = max($newInstanceForDate)+1;
                        }
						$matchingValues = explode("~",$matchingValue);

						## Add the matched instance data
                        if (empty($newRepeatingData[$formName][$thisInstance])) {
                            $newRepeatingData[$formName][$thisInstance] = $this->findMatchedInputData($projectId, $recordId, $matchingValue, true);
                        }

						## Add the matching data fields too
						foreach($formDetails[self::$matchedFields] as $fieldKey => $thisField) {
							$newRepeatingData[$formName][$thisInstance][$thisField] = $matchingValues[$fieldKey];
						}
                        $newRepeatingData[$formName][$thisInstance]['cross_matching_complete'] = 2;
                        $newInstanceForDate[$matchingValue] = $thisInstance;
					} else { ##Otherwise just set cross_matching_complete to 2
                        $matchedInstances = $this->findMatchingInstances($projectId,$recordId,$matchingValue,self::$reconciledType);
                        $thisInstance = $matchedInstances[$formName][0];
                        $newRepeatingData[$formName][$thisInstance]['cross_matching_complete'] = 2;
                    }
				}
			}
			else if(strpos($postField,"|") !== false) {
				$nonBlankValue = false;
				foreach($postValue as $thisValue) {
					if($thisValue !== "") {
						$nonBlankValue = true;
						break;
					}
				}

				## Only try to map data that has actually been reconciled by clicking a value
				if($nonBlankValue === false) continue;

                list($matchingValue,$fieldName,$rawValue) = explode("|",$postField);
				$matchingValues = explode("~",$matchingValue);
                
                ## Just check the first $postValue, they should all match anyways
                $postValue = reset($postValue);
                
                if(!array_key_exists($matchingValue,$matchedValues)) {
					$matchedInstances = $this->findMatchingInstances($projectId,$recordId,$matchingValue,self::$reconciledType);

					foreach($dataMapping[self::$reconciledType] as $formName => $formDetails) {
						## If instances already exist for this matching value on this form, copy in existing data
						if(array_key_exists($formName,$matchedInstances)) {
							$matchedValues[$matchingValue][$formName] = $matchedInstances[$formName];

							foreach($matchedInstances[$formName] as $thisInstance) {
								$newRepeatingData[$formName][$thisInstance] = $recordDataRepeating[$formName][$thisInstance];
								//Add test to accepted list if the instance already exists and is complete (it'll get added in the accept section of this loop if it's newly accepted)
								if ($newRepeatingData[$formName][$thisInstance]['cross_matching_complete'] == 2) {
								    $acceptedTests[$matchingValue] = 1;
                                }
							}
						}
						## If instances don't exist, find a new instance and copy the matching data, while ignoring the unconfirmed data
						else {
                            if (array_key_exists($matchingValue, $newInstanceForDate)) {
                                $thisInstance = $newInstanceForDate[$matchingValue];
                            } else if (empty($newInstanceForDate)){
                                $thisInstance = $this->findNextInstance($projectId,$recordId,$formName);
                            } else {
                                $thisInstance = max($newInstanceForDate)+1;
                            }
                            
							$matchedValues[$matchingValue][$formName] = [$thisInstance];

							## Add the matched instance data
							$newRepeatingData[$formName][$matchedValues[$matchingValue][$formName][0]] = $this->findMatchedInputData($projectId,$recordId,$matchingValue,true);

							## Add the matching data fields too
							foreach($formDetails[self::$matchedFields] as $fieldKey => $thisField) {
								$newRepeatingData[$formName][$matchedValues[$matchingValue][$formName][0]][$thisField] = $matchingValues[$fieldKey];
							}
                            $newInstanceForDate[$matchingValue] = $thisInstance;
						}
					}
				}
				## Foreach form in reconciled type, find the field that has the given raw value
				## Then update the $newRecordData with the $postValue
				foreach($dataMapping[self::$reconciledType] as $formName => $formDetails) {
					foreach($formDetails[self::$dataFields] as $thisField) {
						$enum = $this->getChoiceLabels($thisField,$projectId);
						if(array_key_exists($rawValue,$enum)) {
							foreach($matchedValues[$matchingValue][$formName] as $thisInstance) {
                                if ($thisField == $fieldName) {
                                    $newRepeatingData[$formName][$thisInstance][$thisField][$rawValue] = $postValue;
                                }
							}
						}
					}
				}
			}
			else if(substr($postField,0,13) == "unacceptable-") {
				list($matchingValue,$fieldName,$rawValue) = explode("-",$postField);
				$formsToCheck = $this->getProjectSetting("input-forms",$projectId);
				$formTypes = $this->getProjectSetting("input-types",$projectId);
				$fieldList = $this->getProjectSetting("input-fields",$projectId);
				foreach($formsToCheck as $thisKey => $thisForm) {
					if($formTypes[$thisKey] == self::$outputType) {
						foreach ($fieldList[$thisKey] as $field) {
						    if ($field == $fieldName) {
                                $options = $this->getChoiceLabels($field, $projectId);
                                if (array_key_exists($rawValue, $options)) {
                                    $newData[$field][$rawValue] = $postValue;
                                }
                            }
						}
					}
				}
			}
		}
        //check if any values other than '0' exist in old+new data for non-repeating data
        foreach ($newData as $fieldName=>$newValues) {
            $setNone = false;
            $existingValues = $recordData[$recordId][$eventId][$fieldName];
            if (array_key_exists(0, $newValues)) {
                $setNone = true;
            }
            unset($newValues[0]);
            unset($existingValues[0]);
            $existingValues = array_filter($existingValues); //Remove all existing values that are set to 0 for comparison
            if (array_search('1', $newValues) !== false) { //If any values are changing to 1 then don't set none to 1
                $newData[$fieldName][0] = '0';
            } else if (!empty($newValues) && !empty($existingValues)) {
                if ($setNone) {
                    foreach ($existingValues as $key=>$value) {
                        //check if every existing value in this section is being overwritten with a 0
                        if (!array_key_exists($key, $newValues) || $newValues[$key] == '1') {
                            $setNone = false;
                        }
                    }
                }
                if (!$setNone) {
                    $newData[$fieldName][0] = '0';
                }
            } else if (array_search('1', $existingValues) !== false) {
                $newData[$fieldName][0] = '0';
            }//TODO determine if 'none' should ever be set to '1' automatically, if so, do it here in an else            
		}
        foreach ($dataMapping[self::$reconciledType] as $formName => $mappings) {
            foreach ($newRepeatingData[$formName] as $instance => $fields) {
                foreach ($mappings[self::$dataFields] as $field) {
                    if ($fields[$field]['0'] == 1) {
                        unset($fields[$field]['0']);
                        //If any value is found in this field other than 'none', then set 'none' to 0
                        if (array_search(1, $fields[$field]) !== false) {
                            $newRepeatingData[$formName][$instance][$field]['0'] = 0;
                        }
                    }
                }
            }            
        }
        
		if(count($newRepeatingData) > 0) {
			$results = \REDCap::saveData($projectId,"array", [$recordId => ["repeat_instances" => [$eventId => $newRepeatingData]]]);

			if(count($results["errors"]) > 0) {
				echo "<pre>";var_dump($results);echo "</pre>";echo "<br />";
			}
			## Unset cached data so unacceptable list updates correctly
			unset(self::$recordData[$projectId][$recordId]);

			$this->updateUnacceptableAntigenList($projectId,$recordId,$eventId,array_keys($acceptedTests));

			## Remove record caches so that the new table is up to date after these changes
			unset(self::$recordData[$projectId][$recordId]);
		}
		//update the non repeating data after updating antigens
		if(count($newData) > 0) {
			$results = \REDCap::saveData($projectId,"array", [$recordId => [$eventId => $newData]]);

			if(count($results["errors"]) > 0) {
				echo "<pre>";var_dump($results);echo "</pre>";echo "<br />";
			}
			## Unset cached data so unacceptable list updates correctly
			unset(self::$recordData[$projectId][$recordId]);
		}
	}

	## Find the forms and instance IDs of matching instances for all forms of the specified type
	public function findMatchingInstances($projectId, $recordId, $matchingValue, $formType) {
		$matchingValues = explode("~",$matchingValue);
		$dataMapping = $this->getDataMapping($projectId);
		$existingRecordData = $this->getData($projectId,$recordId);
		$matchingInstances = [];

		## For each form configured for this type, look for matching instances
		foreach($dataMapping[$formType] as $thisForm => $formDetails) {
			foreach($existingRecordData[$recordId]["repeat_instances"] as $eventId => $eventDetails) {
				foreach($eventDetails[$thisForm] as $instanceId => $instanceDetails) {
					$recordMatches = true;

					## Check if an instance exists for the matching values
					foreach($formDetails[self::$matchedFields] as $fieldKey => $matchedField) {
						$cleanedValue = str_replace("~","",$instanceDetails[$matchedField]);
						if($cleanedValue != $matchingValues[$fieldKey]) {
							$recordMatches = false;
							break;
						}
					}

					## Add to the list of matches
					if($recordMatches) {
						if(!array_key_exists($thisForm,$matchingInstances)) {
							$matchingInstances[$thisForm] = [];
						}
						$matchingInstances[$thisForm][] = $instanceId;
					}
				}
			}
		}

		return $matchingInstances;
	}

	## Find the first event with instances for this form and return the max instance ID plus 1
	public function findNextInstance($projectId, $recordId, $formName) {
		$existingRecordData = $this->getData($projectId,$recordId);
		$maxInstance = 0;
		foreach($existingRecordData[$recordId]["repeat_instances"] as $eventId => $eventDetails) {
			if(count($eventDetails[$formName]) > 0) {
				$maxInstance = max(array_keys($eventDetails[$formName]));
				break;
			}
		}

		return ($maxInstance + 1);
	}

	## Look through the input data that matches the matchingValue and find all the checkboxes
	## that are checked on any input form (or only those that match
	public function findMatchedInputData($projectId,$recordId,$matchingValue,$confirmedOnly = false) {
		$matchedData = [];

		$dataMapping = $this->getDataMapping($projectId);
		$comparisonData = $this->getComparisonData($projectId,$recordId);

		foreach($dataMapping[self::$reconciledType] as $thisForm => $formDetails) {
			## Go through the comparison data for this matching value and create a raw value for each
			## checkbox checked on at least one input form
			foreach($comparisonData["comparison"][$matchingValue] as $fieldKey => $fieldDetails) {
				$fieldData = [];
				foreach($fieldDetails as $rawValue => $rawData) {
					## If only looking for confirmed data, skip unconfirmed values
					if($confirmedOnly && count($rawData) > 1) continue;

					if(array_key_exists(1,$rawData)) {
						$fieldData[$rawValue] = 1;
					}
					else {
						$fieldData[$rawValue] = 0;
					}
				}
				$matchedData[$formDetails[self::$dataFields][$fieldKey]] = $fieldData;
			}
		}

		return $matchedData;
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
	
	public function getMatchingValue($project_id, $record, $instrument, $repeatInstance) {
		$formsToCheck = $this->getProjectSetting("input-forms",$project_id);
		$fieldList = $this->getProjectSetting("input-matching-fields",$project_id);
		
		$outputFields = [];
		foreach($formsToCheck as $thisKey => $thisForm) {
			if($thisForm == $instrument) {
				$outputFields = $fieldList[$thisKey];
			}
		}

		if(count($outputFields) == 0) {
			return false;
		}
		
		$recordData = \REDCap::getData([
			"project_id" => $project_id,
			"records" => $record,
			"fields" => $outputFields,
			"return_format" => "json"
		]);

		$recordData = json_decode($recordData,true);

		if($repeatInstance == "") {
			$repeatInstance = 1;
		}

		$matchingValues = [];
		$instance = 1;
		foreach($recordData as $instanceData) {
			if($instance == $repeatInstance) {
				foreach($outputFields as $thisField) {
					$matchingValues[] = $instanceData[$thisField];
				}
			}
			$instance++;
		}

		return implode("~",$matchingValues);
	}

	public function redcap_save_record( $project_id, $record, $instrument, $event_id, $group_id, $survey_hash = NULL, $response_id = NULL, $repeat_instance = 1 ) {
        //This shouldn't happen anymore, but leaving commented out for now
//		$matchingValue = $this->getMatchingValue($project_id,$record,$instrument,$repeat_instance);
//
//		if($matchingValue) {
//			$this->updateUnacceptableAntigenList($project_id,$record,$event_id,[$matchingValue]);
//		}
	}

	public function updateUnacceptableAntigenList($project_id,$record,$event_id,$matchingValues) {
		## Don't run hook without specific matching values to check
		if(!$matchingValues) {
			return;
		}

		$formsToCheck = $this->getProjectSetting("input-forms",$project_id);
		$formTypes = $this->getProjectSetting("input-types",$project_id);
		$fieldList = $this->getProjectSetting("input-fields",$project_id);

		$outputFields = [];
		foreach($formsToCheck as $thisKey => $thisForm) {
			if($formTypes[$thisKey] == self::$outputType) {
				$outputFields = $fieldList[$thisKey];
			}
		}

		$combinedData = $this->getComparisonData($project_id,$record,$matchingValues);
		$newData = [];

		## Comparison data function already incorporates confirmed tests
		foreach($combinedData["confirmed"] as $rawValue => $checked) {
			## Don't worry about "None" or "Pending" as they get set/unset later
			if($checked && $rawValue != "0" && $rawValue != "P") {
				foreach($outputFields as $thisField) {
					## Find the field that has this rawValue and save
					$checkValues = $this->getChoiceLabels($thisField,$project_id);

					if(array_key_exists($rawValue,$checkValues)) {
						$newData[$thisField][$rawValue] = 1;
					}
				}
			}
		}

		## Run through all fields again and set/unset "None" as needed
		foreach($outputFields as $thisField) {
			if(array_key_exists($thisField,$newData) && count($newData[$thisField]) > 0) {
				## Uncheck "None" if not needed
				$newData[$thisField]["0"] = 0;
			}
			else {
				## Check "None" if no data being added to this field
				$newData[$thisField]["0"] = 1;
			}
		}

		## Save the unacceptable antigen list any time a new matched value is found
		$this->saveData($project_id,$record,$event_id,$newData);
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
		$restrictedUsers = $this->getProjectSetting("users-to-access");

		if(count($restrictedUsers) > 0 && reset($restrictedUsers) != "") {
			if(!in_array(USERID,$restrictedUsers)) {
				return false;
			}
		}

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