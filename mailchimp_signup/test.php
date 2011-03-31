<?php

header("Content-type: text/plain");

require_once 'lib/MailChimp_Client.php';
require_once 'lib/MailChimp_WelcomeEmail.php';

$w = new MailChimp_WelcomeEmail(5, 197, 'supersecretkey');
var_dump($w->send('mq.chen@gmail.com', 'Hello'));

/*
$apiKey = '3195d0a4072e215253a9e6f73a6dc734-us2';
$listId = 'a748c5d5a7';
$email = 'mqchen+test@gmail.com';



$m = new MailChimp_Client($apiKey, $listId);


// Add member
/*$member = new MailChimp_Member('mqchen+testing2@gmail.com', 'Test2', 'Testing2', '123123123', 'I want to sell ice cream!');
$member->addEvent(1337);

$m->addMember($member);
*/
/*
$m->groupinsName = 'Events';

$member = new MailChimp_Member('mqchen+test5@gmail.com', array(
	'fname' => '123Tester',
	'lname' => 'Testersen',
	'phone' => '1234123',
	'idea' => 'I want to sell juice',
));

$m->addMember($member);*/


// Create a group
//$m->createGroup(123, "Shaggy group");


// Add existing member to new group
/*$member = $m->getMember('mqchen+test@gmail.com');
$member->addEvent(123);
$m->updateMember($member);*/

