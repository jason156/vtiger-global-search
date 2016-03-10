<?php
/* +***********************************************************************************************************************************
 * The contents of this file are subject to the YetiForce Public License Version 1.1 (the "License"); you may not use this file except
 * in compliance with the License.
 * Software distributed under the License is distributed on an "AS IS" basis, WITHOUT WARRANTY OF ANY KIND, either express or implied.
 * See the License for the specific language governing rights and limitations under the License.
 * The Original Code is YetiForce.
 * The Initial Developer of the Original Code is YetiForce. Portions created by YetiForce are Copyright (C) www.yetiforce.com. 
 * All Rights Reserved.
 * *********************************************************************************************************************************** */

class Settings_Search_Module_Model extends Settings_Vtiger_Module_Model
{

	public function getModulesEntity($tabid = false)
	{
		$log = vglobal('log');
		$log->debug("Entering Settings_Search_Module_Model::getModulesEntity() method ...");
		$adb = PearDatabase::getInstance();
		$adb->pquery("INSERT IGNORE INTO globalsearch_settings (gstabid) (SELECT vtiger_entityname.tabid FROM vtiger_entityname LEFT JOIN globalsearch_settings ON vtiger_entityname.tabid = globalsearch_settings.gstabid)");
		$sql = 'SELECT * from vtiger_entityname';
		$sql .= " LEFT JOIN globalsearch_settings ON vtiger_entityname.tabid = globalsearch_settings.gstabid ";

		$params = array();
		if ($tabid) {
			$sql .= ' WHERE tabid = ?';
			$params[] = $tabid;
		}
		$sql .= ' ORDER BY sequence';
		$result = $adb->pquery($sql, $params, true);
		$moduleEntity = array();
		for ($i = 0; $i < $adb->num_rows($result); $i++) {
			$row = $adb->query_result_rowdata($result, $i);
			$moduleEntity[$row['tabid']] = $row;
		}

		return $moduleEntity;
		$log->debug("Exiting Settings_Search_Module_Model::getModulesEntity() method ...");
	}

	public function getFieldFromModule()
	{
		$adb = PearDatabase::getInstance();
		$result = $adb->pquery("SELECT * from vtiger_field WHERE uitype NOT IN ('15','16','52','53','56','70','120')");
		$fields = array();
		while ($row = $adb->fetch_array($result)) {
			$fields[$row['tabid']][] = $row;
		}
		return $fields;
	}

	public static function compare_vale($actions, $item)
	{
		if (strpos($actions, ',')) {
			$actionsTab = explode(",", $actions);
			if (in_array($item, $actionsTab)) {
				$return = true;
			} else {
				$return = false;
			}
		} else {
			$return = $actions == $item ? true : false;
		}
		return $return;
	}

	public function Save($params)
	{
		$adb = PearDatabase::getInstance();
		$name = $params['name'];

		if ($name == 'searchcolumn' || $name == 'displayfield') {
			$value = implode(',', $params['value']);
 
			$adb->pquery("UPDATE vtiger_entityname LEFT JOIN globalsearch_settings ON vtiger_entityname.tabid = globalsearch_settings.gstabid SET " . $name . " = ? WHERE tabid = ?", array($value, (int) $params['tabid']));
		} elseif ($name == 'turn_off') {
			$adb->pquery("UPDATE vtiger_entityname LEFT JOIN globalsearch_settings ON vtiger_entityname.tabid = globalsearch_settings.gstabid SET turn_off = ? WHERE tabid = ?", array($params['value'], (int) $params['tabid']));
		}

	}

	public function UpdateLabels($params)
	{
		$log = vglobal('log');
		$log->debug("Entering Settings_Search_Module_Model::UpdateLabels(" . $params . ") method ...");
		$adb = PearDatabase::getInstance();
		$tabid = (int) $params['tabid'];
		$modulesEntity = self::getModulesEntity($tabid);
		$moduleEntity = $modulesEntity[$tabid];
		$modulename = $moduleEntity['modulename'];
		$tablename = $moduleEntity['tablename'];


		$entityidfield = $moduleEntity['entityidfield'];

		$primary = CRMEntity::getInstance($modulename);
		$moduleothertables = $primary->tab_name_index;
		unset($moduleothertables[$tablename], $moduleothertables['vtiger_crmentity']);		

//		$moduleothertables = array_diff($moduleothertables, array('crmid',$entityidfield));


		foreach ($moduleothertables as $othertable => $otherindex) {
			if (isset($moduleothertables)) {

				$otherquery .= " LEFT JOIN $othertable ON $othertable.$otherindex=$tablename.$entityidfield";

				} else {

				$otherquery .= '';
			}

		}

		$fieldname = $moduleEntity['displayfield'];
		$searchcolumn = $moduleEntity['searchcolumn'];
		$moduleInfo = Vtiger_Functions::getModuleFieldInfos($modulename);
		$columns_name = explode(',', $fieldname);
		$columns_search = explode(',', $searchcolumn);
		$sql_ext = '';
		$sql_fieldname = '';
		$sql_searchcolumn = '';
		
		$moduleInfoExtend = [];
		foreach ($moduleInfo as $field => $fieldInfo) {
			$moduleInfoExtend[$fieldInfo['columnname']] = $fieldInfo;
		}
		
		foreach ($columns_name as $key => $columnName) {
			$fieldObiect = $moduleInfoExtend[$columnName];
			if (in_array($fieldObiect['uitype'], array(10, 51, 75, 81))) {
				$sql_ext .= " LEFT JOIN (SELECT extj_$key.crmid, extj_$key.label AS ext_$columnName FROM vtiger_crmentity extj_$key) ext_$key ON ext_$key.crmid = " . $fieldObiect['tablename'] . ".$columnName";
				$sql_fieldname .= ",ext_$columnName";
			} else {
				$sql_fieldname .= ",$columnName";
			}
		}
		foreach ($columns_search as $key => $columnName) {
			$fieldObiect = $moduleInfoExtend[$columnName];
			if (in_array($fieldObiect['uitype'], array(10, 51, 75, 81))) {
				$sql_ext2 = " LEFT JOIN (SELECT extj_$key.crmid, extj_$key.label AS ext_$columnName FROM vtiger_crmentity extj_$key) ext_$key ON ext_$key.crmid = " . $fieldObiect['tablename'] . ".$columnName";
				if (!strstr($sql_ext, $sql_ext2)) {
					$sql_ext .= $sql_ext2;
				}
				$sql_searchcolumn .= ",ext_$columnName";
			} else {
				$sql_searchcolumn .= ",$columnName";
			}
		}

		$sql = "UPDATE vtiger_crmentity INNER JOIN globalsearch_data ON vtiger_crmentity.crmid = globalsearch_data.gscrmid";
		$sql .= " LEFT JOIN $tablename ON vtiger_crmentity.crmid = $tablename.$entityidfield ";
		$sql .= $sql_ext;
		$sql .= $otherquery;
		$sql .= " SET vtiger_crmentity.label = CONCAT_WS(' ' $sql_fieldname), globalsearch_data.searchlabel = CONCAT_WS(' ' $sql_searchcolumn)";
		$sql .= " WHERE vtiger_crmentity.setype = '$modulename'";
		$adb->pquery("INSERT IGNORE INTO globalsearch_data (gscrmid) (SELECT crmid FROM vtiger_crmentity LEFT JOIN globalsearch_data ON vtiger_crmentity.crmid = globalsearch_data.gscrmid)");
		$adb->query($sql);
		$log->debug("Exiting Settings_Search_Module_Model::UpdateLabels() method ...");
	}

	public function updateSequenceNumber($modulesSequence)
	{
		$log = vglobal('log');
		$log->debug("Entering Settings_Search_Module_Model::updateSequenceNumber(" . $modulesSequence . ") method ...");
		$tabIdList = array();
		$db = PearDatabase::getInstance();

		$query = 'UPDATE vtiger_entityname ';
		$query .='LEFT JOIN globalsearch_settings ON vtiger_entityname.tabid = globalsearch_settings.gstabid SET ';
		$query .=' sequence= CASE ';
		foreach ($modulesSequence as $newModuleSequence) {
			$tabId = $newModuleSequence['tabid'];
			$sequence = $newModuleSequence['sequence'];
			$tabIdList[] = $tabId;
			$query .= ' WHEN tabid=' . $tabId . ' THEN ' . $sequence;
		}

		$query .=' END ';

		$query .= ' WHERE tabid IN (' . generateQuestionMarks($tabIdList) . ')';
		$db->pquery($query, array($tabIdList));
		$log->debug("Exiting Settings_Search_Module_Model::updateSequenceNumber() method ...");
	}


}
