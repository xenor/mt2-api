<?php
class modOpfer
{
	public function modTest($a)
	{
		print_r($a);
		return true;
	}
}

$mod = new modOpfer();
$data = (object) array(
	"author" => "xenor",
	"hash" => "",
);

var_dump($api->registerModule($mod,$data));
?>