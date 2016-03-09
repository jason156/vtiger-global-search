<?php
/* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * *********************************************************************************** */
require_once 'include/events/VTEventHandler.inc';

class Settings_Search_RecordSearchLabelUpdater_Handler extends VTEventHandler {

	function handleEvent($eventName, $data) {
		global $adb;

		if ($eventName == 'vtiger.entity.aftersave') {
            $module = $data->getModuleName();
            if($module != "Users"){

                $labelInfo = self::computeCRMRecordLabels($module, $data->getId(),true);
				if (count($labelInfo) > 0) {
					$label = decode_html($labelInfo[$data->getId()]['name']);
					$search = decode_html($labelInfo[$data->getId()]['search']);
					$adb->pquery('UPDATE vtiger_crmentity INNER JOIN globalsearch_data ON vtiger_crmentity.crmid = globalsearch_data.gscrmid SET label=?,searchlabel=? WHERE crmid=?', array($label, $search, $data->getId()));
				}
            }
		}
	}


	public function computeCRMRecordLabels($module, $ids, $search = false) {

		$log = vglobal('log');
		$log->debug("Entering Settings_Search_Handlers_Model::computeCRMRecordLabels() method ...");

		$adb = PearDatabase::getInstance();

		if (!is_array($ids))
			$ids = array($ids);

		if ($module == 'Events') {
			$module = 'Calendar';
		}

		if ($module) {
			$entityDisplay = array();

			if ($ids) {

				if ($module == 'Groups') {
					$metainfo = array('tablename' => 'vtiger_groups', 'entityidfield' => 'groupid', 'fieldname' => 'groupname');
					/* } else if ($module == 'DocumentFolders') { 
					  $metainfo = array('tablename' => 'vtiger_attachmentsfolder','entityidfield' => 'folderid','fieldname' => 'foldername'); */
				} else {
					$metainfo = Vtiger_Functions::getEntityModuleInfo($module);
				}

					

				$modulename = $metainfo['modulename'];

				$table = $metainfo['tablename'];
				$idcolumn = $metainfo['entityidfield'];
				$columns_name = explode(',', $metainfo['fieldname']);

				$primary = CRMEntity::getInstance($modulename);
				$moduleothertables = $primary->tab_name_index;
				$moduleothertables = array_diff($moduleothertables, array('crmid'));


		foreach ($moduleothertables as $othertable => $otherindex) {
			if (isset($moduleothertables)) {

				$otherquery .= " LEFT JOIN $othertable ON $othertable.$otherindex=$table.$idcolumn";

				} else {

				$otherquery .= '';
			}

		}

				$sqlquery ="SELECT searchcolumn FROM vtiger_entityname LEFT JOIN globalsearch_settings ON vtiger_entityname.tabid = globalsearch_settings.gstabid ";
				$sqlquery .= $otherquery;
				$sqlquery .= " WHERE vtiger_entityname.modulename = '".$modulename."' ";


				$columns_search = $adb->pquery($sqlquery);
				$columns_search = $columns_search->fields;
 
				$columns_search = explode(',', $columns_search['searchcolumn']);

				$columns = array_unique(array_merge($columns_name, $columns_search));

				$moduleothertableslim = $moduleothertables;
				unset($moduleothertableslim[$table], $moduleothertableslim['vtiger_crmentity']);


		foreach ($moduleothertableslim as $othertable => $otherindex) {
			if (isset($moduleothertableslim)) {

				$otherqueryslim .= " LEFT JOIN $othertable ON $othertable.$otherindex=$table.$idcolumn";

				} else {

				$otherqueryslim .= '';
			}

		}

				$full_idcolumn = $table.'.'.$idcolumn;
				$sql = sprintf('SELECT ' . implode(',', array_filter($columns)) . ', %s AS id FROM %s %s WHERE %s IN (%s)', $full_idcolumn, $table, $otherqueryslim, $full_idcolumn, generateQuestionMarks($ids));
				$result = $adb->pquery($sql, $ids);

				$moduleInfo = Vtiger_Functions::getModuleFieldInfos($module);
				$moduleInfoExtend = [];
				if (count($moduleInfo) > 0) {
					foreach ($moduleInfo as $field => $fieldInfo) {
						$moduleInfoExtend[$fieldInfo['columnname']] = $fieldInfo;
					}
				}
				for ($i = 0; $i < $adb->num_rows($result); $i++) {
					$row = $adb->raw_query_result_rowdata($result, $i);
					$label_name = array();
					$label_search = array();
					foreach ($columns_name as $columnName) {
						if ($moduleInfoExtend && in_array($moduleInfoExtend[$columnName]['uitype'], array(10, 51, 75, 81)))
							$label_name[] = Vtiger_Functions::getCRMRecordLabel($row[$columnName]);
						else
							$label_name[] = $row[$columnName];
					}
					if ($search) {
						foreach ($columns_search as $columnName) {
							if ($moduleInfoExtend && in_array($moduleInfoExtend[$columnName]['uitype'], array(10, 51, 75, 81)))
								$label_search[] = Vtiger_Functions::getCRMRecordLabel($row[$columnName]);
							else
								$label_search[] = $row[$columnName];
						}
						$entityDisplay[$row['id']] = array('name' => implode(' ', $label_name), 'search' => implode(' ', $label_search));
					}else {
						$entityDisplay[$row['id']] = implode(' ', $label_name);
					}
				}
			}
			return $entityDisplay;
		}
		$log->debug("Exiting Settings_Search_Handlers_Model::computeCRMRecordLabels() method ...");
	}
}