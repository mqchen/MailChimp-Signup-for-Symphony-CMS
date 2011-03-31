<?php

if(!defined('DOCROOT')) {
	define('DOCROOT', realpath(dirname(__FILE__) . '/../../../'));
	define('DOMAIN', rtrim(rtrim($_SERVER['HTTP_HOST'], '\\/') . dirname($_SERVER['PHP_SELF']), '\\/'));
	require_once DOCROOT . '/symphony/lib/boot/bundle.php';
	require_once CORE . '/class.frontend.php';
}
require_once dirname(__FILE__) . '/MailChimp_Client.php';

class MailChimp_Utils {
	
	private static $instance = null;
	
	private static $cryptKey = 'monkeyeatsbananas';
	protected static $key = 'mailchimpsignup';
	protected $config = null;
	protected $sectionsConfig = null;
	protected $client = null;
	
	private function __construct() {
		
	}
	
	public static function instance() {
		if(!isset(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	public static function getKey() {
		return self::$key;
	}
	
	protected static function getCryptKey() {
		return md5(self::$cryptKey);
	}

	
	public function getConfig() {
		
		if(is_array($this->config)) {
			return $this->config;
		}
		
		// Check if it exists first
		$tmp = Symphony::Configuration()->get(self::getKey());
		if(!is_array($tmp)) {
			$tmp = array();
		}
		
		if(!array_key_exists('apikey', $tmp)) {
			$tmp['apikey'] = '';
		}
		
		Symphony::Configuration()->set(self::getKey(), $tmp);
		
		$this->config = $tmp;
		
		return Symphony::Configuration()->get(self::getKey());
	}
	
	
	public function getWebHookKey() {
		$key = @file_get_contents(MANIFEST . '/' . self::getKey() . '_webhookkey.txt');
		return (is_string($key)) ? preg_replace('/[^a-zA-Z0-9]/', '', trim($key)) : '';
	}
	
	public function writeWebHookKey($key) {
		// Sanitize key
		$key = preg_replace('/[^a-zA-Z0-9]/', '', $key);
		file_put_contents(MANIFEST . '/' . self::getKey() . '_webhookkey.txt', trim($key));
	}
	
	
	public function getSectionsConfig($sectionId = null) {
		if(!is_array($this->sectionsConfig)) {
			$this->sectionsConfig = unserialize(file_get_contents(MANIFEST . '/' . self::getKey() . '_sections.txt'));
			
			// Sanitize
			foreach($this->sectionsConfig as $key => $value) {
				if(array_key_exists('monitor', $value)) {
					$this->sectionsConfig[$key]['monitor'] = (boolean) $value['monitor'];
				}
				else {
					$this->sectionsConfig[$key]['monitor'] = false;
				}
			}
		}
		
		if($sectionId == null) {
			return $this->sectionsConfig;
		}
		else {
			if(array_key_exists(intval($sectionId), $this->sectionsConfig)) {
				return $this->sectionsConfig[$sectionId];
			}
			else {
				return array(
					'monitor' => false,
					'listid' => '',
					'groupingsname' => '',
					'buildgroupname' => '',
					'emailtitle' => '',
					'emailbody' => ''
				);
			}
		}
	}
	
	public function writeSectionsConfig(array $config) {
		file_put_contents(MANIFEST . '/' . self::getKey() . '_sections.txt', serialize($config), LOCK_EX);
	}
	
	/*
	public static function getSMTPDataPrototype() {
		return array(
			'host' => '',
			'port' => 0,
			'username' => '',
			'password' => '',
			'ssl' => false
		);
	}
	
	public static function getSMTPInfo() {
		$file = MANIFEST . '/' . self::getKey() . '_smtp.txt';
		$orgData = self::getSMTPDataPrototype();
		if(is_readable($file)) {
			$data = file_get_contents($file);
			$data = base64_decode($data);
			$data = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, self::getCryptKey(), $data, MCRYPT_MODE_CBC, md5(self::getCryptKey()));
			$data = unserialize($data);
			$orgData = array_merge($orgData, $data);
		}
		
		return $orgData;
	}
	
	public static function writeSMTPInfo(array $data) {
		$data = serialize($data);
		$data = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, self::getCryptKey(), $data, MCRYPT_MODE_CBC, md5(self::getCryptKey()));
		$data = base64_encode($data);
		return file_put_contents(MANIFEST . '/' . self::getKey() . '_smtp.txt', $data, LOCK_EX);
	}*/
	
	
	public function getMailChimpClient($listId) {
		if(!($this->client instanceof MailChimp_Client && $this->client->listId == $listId)) {
			$config = $this->getConfig();
			$this->client = new MailChimp_Client($config['apikey'], $listId);
		}
		
		return $this->client;
	}
	
}