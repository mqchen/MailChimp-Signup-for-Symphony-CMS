<?php

require_once dirname(__FILE__) . '/MailChimp_Client.php';


class MailChimp_Member {
	public $email;
	public $merge = array();
	public $events = array();
	
	
	public function __construct($email, array $merge, array $events = array()) {
		$this->email = $email;
		$this->merge = $merge;
		$this->events = $events;
	}
	
	
	public function addEvent($id) {
		if(is_array($id)) {
			$this->events = $this->events + $id;
		}
		else {
			$this->events[] = $id;
		}
	}
	
	public static function createFromAPIReturn(array $ret) {
		$merge = array();
		foreach($ret as $key => $value) {
			$key = strtolower($key);
			if($key === 'email') continue;
			$merge[$key] = $value;
		}
		
		$member = new self($ret['EMAIL'],$merge);
		
		// Events
		$eventIds = array();
		foreach($ret["GROUPINGS"] as $collection) {
			$groups = explode(',', $collection["groups"]);
			foreach($groups as $groupName) {
				// Attempt to extract id
				$groupId = MailChimp_Client::getIdFromGroupName($groupName);
				if(!is_null($groupId)) {
					$eventIds[] = $groupId;
				}
			}
		}
		
		$member->events = $eventIds;
		
		return $member;
	}
	
	public function createMergeArray(MailChimp_Client $client) {
		
		$merge = $this->merge;
		//$merge['EMAIL'] = $this->email;
		if(count($this->events) > 0) {
			$merge['GROUPINGS'] = array(
				array(
					'name' => $client->groupingsName,
					'groups' => implode(',', $client->getNamesFromLocalIds($this->events)),
				)
			);
		}
		
		return $merge;
	}
}