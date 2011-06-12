<?php
$host = "localhost";
$user = "root";
$passwd = "";

mysql_connect($host,$user,$passwd);

$lager = array();

$sql = "SELECT * FROM player.item_proto WHERE 1 = 1";
$q = mysql_query($sql) or die(mysql_error());
while($data = mysql_fetch_object($q))
{
	$lager[$data->vnum] = array("size" => $data->size);
}
$code = var_export($lager,true);
file_put_contents("itemproto.api.php","<?php\n\$itemproto=".$code.";\nreturn \$itemproto;\n?>");
?>