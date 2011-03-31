<?php


require_once dirname(__FILE__) . '/../lib/MailChimp_Client.php';
require_once dirname(__FILE__) . '/../lib/MailChimp_Utils.php';
require_once dirname(__FILE__) . '/../lib/MailChimp_Exception.php';
require_once dirname(__FILE__) . '/../lib/MailChimp_Member.php';
require_once dirname(__FILE__) . '/../lib/MailChimp_WelcomeEmail.php';

class eventMailChimpSignup_Signup extends Event {
	
	
	public static function about() {
		return array(
			 'name' => 'MailChimp Signup: New Signup',
			 'author' => array('name' => 'Moquan Chen',
							   'website' => 'http://designoslo.com',
							   'email' => 'mq.chen@gmail.com'),
			 'version' => '1.0',
			 'release-date' => '2011-02-10',
		);
	}
	
	public function documentation() {
		return new XMLElement('p', 'Event for new member signup.');
	}
	
	public function load() {
		// Only run if a post request from a mailchimpsignup form
		if(isset($_POST[MailChimp_Utils::getKey()]) && isset($_POST['fields'])) {
			return $this->__trigger();
		}
	}
	
	public function __trigger() {
		
		$result = new XMLElement(MailChimp_Utils::getKey(), $attributes);
		
		$fields = $_POST['fields'];
		
		// Get the section id
		if(isset($fields['sectionId'])) {
			$sectionId = intval($fields['sectionId']);
		}
		else {
			// no section, abort
			$result->setAttribute('result', 'error');
			$result->appendChild(new XMLElement('error', 'SectionID is required.'));
			return $result;
		}
		
		// Get the section config
		$sectionConfig = MailChimp_Utils::instance()->getSectionsConfig($sectionId);
		if(!$sectionConfig['monitor']) return;
		
		// Get the event id
		if(isset($fields['localId'])) {
			$eventId = intval($fields['localId']);
		}
		else {
			// no event, abort
			$result->setAttribute('result', 'error');
			$result->appendChild(new XMLElement('error', 'EventID is required.'));
			return $result;
		}
		
		// Get the email address
		if(isset($fields['email'])) {
			$email = $fields['email'];
		}
		else {
			$result->setAttribute('result', 'error');
			$result->appendChild(new XMLElement('error', 'Email address is required.'));
			return $result;
		}
		
		// Get the rest of the fields
		$merge = array();
		foreach($fields as $key => $value) {
			$merge[strtoupper($key)] = $value;
		}
		
		// Client
		$client = MailChimp_Utils::instance()->getMailChimpClient($sectionConfig['listid']);
		$client->groupingsName = $sectionConfig['groupingsname'];
		
		// Check if member exists
		$member = $client->getMember($email);
		if($member !== null) {
			// Add the new event to the member
			$member->addEvent($eventId);
			$ret = $client->updateMember($member);
			$newMember = false;
		}
		else {
			$member = new MailChimp_Member($email, $merge, array($eventId));
			$ret = $client->addMember($member);
			$newMember = true;
		}
		
		if(!$ret) {
			// Something went wrong
			$error = new XMLElement('error', $client->getLastErrorMessage());
			$error->setAttribute('code', $client->getLastErrorCode());
			$result->setAttribute('result', 'error');
			$result->appendChild($error);
		}
		else {
			$result->setAttribute('result', 'success');
			$success = new XMLElement('success');
			$success->setAttribute('new-member', ($newMember) ? 'Yes' : 'No');
			
			// Email
			$m = new MailChimp_WelcomeEmail($sectionId, $eventId, MailChimp_Utils::getWebHookKey());
			$emailResult = $m->send($member->email);
			
			$success->setAttribute('welcome-email', ($emailResult) ? 'Yes' : 'No');
			
			$result->appendChild($success);
			
			
			// Log
			$client->logMemberSignup($member);
		}
		
		return $result;
	}
}