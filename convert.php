<?php

require_once 'functions.php';
require_once 'classes/config.php';
require_once 'classes/database.php';

$root = dirname(__FILE__);
$config = new Config();
$database = new Database($config->database);

$oldresult = $database->query("SELECT address, netmask, parent, description FROM ipold");

$database->query("DELETE FROM ip");

foreach ($oldresult as $row) {
	$bits = 0;
	for ($i=0; $i<strlen($row['netmask']); $i++) {
		$bits += substr_count(decbin(hexdec($row['netmask'][$i])), '1');
	}
	if (preg_match('/^000000000000000000000000/', $row['address']))
		$bits -= 96;
	echo ip2address($row['address']).'/'.$bits;
	if ($row['parent']==0)
		$parent = '00000000000000000000000000000000';
	else
		$parent = $database->query("SELECT address FROM ipold WHERE id=".$row['parent']);
	$database->query("INSERT INTO ip (address, bits, parent, description) VALUES('".
					 $database->escape(strtolower($row['address']))."', ".
					 $bits.", '".
					 $database->escape(strtolower($parent))."', '".
					 $database->escape($row['description'])."')");
	echo "\n";
}

$database->close();


?>
