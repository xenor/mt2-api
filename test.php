<?php
include("mt2-api.php");									#! Klasse einbinden

$host	= "localhost";									#! Der Host der Datenbank (MySQL)
$user	= "root";										#! MySQL Benutzer
$pw		= "";											#! MySQL Passwort

$mt2 = new Mt2($host,$user,$pw);						#! Neue Klasse erstellen
$mt2->setDebug(true);									#! Damit Fehler ausgegeben werden.
$mt2->loadConfigFile("config.ini");						#! Lädt das Config File
$mt2->__init();

$info = array(
	"login" => "myUsername",
	"password" => "myPw",
	"email" => "myEmail",
);
#$account_id = $mt2->addAccount($info);					#! Fügt den User myUsername ein mit Passwort myPw und E-Mail-Adresse myEmail
$mt2->makeGM(3,"42.42.42.42","IMPLEMENTOR");			#! Macht den Charakter mit der ID 18648 zum GM
#$tmp = $mt2->addItem(36424,3009);						#! Fügt dem Account mit der ID 18648 ein Schwert+9 im Itemshop Lager hinzu.
$new_version = $mt2->checkForUpdates();				#! Prüft nach Updates für die API und gibt die neuste Buildnummer zurück
$mt2->installUpdate($new_version);						#! Installier den gegebenen Build
#$mt2->checkAccountName("xenor");#! Überprüft ob der login bereits vergeben ist
$mt2->checkPw1("passwort");#! Überprüft ob der login bereits vergeben ist
$mt2->checkPw2("passwort","passwort");#! Überprüft ob das passwort den bestimmungen entspricht
$mt2->checkEMail("xenor@lifefight.de");#! Überprüft ob die email bereits vergeben ist
#$mt2->sendValidationEMail("xenor@lifefight.de","xenor");#! sendet eine E-Mail
var_dump($mt2->modTest("lolwas","opfer"));#! Testet Mods

var_dump($mt2->getHighscore());
?>