<?php
include("api.php");										#! Klasse einbinden

$host	= "localhost";									#! Der Host der Datenbank (MySQL)
$user	= "root";										#! MySQL Benutzer
$pw		= "";											#! MySQL Passwort

$mt2 = new Mt2($host,$user,$pw);						#! Neue Klasse erstellen
$mt2->setDebug(true);									#! Damit Fehler ausgegeben werden.
$mt2->loadConfigFile("config.ini");						#! L�dt das Config File

$info = array(
	"login" => "myUsername",
	"password" => "myPw",
	"email" => "myEmail",
);
#$account_id = $mt2->addAccount($info);					#! F�gt den User myUsername ein mit Passwort myPw und E-Mail-Adresse myEmail
#$mt2->makeGM(3,"42.42.42.42","IMPLEMENTOR");			#! Macht den Charakter mit der ID 18648 zum GM
#!$tmp = $mt2->addItem(36424,3009);						#! F�gt dem Account mit der ID 18648 ein Schwert+9 im Itemshop Lager hinzu.
#!$new_version = $mt2->checkForUpdates();				#! Pr�ft nach Updates f�r die API und gibt die neuste Buildnummer zur�ck
#!$mt2->installUpdate($new_version);					#! Installier den gegebenen Build

var_dump($mt2->checkAccountName("xenor"));
var_dump($mt2->checkPw1("passwort"));
var_dump($mt2->checkPw2("passwort","passwort"));
var_dump($mt2->checkEMail("xenor@lifefight.de"));
$mt2->sendValidationEMail("xenor@lifefight.de","xenor");

?>