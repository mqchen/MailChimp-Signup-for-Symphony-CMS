<?php

require_once dirname(__FILE__) . '/MCAPI/MCAPI.class.php';
require_once dirname(__FILE__) . '/MailChimp_Exception.php';
require_once dirname(__FILE__) . '/MailChimp_Member.php';

class MailChimp_Client {
	
	protected $api;
	public $listId;
	public $groupinsName = '';
	protected $log = null;
	
	public function __construct($apiKey, $listId) {
		$this->api = new MCAPI($apiKey);
		$this->listId = $listId;
	}
	
	protected function checkForError($throw = true) {
		if($this->api->errorCode) {
			if($throw) {
				throw new MailChimp_Exception($this->api);
			}
			return true;
		}
		return false;
	}
	
	public function getLastErrorMessage() {
		return $this->api->errorMessage;
	}
	
	public function getLastErrorCode() {
		return $this->api->errorCode;
	}
	
	
	public function getMember($memberEmail) {
		$ret = $this->api->listMemberInfo($this->listId, $memberEmail);
		
		$this->checkForError();
		
		if(array_key_exists("data", $ret)) {
			if(array_key_exists(0, $ret["data"])) {
				if(!array_key_exists("error", $ret["data"][0])) {
					$tmp = $ret["data"][0]["merges"];
					$member = MailChimp_Member::createFromAPIReturn($tmp);
					return $member;
				}
			}
		}
		return null;
	}
	
	
	/**
	 * This method does not check if member exists or not. It assumes the member
	 * doesnt exist.
	 *
	 *
	 * @param unknown_type $email
	 * @param unknown_type $firstName
	 * @param unknown_type $lastName
	 * @param unknown_type $phoneNumber
	 * @param unknown_type $idea
	 */
	public function addMember(MailChimp_Member $member) {
		$merge = $member->createMergeArray($this);
		
		// Add the OPTIN field
		$merge['OPTINIP'] = $_SERVER['REMOTE_ADDR'];
		
		$ret = $this->api->listSubscribe($this->listId, $member->email, $merge, 'html', false, true, true, true);
		
		return !$this->checkForError(false);
	}
	
	
	/**
	 * If member already exists in list, then use this method to add existing member
	 * to new group.
	 *
	 * @param MailChimp_Member $member
	 * @param array $localIds
	 */
	public function updateMember(MailChimp_Member $member) {
		
		// update member
		$merge = $member->createMergeArray($this);
		
		$this->api->listUpdateMember($this->listId, $member->email, $merge, false);
		
		return !$this->checkForError(false);
	}
	
	public function logMemberSignup(MailChimp_Member $member) {
		if($this->log === null) {
			//$this->log = new Log(ACTIVITY_LOG);
			$this->log = MANIFEST . '/logs/' . __CLASS__ . '-log.csv';
		}
		$merge = array_values($member->createMergeArray($this));
		array_unshift($merge, date('r', time()), $member->email, implode(',', $member->events));
		
		/// Convert to CSV using fputcsv
		$handle = @fopen('php://memory', 'w+');
		if(!$handle) {
			return false;
		}
		$written = fputcsv($handle, $merge);
		if($written === false) {
			fclose($handle);
			return false;
		}
		rewind($handle);
		$csv = trim(fread($handle, $written + 1)) . "\n";
		fclose($handle);
		
		/*
		$csv = array();
		foreach($merge as $v) {
			if(is_array($v)) {
				$v = implode(';', $v);
			}
			$csv[] = '"' . addcslashes('' . $v, '"\\') . '"';
		}
		$csv = implode(',', $csv) . "\n";*/
		
		return @file_put_contents($this->log, $csv, FILE_APPEND);
	}
	
	public static function getIdFromGroupName($groupName) {
		
		if(preg_match("/^.*\([0-9]+\)$/", $groupName)) {
			return intval(preg_replace("/^.*\(([0-9]+)\)$/", "\\1", $groupName));
		}
		
		return null;
	}
	
	public function getNamesFromLocalIds(array $localIds) {
		$groupNames = array();
		
		// Get groups with those IDs
		$ret = $this->api->listInterestGroupings($this->listId);
		
		foreach($ret as $collection) {
			foreach($collection["groups"] as $group) {
				// Attempt to extract id
				$groupId = $this->getIdFromGroupName($group["name"]);
				if(!is_null($groupId) && in_array($groupId, $localIds)) {
					$groupNames[] = $group["name"];
				}
			}
		}
		
		return $groupNames;
	}
	
	protected function makeGroupName($localId, $name) {
		return str_replace(',', ' ', $name) . ' (' . $localId . ')';
	}
	
	public function createGroup($localId, $name) {
		$ret = $this->api->listInterestGroupAdd($this->listId, $this->makeGroupName($localId, $name));
		return !$this->checkForError(false);
	}
	
	public function removeGroups(array $localIds) {
		$names = $this->getNamesFromLocalIds($localIds);
		foreach($names as $name) {
			$ret = $this->api->listInterestGroupDel($this->listId, $name);
		}
		return !$this->checkForError(false);
	}
	
	public function removeGroup($localId) {
		return $this->removeGroups(array($localIds));
	}
	
	public function renameGroup($localId, $newName) {
		$name = $this->getNamesFromLocalIds(array($localId));
		$name = $name[0];
		
		$ret = $this->api->listInterestGroupUpdate($this->listId, $name, $this->makeGroupName($localId, $newName));
		return !$this->checkForError(false);
	}
	
}