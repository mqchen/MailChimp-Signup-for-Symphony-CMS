<?php


require_once dirname(__FILE__) . '/MailChimp_Utils.php';
//if(!class_exists('Swift')) { require_once dirname(__FILE__) . '/Swift/lib/swift_required.php'; }
require_once EXTENSIONS . '/swiftmailer/lib/Swift_Mailer_Symphony.php';


class MailChimp_WelcomeEmail {
	
	protected $sectionId;
	protected $entryId;
	
	protected $emailTitle;
	protected $emailBody;
	protected $emailReplyTo;
	
	public function __construct($sectionId, $entryId, $authkey) {
		
		// Check authkey
		if(MailChimp_Utils::instance()->getWebHookKey() !== $authkey) {
			throw new Exception('Wrong authorization key!');
		}
		
		$this->sectionId = $sectionId;
		$this->entryId = $entryId;
	}
	
	/**
	 * Get the title and body based on config.
	 */
	protected function getData() {
		$conf = MailChimp_Utils::instance()->getSectionsConfig($this->sectionId);
		
		$titleFieldName = (isset($conf['emailtitle'])) ? $conf['emailtitle'] : '';
		$bodyFieldName = (isset($conf['emailbody'])) ? $conf['emailbody'] : '';
		$replytoFieldName = (isset($conf['emailreplyto'])) ? $conf['emailreplyto'] : '';
		
		if(strlen($titleFieldName) > 0 && strlen($bodyFieldName) > 0) {
			// Get data from entry
			$parent = class_exists('Administration') ? Administration::instance() : Frontend::instance();
			$entryManager = new EntryManager($parent);
			
			/*
			$element_names = array($titleFieldName, $bodyFieldName);
			if(strlen($replytoFieldName) > 0) { $element_names[] = $replytoFieldName; }*/
			
			$entry = $entryManager->fetch($this->entryId, $this->sectionId);//, null, null, null, false, true, $element_names);
			$data = $entry[0]->getData();
			
			$this->emailTitle = $data[intval($titleFieldName)]['value'];
			$this->emailBody = $data[intval($bodyFieldName)]['value'];
			
			// Maybe specify reply-to?
			$this->emailReplyTo = '';
			if(strlen($replytoFieldName) > 0 && isset($data[intval($replytoFieldName)])) {
				$this->emailReplyTo = $data[intval($replytoFieldName)]['value'];
			}
		}
	}
	
	/**
	 * Send the email
	 * @param string $toEmail
	 * @return bool Success or fail.
	 */
	public function send($toEmail, $name = '') {
		
		$this->getData();
		
		//$smtpInfo = MailChimp_Utils::getSMTPInfo();
		
		$transport = Swift_Mailer_Symphony::getTransport();
		
		$from = (strlen($this->emailReplyTo) == 0) ? Swift_Mailer_Symphony::getSMTPUsername() : $this->emailReplyTo;
		
		/*$transport = Swift_SmtpTransport::newInstance($smtpInfo['host'], $smtpInfo['port'], ($smtpInfo['ssl']) ? 'ssl' : null);
		$transport->setUsername($smtpInfo['username']);
		$transport->setPassword($smtpInfo['password']);*/
		
		$mailer = Swift_Mailer::newInstance($transport);
		
		$m = Swift_Message::newInstance();
		$m->setSubject($this->emailTitle);
		$m->setTo(array($toEmail => $name));
		$m->setFrom(array($from));
		$m->addPart($this->emailBody, 'text/html');
		$m->setBody(strip_tags(str_replace('</p>', "\r\n\r\n</p>", $this->emailBody)), 'text/plain');
		
		return (boolean) $mailer->send($m);
	}
	
}