<?php


require_once dirname(__FILE__) .'/lib/MailChimp_Client.php';
require_once dirname(__FILE__) .'/lib/MailChimp_Utils.php';

class extension_mailchimp_signup extends Extension {
	
	public function about() {
		return array(
			'name' => 'MailChimp signup',
			'version' => '0.1',
			'release-date' => '2011-01-16',
			'author' => array('name' => '<a href="http://designoslo.com">Moquan Chen</a>'),
			'description' => 'When a user sends a form, he is signed up to a list plus group.'
		);
	}
	

	public function install() {
		MailChimp_Utils::instance()->getConfig();
		Administration::instance()->saveConfig();
		
		// Sections config file
		file_put_contents(MANIFEST . '/' . MailChimp_Utils::getKey() . '_sections.txt', serialize(array()), LOCK_EX);
		
		// SMTP info
		//file_put_contents(MANIFEST . '/' . MailChimp_Utils::getKey() . '_smtp.txt', '');
	}
	
	public function uninstall() {
		Symphony::Configuration()->remove(MailChimp_Utils::getKey());
		Administration::instance()->saveConfig();
		
		// Remove sections and smtp file
		@unlink(MANIFEST . '/' . MailChimp_Utils::getKey() . '_sections.txt');
		//@unlink(MANIFEST . '/' . MailChimp_Utils::getKey() . '_smtp.txt');
	}
	
	
	public function getSubscribedDelegates() {
		return array(
			array(
				'page' => '/system/preferences/',
				'delegate' => 'AddCustomPreferenceFieldsets',
				'callback' => 'addPrefs'
			),
			array(
				'page' => '/system/preferences/',
				'delegate' => 'Save',
				'callback' => 'savePrefs'
			),
			array(
				'page' => '/publish/new/',
				'delegate' => 'EntryPostCreate',
				'callback' => 'createGroup'
			),
			array(
				'page' => '/publish/',
				'delegate' => 'Delete',
				'callback' => 'removeGroup'
			),
			array(
				'page' => '/publish/edit/',
				'delegate' => 'EntryPostEdit',
				'callback' => 'renameGroup'
			)
		);
	}
	
	
	
	protected function isSectionMonitored($context) {
		// Check if this is one of the monitored sections
		$sectionId = $context['entry']->get('section_id');
		$sectionConfig = MailChimp_Utils::instance()->getSectionsConfig($sectionId);
		
		return $sectionConfig['monitor'];
	}
	
	protected function getSectionId($context) {
		return $context['entry']->get('section_id');
	}
	
	protected function initClientForSectionChange(array $sectionConfig) {
		$client = MailChimp_Utils::instance()->getMailChimpClient($sectionConfig['listid']);
		$client->groupingsName = $sectionConfig['groupingsname'];
		
		return $client;
	}
	
	protected function buildGroupName($format, $fields) {
		$name = $format;
		foreach($fields as $key => $value) {
			if($key == 'date') {
				$value = date('d-M-Y', strtotime($value['start'][0]));
			}
			
			$name = str_replace('{$'.$key.'}', $value, $name);
		}
		return $name;
	}
	
	public function createGroup($context) {
		
		// Check if this is one of the monitored sections
		$sectionId = $this->getSectionId($context);
		$sectionConfig = MailChimp_Utils::instance()->getSectionsConfig($sectionId);
		
		if(!$this->isSectionMonitored($context)) return; // This section is not monitored
		
		$client = $this->initClientForSectionChange($sectionConfig);
		
		// Build the group name
		$name = $this->buildGroupName($sectionConfig['buildgroupname'], $context['fields']);
		
		$client->createGroup($context['entry']->get('id'), $name);
		
		
		//print_r($context['entry']->get());
		/*print_r($context['fields']);
		print_r($context['section']->get());*/
	}
	
	public function removeGroup($context) {
		
		// Since entryIds are unique, it is no need to check for the entry«s sectionConfig.
		// Go through all section configs / lists, and delete all entry ids
		
		$localIds = $context['entry_id'];
		if(!is_array($localIds)) {
			$localIds = array($localIds);
		}
		
		
		foreach(MailChimp_Utils::instance()->getSectionsConfig() as $sectionConfig) {
			if($sectionConfig['monitor']) {
				$client = $this->initClientForSectionChange($sectionConfig);
				$client->removeGroups($localIds);
			}
		}
		
	}
	
	public function renameGroup($context) {
		// Check if this is one of the monitored sections
		$sectionId = $this->getSectionId($context);
		$sectionConfig = MailChimp_Utils::instance()->getSectionsConfig($sectionId);
		
		if(!$this->isSectionMonitored($context)) return; // This section is not monitored
		
		$client = $this->initClientForSectionChange($sectionConfig);
		
		// Build the group name
		$name = $this->buildGroupName($sectionConfig['buildgroupname'], $context['fields']);
		
		$client->renameGroup($context['entry']->get('id'), $name);
	}
	
	
	public function addPrefs($context) {
		
		require_once TOOLKIT . '/class.sectionmanager.php';
		
	    $config = MailChimp_Utils::instance()->getConfig();
		
		$sectionManager = new SectionManager($context['parent']);
	    $sections = $sectionManager->fetch(NULL, 'ASC', 'name');
	    
	    // Fieldset
	    $fieldset = new XMLElement('fieldset');
	    $fieldset->setAttribute('class', 'settings');
	    $legend = new XMLElement('legend');
	    $legend->setValue('MailChimp Signup');
	    $fieldset->appendChild($legend);
	    
	    // Help
	    $help = new XMLElement('p');
	    $help->setAttribute('class', 'help');
	    $help->setValue('Configure which sections MailChimp should interperet as groups/events.');
	    $fieldset->appendChild($help);
	    
	    
	    /// Mailchimp stuff
    	$group = new XMLElement('div');
	    $group->setAttribute('class', 'group');
	    
	    /// Add apikey
	    $label = Widget::Label('MailChimp API key');
	    $label->appendChild(Widget::Input('settings['.MailChimp_Utils::getKey().'][apikey]', $config['apikey'], 'text'));
	    $group->appendChild($label);
	    
	    /// Webhook authokey
	    $label = Widget::Label('Webhook authorization key');
	    $label->appendChild(Widget::Input(MailChimp_Utils::getKey().'[webhookkey]', MailChimp_Utils::instance()->getWebHookKey(), 'text'));
	    $group->appendChild($label);
	    
	    $fieldset->appendChild($group);
	    
	    
	    /// SMTP Email stuff
	    //$this->makeSMTPInfoFields($fieldset);
	    
	    
	    /// Add a group for each section (fields: checkbox - monitor section, text - listid, text - grouping name, text - group name
	    foreach($sections as $section) {
	    	$this->makeSectionGroup($section, $fieldset);
	    	//$fieldset->appendChild(new XMLElement('hr'));
	    }
	    
	    // Add to pref
	    $context["wrapper"]->appendChild($fieldset);
	}
	/*
	protected function makeSMTPInfoFields(XMLElement $fieldset) {
		
		$data = MailChimp_Utils::getSMTPInfo();
		
		$namePrefix = MailChimp_Utils::getKey() . '[smtp]';
		
		/// Server info
		$group = new XMLElement('div');
	    $group->setAttribute('class', 'group');
		
	    // host
		$label = Widget::Label('SMTP host');
		$label->appendChild(Widget::Input($namePrefix . '[host]', $data['host'], 'text'));
		$group->appendChild($label);
		
		// port
		$label = Widget::Label('SMTP port');
		$label->appendChild(Widget::Input($namePrefix . '[port]', $data['port'], 'text'));
		$group->appendChild($label);
		
		$fieldset->appendChild($group);
		
		/// login info
		$group = new XMLElement('div');
	    $group->setAttribute('class', 'group');
	    
	    // Username
		$label = Widget::Label('User name');
		$label->appendChild(Widget::Input($namePrefix . '[username]', $data['username'], 'text'));
		$group->appendChild($label);
		
		// password
		$label = Widget::Label('Password');
		$label->appendChild(Widget::Input($namePrefix . '[password]', ($data['password'] === '' ? '' : sha1('old password')), 'password'));
		$group->appendChild($label);
		
		$fieldset->appendChild($group);
		
		// Use SSL?
		$label = Widget::Label();
		$checkbox = Widget::Input($namePrefix . '[ssl]',
			'1', 'checkbox',
			($data['ssl'] ? array('checked' => 'checked') : array())
		);
		$label->setValue($checkbox->generate() . ' Use Secure Sockets Layer (SSL)');
		$fieldset->appendChild($label);
	}*/
	
	
	protected function makeSectionGroup(Section $section, XMLElement $fieldset) {
		
		$sectionConfig = MailChimp_Utils::instance()->getSectionsConfig($section->get('id'));
		$fieldprefix = MailChimp_Utils::getKey() . '[sections][' . $section->get('id') . ']';
		
		// Title
    	$fieldset->appendChild(new XMLElement('strong', $section->get('name')));
		
		// Monitor checkbox
		$label = Widget::Label();
		$checkbox = Widget::Input($fieldprefix . '[monitor]',
			'1', 'checkbox',
			($sectionConfig['monitor'] ? array('checked' => 'checked') : array())
		);
		$label->setValue($checkbox->generate() . ' Monitor section');
		$fieldset->appendChild($label);
		
		
		$group = new XMLElement('div', NULL, array('class' => 'group'));
		
		// List id
		$label = Widget::Label('List ID');
	    $label->appendChild(Widget::Input($fieldprefix . '[listid]',
	    	$sectionConfig['listid'], 'text'));
	    $group->appendChild($label);
	    
	    
	    // Groupings name
	    $label = Widget::Label('Groupings name');
	    $label->appendChild(Widget::Input($fieldprefix . '[groupingsname]',
	    	$sectionConfig['groupingsname'], 'text'));
	    $group->appendChild($label);
	    
	    $fieldset->appendChild($group);
	    
	    
	    // Build group name
	    $label = Widget::Label('Build group name');
	    $label->appendChild(Widget::Input($fieldprefix . '[buildgroupname]', $sectionConfig['buildgroupname'], 'text'));
	    $fieldset->appendChild($label);
	    
	    $fieldset->appendChild(new XMLElement('p', 'Use the following fields if you wish to send an email to the member once he/she has signed up to an event'));
	    
	    
	    $group = new XMLElement('div', NULL, array('class' => 'group'));
	    
	    // Welcome email title
	    $label = Widget::Label('Welcome email title');
	    $options = array();
	    // Disable
	    $options[] = array('', false, 'Do not send');
	    $options = $this->makeSectionFieldsSelectOptions($section, 'emailtitle', $options);
	    $label->appendChild(Widget::Select($fieldprefix . '[emailtitle]', $options));
	    $group->appendChild($label);
	    
	    
	    // Welcome email body
	    $label = Widget::Label('Welcome email body');
	    $options = array();
	    // Disable
	    $options[] = array('', false, 'Do not send');
	    $options = $this->makeSectionFieldsSelectOptions($section, 'emailbody', $options);
	    $label->appendChild(Widget::Select($fieldprefix . '[emailbody]', $options));
	    $group->appendChild($label);
	    
	    $fieldset->appendChild($group);
	    
	    $group = new XMLElement('div', NULL, array('class' => 'group'));
	    
	    // Welcome email reply-to address
	    $label = Widget::Label('Welcome email reply-to address');
	    $options = array();
	    // Disable
	    $options[] = array('', false, 'Do not specify');
	    $options = $this->makeSectionFieldsSelectOptions($section, 'emailreplyto', $options);
	    $label->appendChild(Widget::Select($fieldprefix . '[emailreplyto]', $options));
	    $group->appendChild($label);
	    
	    
	    $fieldset->appendChild($group);
	    
	}
	
	
	protected function makeSectionFieldsSelectOptions(Section $section, $selectedKey = null, array $options = array()) {
		foreach($section->fetchFields() as $field) {
	    	$fieldKey = $field->get('id');
	    	$fieldName = $field->get('label');
	    	$conf = MailChimp_Utils::instance()->getSectionsConfig($section->get('id'));
	    	$options[] = array(
	    		$fieldKey,
	    		($selectedKey != null && $conf[$selectedKey] == $fieldKey),
	    		$fieldName,
	    	);
	    }
	    return $options;
	}
	
	public function savePrefs($context) {
		
		// Update API key
		$config = MailChimp_Utils::instance()->getConfig();
		$config['apikey'] = $_POST['settings'][MailChimp_Utils::getKey()]['apikey'];
		Symphony::Configuration()->set(MailChimp_Utils::getKey(), $config);
		Administration::instance()->saveConfig();
		
		
		// Update webhookkey
		$webhookkey = $_POST[MailChimp_Utils::getKey()]['webhookkey'];
		MailChimp_Utils::instance()->writeWebHookKey($webhookkey);
		
		
		// Update sections
		if(isset($_POST[MailChimp_Utils::getKey()]['sections'])) {
			$sections = $_POST[MailChimp_Utils::getKey()]['sections'];
			foreach($sections as $id => $section) {
				if(array_key_exists('monitor', $section)) {
					$sections[$id]['monitor'] = true;
				}
				else {
					$sections[$id]['monitor'] = false;
				}
			}
			
			// write to file
			MailChimp_Utils::instance()->writeSectionsConfig($sections);
		}
		
		
		// Update SMTP info
		/*if(isset($_POST[MailChimp_Utils::getKey()]['smtp'])) {
			$data = MailChimp_Utils::getSMTPInfo();
			$postData = $_POST[MailChimp_Utils::getKey()]['smtp'];
			
			
			// Host
			$data['host'] = strval($postData['host']);
			
			// Port
			$data['port'] = intval($postData['port']);
			
			// SSL
			$data['ssl'] = (boolean) $postData['ssl'];
			
			// Password
			if($postData['password'] === sha1('old password')) {
				// keep old
			}
			else {
				$data['password'] = strval($postData['password']);
			}
			
			
			// username
			$data['username'] = strval($postData['username']);
			
			MailChimp_Utils::writeSMTPInfo($data);
		}*/
	}
}