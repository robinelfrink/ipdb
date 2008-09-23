<?php

require_once 'functions.php';
require_once 'classes/config.php';
require_once 'classes/database.php';

$root = dirname(__FILE__);
$config = new Config();
$database = new Database($config->database);

$oldresult = $database->query("SELECT id, address, netmask, parent, description FROM ipold");

$database->query("DELETE FROM ip");

foreach ($oldresult as $row) {
	$bits = 0;
	for ($i=0; $i<strlen($row['netmask']); $i++) {
		$bits += substr_count(decbin(hexdec($row['netmask'][$i])), '1');
	}
	echo ip2address($row['address']).'/'.$bits;
	$database->query("INSERT INTO ip (id, address, bits, parent, description) VALUES(".
					 $database->escape($row['id']).", '".
					 $database->escape(strtolower($row['address']))."', ".
					 $bits.", ".
					 $database->escape($row['parent']).", '".
					 $database->escape($row['description'])."')");
	echo "\n";
}

$database->close();


?>
