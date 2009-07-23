<?php

require_once 'functions.php';
require_once 'classes/config.php';
require_once 'classes/database.php';
require_once 'classes/session.php';

$root = dirname(__FILE__);
$config = new Config();
$session = new Session($config->session);
$database = new Database($config->database);

$oldresult = $database->query("SELECT * FROM ipold ORDER BY address, netmask DESC");

$database->query("DELETE FROM ".$config->database['prefix']."ip");
$database->query("DELETE FROM ".$config->database['prefix']."extrafields");

foreach ($oldresult as $row) {
	$bits = 0;
	for ($i=0; $i<strlen($row['netmask']); $i++) {
		$bits += substr_count(decbin(hexdec($row['netmask'][$i])), '1');
	}
	echo ip2address($row['address']).'/'.$bits;
	$node = $database->addNode(strtolower($row['address']), $bits, $row['description']);
	if ($row['debtor']) {
		echo ', '.$row['debtor'];
		$database->setField('customer', $node, $row['debtor']);
	}
	if ($row['abo']) {
		echo ', '.$row['abonr'];
		$database->setField('abonr', $node, $row['abo']);
	}
	echo "\n";
}

$database->close();


?>
