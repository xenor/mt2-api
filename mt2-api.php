<?php
/*
 * metin2 database API by xenor (576-772)
 * visit our server at http://www.xtreamyt2.org
 * please report bugs to me (576-772 on ICQ)
 * check http://www.elitepvpers.de
 */

/*##b##*/
if(!defined("MT2API_BUILD")) define("MT2API_BUILD",42);
define("MT2API_VERSION","0.3.1-".MT2API_BUILD);

#! Patches für Windows Funktionen unter Linux/FreeBSD
if(!function_exists("parse_ini_string"))
{
	function parse_ini_string($string)
	{
		$tmp = explode("\n",$string);
		$return = array();
		foreach($tmp as $line)
		{
			if(trim($line)!="")
			{
				$key = trim(substr($line,0,strpos($line,"=")));
				$val = trim(substr($line,strpos($line,"=")+3));
				$val = substr($val,0,strlen($val)-1);
				$return[$key] = $val;
			}
		}
		return $return;
	}
}

class Mt2
{
#! private
	private $mysql_link;
	private $debug = false;
	private $dry = false;
	private $itemproto;
	private $account = "account";
	private $common = "common";
	private $player = "player";
	private $db_host = "localhost";
	private $db_user = "root";
	private $db_passwd = "";
	private $from_header = "idontuseaconfigfile@crapcode.lifefight.de";
	private $mail_subject = "mt2api-driven server";
	private $mail_content = "<html><body>Hallo %username%<br/>Bitte klicke auf den folgenden Link, um deinen Account zu aktivieren: %link%<br/>MfG dein Servername-Team</body></html>";
	private $mail_linktxt = "Account aktivieren";
	private $mail_hash_paramname = "hash";
	private $mail_acc_paramname = "acc_id";
	private $mail_activationfilename = "/activate.php";
	private $locale;
	private $mods = array();
	private $verifyMods = false;
	private $itemshop;
	
	public function __construct($host="localhost",$user="root",$pw="")#! Konstruiert die API
	{
		echo "<!--\nMt2 API by xenor\nVisit us at http://www.xtreamyt2.org\nVersion: ".MT2API_VERSION."\nBUILD: ".MT2API_BUILD."\n-->\n";
		$this->itemproto = include("itemproto.api.php");
		$this->locale = include("locale.api.php");
		#! Mods einbinden und erlauben die Mt2-Klasse zu verändern
		$cwd = getcwd();#! Liest das aktuelle Verzeichnis aus
		if(file_exists($cwd."/mods") && is_dir($cwd."/mods"))#! Falls das Verzeichnis existiert
		{
			$files = scandir($cwd."/mods");#! Lies jede Datei aus
			$api = &$this;
			foreach($files as $val)#! Für alle Dateien...
			{
				$filename = $cwd."/mods/".$val;#! Dateiname
				if(is_file($filename))#! Wenns eine Datei ist
				{
					include("mods/".$val);#! Binde sie ein
				}
			}
			$api = null;
		}
		
		$this->db_host = $host;
		$this->db_user = $user;
		$this->db_passwd = $pw;
	}
	
	public function __init()
	{
		$this->mysql_link = mysql_connect($this->db_host,$this->db_user,$this->db_passwd);
		if($this->mysql_link)
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	
	#! functions
	
	public function say($a)#! Gibt den Text aus
	{
		echo str_replace("\n","<br />",str_replace(" ","&nbsp;",print_r($a,true))),"\r\n";
	}
	
	private function log($str)#! Gibt als Log aus
	{
		if($this->debug === true)
		{
			$this->say($str);
		}
	}
	
	private function checkItem($data,&$lager)#! Sub Routine zum erstellen des Inventar-Arrays
	{
		$lager[$data->pos] = $data->vnum;
		$size = $this->itemproto[$data->vnum]["size"];
		if($size > 1)
		{
			$lager[($data->pos+5)] = $data->vnum;
		}
		if($size > 2)
		{
			$lager[($data->pos+10)] = $data->vnum;
		}
		return $lager;
	}
	
	private function getFreePosition($window,$owner_id,$size)#! Gibt die nächste freie Inventar Position für die angegebene Größe zurück
	{
		$lager = array();
		$sql = "SELECT * FROM ".$this->player.".item WHERE window = '".$window."' AND owner_id = '".$owner_id."'";
		$q = mysql_query($sql);
		if(mysql_num_rows($q) == 0)
		{
			return "0";
		}
		while($data = mysql_fetch_object($q))
		{
			$lager = $this->checkItem($data,$lager);
		}
		$end=50;
		$end-=($size*5);
		for($i=0;$i<$end;$i++)
		{
			if(!isset($lager[$i]))
			{
				if($size == 1)
				{
					return $i;
				}
				elseif($size == 2)
				{
					if(!isset($lager[($i+5)]))
					{
						return $i;
					}
				}
				elseif($size == 3)
				{
					if(!isset($lager[($i+5)]) && !isset($lager[($i+10)]))
					{
						return $i;
					}
				}
			}
		}
		return false;
	}

	private function checkEmailFormat($email)#! Überprüft das Format der E-Mail-Adresse
	{
		if(strpos($email,".") === false) return false;
		if(strpos($email,"@") === false) return false;
		return true;
	}
	
	private function generateValidationHash($email)
	{
		$hash1 = md5($email);
		$hash2 = md5(microtime());
		$hash3 = $hash1.$hash2.sha1($hash1.$hash2);
		return $hash3;
	}
	
	private function sendMail($from,$to,$subject,$content)
	{
		return mail($to,$subject,$content,'From: '.$from."\r\n".'Content-Type: text/html; charset="UTF-8"');
	}
	
	private function encrypt($content,$key)
	{
		$result = "";
		for($i = 0; $i < strlen($content); $i++)
		{
			$char = $content{$i};
			$keychar = substr($key, ($i % strlen($key))-1, 1);
			$char = chr(ord($char) + ord($keychar));
			$result .= $char;
		}
		return base64_encode($result);
	}
	
	private function decrypt($content,$key)
	{
		$result = "";
		$content = base64_decode($content);
		for($i = 0; $i < strlen($content); $i++)
		{
			$char = $content{$i};
			$keychar = substr($key, ($i % strlen($key))-1, 1);
			$char = chr(ord($char) - ord($keychar));
			$result .= $char;
		}
		return $result;
	}
	
	#! public functions
	
	public function setDebug($bool)#! Setzt den Debug-Modus der API auf true oder false
	{
		if($bool === true || $bool === false)
		{
			$this->log("Going to debug mode...");
			$this->debug = $bool;
			return true;
		}
		else
		{
			return false;
		}
	}
	
	public function setDry($bool)#! Setzt den Trockendock-Modus der API auf true oder false
	{
		if($bool === true || $bool === false)
		{
			$this->log("Aktiviere Trockendock-Modus...");
			$this->dry = $bool;
			return true;
		}
		else
		{
			return false;
		}
	}
	
	public function updateGMIP($playerName,$ip)#! Aktualisiert die GM Rechte eines GMs
	{
		$sql = "UPDATE ".$this->common.".gmlist SET mContactIP = '".$ip."' WHERE mName = ".$playerName." LIMIT 1";
		$this->log("MySQL Query: ".$sql);
		if($this->dry == false)
		{
			if(mysql_query($sql))
			{
				return true;
			}
			else
			{
				throw new Exception($this->locale->ERR_MYSQL.": ".mysql_error());
				return false;
			}
		}
	}
	
	public function makeGM($playerID,$ip,$auth,$prefix = "")#! Macht den Charakter mit der gegebenen Player ID zum GM der gegebenen Stufe
	{
		$sql = "SELECT name, account_id FROM ".$this->player.".player WHERE id = '".$playerID."'";
		$q = mysql_query($sql);
		$player = mysql_fetch_object($q);
		$this->log($player);
		if($prefix != "")
		{
			$sql = "UPDATE ".$this->player.".player SET name = '".$prefix.$player->name."' WHERE id = '".$playerID."' LIMIT 1";
			$this->log("MySQL Query: " . $sql); if($this->dry == false) mysql_query($sql);
		}
		$sql = "SELECT login FROM ".$this->account.".account WHERE id= '".$player->account_id."' LIMIT 1";
		$q = mysql_query($sql);
		$tmp = mysql_fetch_object($q);
		if($tmp == false) die("Datenbankfehler #1");
		$login = $tmp->login;
		$tmp = null;
		$sql = "INSERT INTO ".$this->common.".gmlist (`mID`,`mAccount`,`mName`,`mContactIP`,`mServerIP`,`mAuthority`) VALUES (NULL, '".$login."','".$player->name."','".$ip."','ALL','".$auth."');";
		$this->log("MySQL Query: " . $sql);
		if($this->dry == false)
		$q = mysql_query($sql);
		if($q)
		{
			return true;
		}
		else
		{
			throw new Exception($this->locale->ERR_MYSQL.": ".mysql_error());
			return false;
		}
	}
	
	public function addAccount($a,$check = false)#! Fügt einen Account hinzu und gibt bei Erfolg die Account ID zurück
	{
		$this->log($a);
		$fields = "";
		$values = "";
		foreach($a as $key => $val)
		{
			if($check === true) $val = mysql_real_escape_string($val);
			$fields .= "`".$key."`, ";
			if($key == "password")
			{
				$values .= "PASSWORD('".$val."'), ";
			}
			else
			{
				$values .= "'".$val."', ";
			}
		}
		$fields = substr($fields, 0,strlen($fields) - 2);
		$values = substr($values, 0,strlen($values) - 2);
		$sql = "INSERT INTO ".$this->account.".account (".$fields.") VALUES (".$values.");";
		$this->log("MySQL Query: ".$sql);
		if($this->dry == false)
		{
			$q = mysql_query($sql);
			if($q)
			{
				return $hash;
			}
			else
			{
				throw new Exception($this->locale->ERR_MYSQL.": ".mysql_error());
				return false;
			}
		}
		return mysql_insert_id();
	}
	
	public function addItem($accountID,$vnum,$a = array())#! Fügt dem gegebenen Spieler bzw. Account ein Item hinzu und gibt den Erfolg zurück				
	{
		if(is_array($vnum))
		{
			$vnum = array_rand($vnum);
		}
		$accountID = intval($accountID);
		$a["owner_id"] = $accountID;
		$a["vnum"] = $vnum;
		if(empty($a["window"]))
		{
			$a["window"] = "MALL";
		}
		if(empty($a["count"])) $a["count"] = "1";
		$sql = "SELECT type FROM ".$this->player.".item_proto WHERE vnum = '".$vnum."'";
		$q = mysql_query($sql);
		if(mysql_num_rows($q) == 1)
		{
		
			$size = $this->itemproto[$vnum]["size"];
			$pos = $this->getFreePosition($a["window"],$a["owner_id"],$size);
			
			if($pos === false)
			{
				throw new Exception($this->locale->ERR_SAFEBOX_FULL);
				return false;
			}
			
			$a["pos"] = $pos;
		
			$fields = "";
			$values = "";
			foreach($a as $key => $val)
			{
				$fields .= "`".$key."`, ";
				$values .= "'".$val."', ";
			}
			$fields = substr($fields, 0,strlen($fields) - 2);
			$values = substr($values, 0,strlen($values) - 2);
			$sql = "INSERT INTO ".$this->player.".item (".$fields.") VALUES (".$values.");";
			$this->log("MySQL Query: ".$sql);
			if($this->dry == false)
			{
				$q = mysql_query($sql);
				if($q)
				{
					return true;
				}
				else
				{
					throw new Exception($this->locale->ERR_MYSQL.": ".mysql_error());
					return false;
				}
			}
			return true;
		}
		else
		{
			throw new Exception($this->locale->ERR_ITEM_NOT_FOUND);
			return false;
		}
		return true;
	}
	
	public function isBanned($accountID)#! Gibt zurück ob der gegebene Account gebannt ist
	{
		$sql = "SELECT * FROM ".$this->account.".account WHERE id = '".$account_id."'";
		$q = mysql_query($sql);
		if(mysql_num_rows($q))
		{
			throw new Exception($this->locale->ERR_ACCOUNT_NOT_FOUND);
			return false; #! Account nicht gefunden
		}
		$data = mysql_fetch_object($q);
		if($data["status"] == "OK") return false;
		if($data["status"] == "BLOCK") return true;
	}
	
	public function banAccount($accountID)#! Bannt den gegebenen Account und gibt den Erfolg zurück
	{
		$sql = "UPDATEUPDATE ".$this->account.".account SET status = 'BLOCK' WHERE id = '".$accountID."'";
		$this->log("MySQL Query: ".$sql);
		if($this->dry == false)
		{
			$q = mysql_query($sql);
			if($q)
			{
				return true;
			}
			else
			{
				throw new Exception($this->locale->ERR_MYSQL.": ".mysql_error());
				return false;
			}
		}
		else
		{
			#! Erfolgreich gebannt
			return true;
		}
	}
	
	public function unbanAccount($accountID)#! Entbannt den gegebenen Account und gibt den Erfolg zurück
	{
		$sql = "UPDATE ".$this->account.".account SET status = 'OK' WHERE id = '".$accountID."'";
		$this->log("MySQL Query: ".$sql);
		if($this->dry == false)
		{
			$q = mysql_query($sql);
			if($q)
			{
				return true;
			}
			else
			{
				throw new Exception($this->locale->ERR_MYSQL.": ".mysql_error());
				return false;
			}
		}
		else
		{
			#! Erfolreich entbannt
			return true;
		}
	}
	
	public function debugCharacter($playerID,$a)#! Entbuggt den Charakter mit der gegebenen ID, es muss ein array mit den daten übergeben werden
	{
		$x = $a["x"];
		$y = $a["y"];
		$map = $a["map"];
		$sql = "UPDATE ".$this->player.".player SET x = '".$x."', y = '".$y."', map_index = '".$map."', exit_x = '".$x."', exit_y = '".$y."', exit_map_index = '".$map."' WHERE id = '".$playerID."'";
		$this->log("MySQL Query: ".$sql);
		if($this->dry == false)
		{
			$q = mysql_query($sql);
			if($q)
			{
				return true;
			}
			else
			{
				throw new Exception($this->locale->ERR_MYSQL.": ".mysql_error());
				return false;
			}
		}
		else
		{
			return true;#! Erfolgreich entbuggt
		}
	}
	
	public function loadConfigString($str)
	{
		$tmp = parse_ini_string($str);#! Parst den String als config
		foreach($tmp as $key => $val)
		{
			$this->$key = $val;#! Setzt den Key in der Config
		}
	}
	
	public function loadConfigFile($configFileName = "config.xml")#! Lädt die in einem Config-File gespeicherten
	{
		$rootnode = simplexml_load_file($configFileName);
		foreach($rootnode->children() as $key => $val)
		{
			if($key == "option")
			{
				$this->log("found option: ".$val->name." => ".$val->value);
			}
			elseif($key == "itemshop")
			{
				$this->log("found itemshop; parsing...");
				$itemshop = &$val;
				unset($val);
				$cat_id = 0;
				foreach($itemshop->children() as $category)
				{
					$cat_id++;
					$cat_name = (string)$category->attributes()->name[0];
					$items = array();
					foreach($category->children() as $item)
					{
						$key = $item->attributes();
						$key = (int)(string)$key->id[0];
						$items[$key] = (object) array(
							"id" => (string)$key,
							"name" => (string)$item->{'name'},
							"picture" => (string)$item->{'picture'},
							"vnum" => (string)$item->{'vnum'},
							"money" => (string)$item->{'money'},
							"desc" => (string)$item->{'desc'},
						);
					}
					$this->itemshop[$cat_id] = (object) array(
						"name" => $cat_name,
						"items" => $items,
					);
				}
			}
		}
		
		@mysql_close($this->mysql_link);#! Schließt die aktuelle Datenbankverbindung
		$this->mysql_link = mysql_connect(
								$this->db_host,
								$this->db_user,
								$this->db_passwd
							);#! Öffnet die Verbindung neu, da Daten geändert wurden
		return true;
	}
	
	public function checkForUpdates()#! Sucht nach Updates und gibt den Erfolg zurück
	{
		$build = constant("MT2API_BUILD");
		$content = file_get_contents("http://crapcode.lifefight.de/mt2-api/LATEST");
		$cmd = explode("\n",$content);
		$newbuild = $cmd[0];
		if($newbuild == $build)
		{
			return false;#! der gleiche build
		}
		elseif($newbuild > $build)
		{
			$version = $cmd[1];
			return intval($newbuild);#! Gibt den neusten Build zurück
		}
		else
		{
			return false;#! Neuer Build < als der aktuelle
		}
	}
	
	public function installUpdate($build)#! Updated die API auf die gegebene Version
	{
		$files = explode("\n",file_get_contents("http://crapcode.lifefight.de/mt2-api/$build/FILES"));
		$this->log("Updating from Build #".MT2API_BUILD." to Build #$build<br />\n");
		foreach($files as $key => $tmp)
		{
			$data = explode('!',$tmp);
			$md5 = md5_file(getcwd()."/".$data[0]);
			$this->log("Local File Hash (".$data[0]."): ".$md5."<br />\n");
			if($data[1] != $md5)
			{
				$this->log("Updating File ".$data[0]."...");
				$content = "<?php\n".file_get_contents("http://crapcode.lifefight.de/mt2-api/$build/".$data[0].".raw")."\n?>";
				file_put_contents(getcwd()."/".$data[0],$content);#!Updatet die Datei
				$this->log("Done.<br />\n");
			}
			else
			{
				$this->log("Skipping File ".$data[0].": Same MD5 ($md5)<br />\n");
			}
		}
		$this->log("Update zu Build #".$build." ausgeführt.<br />\n");
		return true;#! immer true zurückgeben
	}
	
	public function checkAccountFormat($account)#! Prüft ob der Accountname den Anforderungen entspricht
	{
		if(strlen($account) < 3 || strlen($account) > 20) return false; else return true;
	}
	
	public function checkAccountName($account,$check = true)#! Prüft ob der Login bereits vergeben ist und gibt den true zurück wenn er frei ist
	{
		if($check == true)
		{
			$account = mysql_real_escape_string($account);#! Maskiert die Eingabe
		}
		$sql = "SELECT login FROM ".$this->account.".account WHERE login = '".$account."' LIMIT 1";
		if(mysql_num_rows(mysql_query($sql)) == 0)
		{
			return true;#! Account name ist frei
		}
		else
		{
			#! throw new Exception($this->locale->ERR_GIVEN_LOGIN);
			return false;
		}
	}
	
	public function checkPw1($passwd)#! Prüft ob das gegebene Passwort den Anforderungen entspricht
	{
		if(strlen($passwd) < 6)#! kürzer als 6 zeichen
		{
			#! throw new Exception($this->locale->ERR_PW_TOO_SHORT);
			return false;
		}
		if(strlen($passwd) > 24)#! länger als 16 zeichen
		{
			#! throw new Exception($this->locale->ERR_PW_TOO_LONG);
			return false;
		}
		return true;#! Passwort entspricht den Anforderungen
	}
	
	public function checkPw2($pw1, $pw2)#! Prüft ob die Passwörter übereinstimmen
	{
		if($pw1 != $pw2)
		{
			#! throw new Exception($this->locale->ERR_PWS_NOT_MATCH);
			return false;
		}
		elseif($pw1 == $pw2)
		{
			return true;#! Passwörter stimmen überein
		}
	}
	
	public function checkEMail($email,$check = true)#! Prüft ob die E-Mail-Adresse bereits vergeben ist
	{
		if(!$this->checkEmailFormat($email))#! Überprüft das Format der Adresse
		{
			return false;
		}
		if($check == true)
		{
			$email = mysql_real_escape_string($email);#! Maskiert die Eingabe
		}
		$sql = "SELECT login FROM ".$this->account.".account WHERE email = '".$email."' LIMIT 1";
		if(mysql_num_rows(mysql_query($sql)) == 0)
		{
			return true;#! E-Mail ist frei
		}
		else
		{
			#! throw new Exception($this->locale->ERR_GIVEN_EMAIL);
			return false;
		}
	}
	
	public function sendValidationEmail($email,$accID,$check = true)#! Schickt eine Registrierungs-E-Mail an die gegebene Adresse
	{
		$hash = $this->generateValidationHash($email);#! Generiert einen neuen Hash
		$activate = $this->mail_activationfilename."?".$this->mail_hash_paramname."=".$hash."&".$this->mail_acc_paramname."=".$accID;
		$link = '<a href="http://'.dirname($_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']).$activate.'">'.$this->mail_linktxt.'</a>';
		$this->log("Using Hash: $hash<br/>\n");#! Logge das
		
		if($check == true)
		{
			$accID = intval($accID);#! Maskiert die Eingabe
		}
		$sql = "SELECT login FROM ".$this->account.".account WHERE id = '".$accID."' LIMIT 1";
		$q = mysql_query($sql);
		if(mysql_num_rows($q) == 0)
		{
			throw new Exception($this->locale->ERR_ACCOUNT_NOT_FOUND);
		}
		$data = mysql_fetch_object($q);
		$login = $data->login;#! Das war der Login
		
		$this->sendMail(
			$this->from_header,
			$email,
			$this->mail_subject,
			str_replace("%link%",$link,str_replace("%username%",$login,$this->mail_content))
		);#! Sendet die Mail
		
		$sql = "UPDATE ".$this->account.".account SET activation_hash = '".$hash."' WHERE id = '".$accID."'";
		if($this->dry == false)#! Schreibt den Hash in die Datenbank
		{
			$q = mysql_query($sql);
			if($q)
			{
				return $hash;
			}
			else
			{
				throw new Exception($this->locale->ERR_MYSQL.": ".mysql_error());
				return false;
			}
		}
	}
	
	public function checkHash($accID, $hash)#! Überprüft den gegebenen Hash
	{
		$accID = intval($accID);#! Maskiere die AccountID
		$sql = "SELECT activation_hash FROM ".$this->account.".account WHERE id = '".$accID."' LIMIT 1";
		$q = mysql_query($sql);
		if(mysql_num_rows($q) == 0)
		{
			throw new Exception($this->locale->ERR_ACCOUNT_NOT_FOUND);
			return false;
		}
		$data = mysql_fetch_object($q);
		if($data->activation_hash == $hash)
		{
			return true;#! Wenn die Hashs übereinstimmen
		}
		else
		{
			throw new Exception($this->locale->ERR_WRONG_HASH);
			return false;
		}
	}
	
	public function activateAccount($accID,$check = true)#! Aktiviert den gegebenen Account
	{
		if($check == true)
		{
			$accID = intval($accID);#! Maskiert die Eingabe
		}
		$sql = "UPDATE ".$this->account.".account SET status = 'OK' WHERE id = '".$accID."'";
		if($this->dry == false)
		{
			$q = mysql_query($sql);
			if($q)
			{
				return true;
			}
			else
			{
				throw new Exception($this->locale->ERR_MYSQL.": ".mysql_error());
				return false;
			}
		}
	}
	
	public function deactivateAccount($accID,$check = true)#! Deaktiviert den gegebenen Account
	{
		if($check == true)
		{
			$accID = intval($accID);#! Maskiert die Eingabe
		}
		$sql = "UPDATE ".$this->account.".account SET status = 'NOTAVAIL' WHERE id = '".$accID."'";
		if($this->dry == false)
		{
			$q = mysql_query($sql);
			if($q)
			{
				return true;
			}
			else
			{
				throw new Exception($this->locale->ERR_MYSQL.": ".mysql_error());
				return false;
			}
		}
	}
	
	public function checkLoginData($login,$passwd)#! Überprüft ob der Account existiert und so
	{
		$login = mysql_real_escape_string($login);
		$passwd = mysql_real_escape_string($passwd);
		$sql = "SELECT * FROM ".$this->account.".account WHERE login = '".$login."' AND password = PASSWORD('".$passwd."') LIMIT 1";
		$q = mysql_query($sql);
		if(mysql_num_rows($q) == 0)
		{
			#!throw new Exception($this->locale->ERR_WRONG_USER_PW);
			return false;
		}
		else
		{
			return mysql_fetch_object($q);
		}
	}
	
	public function getData($accID)#!Liefert die Accountdaten der angegebenen ID
	{
		$sql = "SELECT * FROM ".$this->account.".account WHERE id = '".intval($accID)."'";
		$q = mysql_query($sql);
		if(mysql_num_rows($q) != 1) return false;
		return mysql_fetch_object($q);
	}
	
	public function __call($name,$args)#!Intern: checkt ob ein Modul diesen Methodennamen benutzt
	{
		foreach($this->mods as $key => $val)
		{
			if(method_exists($val,$name))#! Prüft ob die Funktion in den Mods existiert
			{
				return $val->$name($args);#! Ruft die Funktion auf
			}
		}
		$this->log("Die Funktion $name wurde nicht gefunden. Erweitere die API doch einfach! Gug bei e*pvp wie man Mods schreibt!");
		return null;
	}
	
	public function registerModule($mod,$data)#!Intern: registriert ein Modul bei der API
	{
		/*if($this->verifyMods === true)#! Sollen Mods verifiziert werden?
		{
			$realmd5 = sha1(md5(gettype($mod)));#! Errechne den MD5-Hash
			if($data->hash == $realmd5)
			{
				$this->mods[] = &$mod;#! Installiere die Mod
				return true;
			}
			else
			{
				throw new Exception("Die Verifizierung des Moduls ".getclass($mod)." schlug fehl.");
				return false;
			}
		}
		else*/
		{
			$this->mods[] = &$mod;#! Installiere die Mod
			return true;
		}
	}
	
	public function getHighscore()#! Liefert den Server highscore sortiert bei level und exp
	{
		$return = array();
		$sql = "SELECT name, level, exp FROM ".$this->player.".player ORDER BY level DESC, exp DESC";
		$q = mysql_query($sql);#! Zieht den Highscore vom Server
		$i = 0;
		while($data = mysql_fetch_object($q))
		{
			$i++;#! Platz wird um eins erhöht
			$return[$i] = (object) array(
				"place" => $i,
				"name" => $data->name,
				"level" => $data->level,
				"exp" => $data->exp,
			);
		}
		return $return;
	}
	
	public function update($accID,$table,$a)#! Updatet den angegeben User
	{
		$str = "UPDATE ".$table." SET ";
		foreach($a as $key => $val)
		{
			$str .= "`".$key."` = ".$val.", ";
		}
		$str = substr($str,0,strlen($str)-2)." WHERE id = '".intval($accID)."'";
		$this->log("mysql:".$str);
		if($this->dry == false)
		{
			$q = mysql_query($str);
			if($q)
			{
				return true;
			}
			else
			{
				throw new Exception($this->locale->ERR_MYSQL.": ".mysql_error());
				return false;
			}
		}
	}
	
	public function isIE()#! Gibt zurück ob der Besucher der Webseite den Internet Explorer benutzt
	{
		if(strpos($_SERVER["HTTP_USER_AGENT"],"MSIE") !== false)
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	
	public function itemshop_getCats()#! Gibt ein Array der Itemshop Kategorien zurück
	{
		$cats = array();
		foreach($this->itemshop as $key => $val)
		{
			$cats[$key] = $val->name;
		}
		return $cats;
	}
	
	public function itemshop_getItems($cat)#! Gibt ein Array mit allen Items der Kategorie zurück
	{
		if(isset($this->itemshop[$cat]))
		{
			return $this->itemshop[$cat]->items;
		}
		else
		{
			return array();
		}
	}
	
	public function itemshop_itemdata($cat,$id)
	{
		return $this->itemshop[$cat]->items[$id];
	}
	
	public function itemshop_get()#!Gibt den Itemshop zurück; Für Profis
	{
		return $this->itemshop;
	}
}
?>