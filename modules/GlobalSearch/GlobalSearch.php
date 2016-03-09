<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by crm-now are Copyright (C) crm-now GmbH.
 * All Rights Reserved.
 *************************************************************************************/
require_once('include/events/include.inc');

class Search {

	/**
	 * Invoked when special actions are performed on the module.
	 * @param String Module name
	 * @param String Event Type (module.postinstall, module.disabled, module.enabled, module.preuninstall)
	 */
	function vtlib_handler($modulename, $event_type) {
		require_once('include/utils/utils.php');			
		if($event_type == 'module.postinstall') {
			$db = PearDatabase::getInstance();
			include_once('vtlib/Vtiger/Module.php');
			//adds settings menu
			$fieldid = $db->getUniqueID('vtiger_settings_field');
			$blockid = getSettingsBlockId('LBL_OTHER_SETTINGS');
			$seq_res = $db->pquery("SELECT max(sequence) AS max_seq FROM vtiger_settings_field WHERE blockid = ?", array($blockid));
			$seq = 1;
			if ($db->num_rows($seq_res) > 0) {
				$cur_seq = $db->query_result($seq_res, 0, 'max_seq');
				if ($cur_seq != null) {
					$seq = $cur_seq + 1;
				}
			}
			$db->pquery('INSERT INTO vtiger_settings_field(fieldid, blockid, name, iconpath, description, linkto, sequence, active, pinned)
				VALUES (?,?,?,?,?,?,?,?,?)', array($fieldid, $blockid, 'Search Setup', '', 'LBL_SEARCH_SETUP_DESCRIPTION',
					'index.php?module=Search&parent=Settings&view=Index', $seq,0,0));
 			
			//register events
			$db = PearDatabase::getInstance();
            $EventManager = new VTEventsManager($db);
			$createEvent = 'vtiger.entity.aftersave';
			$handler_path = 'modules/Settings/Search/handlers/RecordSearchLabelUpdater.php';
			$className = 'Settings_Search_RecordSearchLabelUpdater_Handler';
			$EventManager->registerHandler($createEvent, $handler_path, $className, NULL ,'1','[]');
		} else if($event_type == 'module.disabled') {
			// TODO Handle actions when this module is disabled.
			return;
		} else if($event_type == 'module.enabled') {
			// TODO Handle actions when this module is enabled.
			return;
		} else if($event_type == 'module.preuninstall') {
			// TODO Handle actions when this module is about to be deleted.
			return;		
		} else if($event_type == 'module.preupdate') {
			// TODO Handle actions before this module is updated.
			return;			
		} else if($event_type == 'module.postupdate') {
			return;			
		}
	}
}
?>