<?php


class MailChimp_Exception extends Exception {

	public function __construct(MCAPI $api) {
		parent::__construct($api->errorMessage, $api->errorCode);
	}
}