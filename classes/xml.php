<?php

/*
Copyright 2009 Introweb Nederland bv
Author: Robin Elfrink <robin@15augustus.nl>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

$Id$
*/


class XML {


	public function __construct($data) {
		global $config, $database, $session;
		libxml_use_internal_errors(true);
		libxml_clear_errors();
		if ($config->debug['debug'])
			error_log("Incoming request: \n".$data);
		if (!($xml = @simplexml_load_string($data))) {
			$error = 'XML Parser Error';
			$details = $this->parse_xml_errors(libxml_get_errors(), $data);
			$this->fatal($error, $details);
			return;
		}

		if ($xml->error) {
			$error = html_entity_decode((string) $xml->error);
			$this->fatal($error);
			return;
		}

		if (($details = $this->validate($data))!==true) {
			$this->fatal('XML Parser Error', $details);
			return;
		}

		$attributes = $xml->attributes();
		$session = new Session($config->session);
		request('username', (string)$xml->attributes()->username, true);
		request('password', (string)$xml->attributes()->password, true);
		request('action', 'login', true);
		if ($session->error || !$session->authenticate()) {
			$this->fatal($session->error);
			return;
		}

		$pools = array();
		foreach ($config->pools as $name=>$value)
			if (preg_match('/^pool_([a-z0-9_]+)$/', $name, $matches))
				$pools[$matches[1]] = preg_split('/,\s*/', $value);

		$result = '';
		$ok = false;
		foreach ($xml->children() as $name=>$request) {
			$id = (int)$request->attributes()->id;
			switch ($request->getName()) {
			  case 'create':
				  if ($request->tableitem) {
					  $table = (string)$request->tableitem->table;
					  $key = (string)$request->tableitem->key;
					  $description = (string)$request->tableitem->description;
					  $columns = array();
					  if ($request->tableitem->column)
						  foreach ($request->tableitem->column as $column)
							  $columns[(string)$column->column] = (string)$column->value;
					  if (!$database->addExtra($table, $key, $description, '', $columns)) {
						  $result .= $this->error($name, $id, ($database->error ? $database->error : 'Unknown error in addExtra'));
						  break;
					  }
					  $result .= $this->result($name, $id, '
			<table>'.$table.'</table>
			<key>'.$key.'</key>');
					  $ok = true;
				  } else if ($request->user) {
					  $username = (string)$request->user->username;
					  $password = ($request->user->password ? (string)$request->user->password : randstr(6));
					  $description = ($request->user-description ? (string)$request->user-description : '');
					  $columns = array('password'=>$password);
					  if ($request->user->maxup && $request->user->maxdown) {
						  $columns['maxup'] = (int)$request->user->maxup;
						  $columns['maxdown'] = (int)$request->user->maxdown;
					  }
					  if (!$database->addExtra('radius', $username, $description, '', $columns)) {
						  $result .= $this->error($name, $id,
												  ($database->error ? $database->error : 'Unknown error in addExtra'));
						  break;
					  }
					  $result .= $this->result($name, $id, '
			<username>'.$username.'</username>
			<password>'.$password.'</password>');
					  $ok = true;
				  } else if ($request->network) {
					  $pool = strtolower((string)$request->network->pool);
					  $bits = (string)$request->network->bits;
					  $customer = (string)$request->network->customer;
					  $description = (string)$request->network->description;
					  $username = (string)$request->network->username;
					  if (!isset($pools[$pool])) {
						  $result .= $this->error($name, $id,
												  'Unknown address pool '.$pool);
						  break;
					  }
					  if (!$database->getExtra('radius', (string)$request->network->username)) {
						  $result .= $this->error($name, $id,
												  'User '.(string)$request->network->username.' does not exist');
						  break;
					  }
					  $blocks = array();
					  foreach ($pools[$pool] as $block) {
						  $address = preg_replace('/\/.*/', '', $block);
						  $blockbits = preg_replace('/.*\//', '', $block);
						  if (preg_match('/\./', $address))
							  $blockbits += 96;
						  $ip = address2ip($address);
						  if ($address!=$ip)
							  $blocks[] = array('address'=>$ip,
												'bits'=>$blockbits);
					  }
					  if (count($blocks)<1) {
						  $result .= $this->error($name, $id,
												  'No usable network blocks in pool '.$pool);
						  break;
					  }
					  if ($request->network->address) {
						  $aok = false;
						  foreach ($blocks as $block)
							  if ((strcmp($block['address'], address2ip((string)$request->network->address))<=0) &&
								  (strcmp(broadcast($block['address'], $block['bits']),
										  broadcast(address2ip((string)$request->network->address), $bits))>=0))
								  $aok = true;
						  if (!$aok) {
							  $result .= $this->error($name, $id,
													  'Address does not fit in pool '.$pool);
							  break;
						  }
						  $free = array('address'=>address2ip((string)$request->network->address),
										'bits'=>$bits);
					  } else if (!($free = $database->findFree($blocks, $bits))) {
						  $result .= $this->error($name, $id,
												  $database->error ? $database->error : 'No free network block in pool '.$pool);
						  break;
					  }
					  if (!$database->addNode($free['address'], $bits, $description)) {
						  $result .= $this->error($name, $id,
												  $database->error ? $database->error : 'Unknown error in addNode');
						  break;
					  }
					  if ($node = $database->findAddress($free['address'], $bits)) {
						  $database->setField('customer', $node['id'], $customer);
						  $database->setItem('radius', $username, $node['id']);
					  }
					  $result .= $this->result($name, $id, '
			<network>'.showip($free['address'], $bits).'</network>');
					  $ok = true;
				  } else {
					  $result .= $this->error($name, $id, 'Unknown request');
				  }
				  break;
			  case 'remove':
				  if ($request->user) {
					  if (!$database->deleteExtra('radius', (string)$request->user)) {
						  $result .= $this->error($name, $id,
												  $database->error ? $database->error : 'Unknown error in deleteExtra');
						  break;
					  }
					  $result .= $this->result($name, $id, '
			<user>'.(string)$request->user.'</user>');
					  $ok = true;
				  } else if ($request->network) {
					  $address = preg_replace('/\/.*/', '', (string)$request->network);
					  $bits = preg_replace('/.*\//', '', (string)$request->network);
					  if (preg_match('/\./', $address))
						  $bits += 96;
					  $ip = address2ip($address);
					  if (!($node = $database->findAddress($ip, $bits))) {
						  $result .= $this->error($name, $id,
												  $database->error ? $database->error : 'Address not found');
						  break;
					  }
					  if (!$database->deleteNode($node['id'])) {
						  $result .= $this->error($name, $id,
												  $database->error ? $database->error : 'Unknown error in deleteNode');
						  break;
					  }
					  $result .= $this->result($name, $id, '
			<network>'.(string)$request->network.'</network>');
					  $ok = true;
				  }
				  break;
			  case 'get':
				  $ip = address2ip((string)$request->address);
				  $bits = (string)$request->bits + (preg_match('/\./', (string)$request->address) ? 96 : 0);
				  if (!($node = $database->findAddress($ip, $bits))) {
					  $result .= $this->error($name, $id,
											  $database->error ? $database->error : 'Address not found');
					  break;
				  }
				  $xml = '<address>'.ip2address($node['address']).'</address>
<bits>'.(strcmp('00000000000000000000000100000000', $ip)>0 ? $node['bits']-96 : $node['bits']).'</bits>
<broadcast>'.ip2address(broadcast($node['address'], $node['bits'])).'</broadcast>
<description>'.htmlentities($node['description']).'</description>';
				  foreach ($config->extrafields as $field=>$details)
					  if ($details['inoverview'] && ($extra = $database->getField($field, $node['id'])))
						  $xml .= '
<'.$field.'>'.$extra.'</'.$field.'>';
				  foreach ($config->extratables as $table=>$details)
					  if ($item = $database->getItem($table, $node['id']))
						  $xml .= '
<'.$table.'>'.$item['item'].'</'.$table.'>';
				  if (((string)$request->children) &&
					  $database->hasChildren($node['id'])) {
					  $children = $database->getTree($node['id']);
					  $xml .= '
<children>';
					  foreach ($children as $child) {
						  $xml .= '
	<child>
		<address>'.ip2address($child['address']).'</address>
		<bits>'.(strcmp('00000000000000000000000100000000', $child['address'])>0 ? $child['bits']-96 : $child['bits']).'</bits>
		<broadcast>'.ip2address(broadcast($child['address'], $child['bits'])).'</broadcast>
		<description>'.htmlentities($child['description']).'</description>';
						  foreach ($config->extrafields as $field=>$details)
							  if ($details['inoverview'] && ($extra = $database->getField($field, $child['id'])))
								  $xml .= '
		<'.$field.'>'.$extra.'</'.$field.'>';
						  foreach ($config->extratables as $table=>$details)
							  if ($item = $database->getItem($table, $child['id']))
								  $xml .= '
		<'.$table.'>'.$item['item'].'</'.$table.'>';
						  $xml .= '
	</child>';
					  }
					  $xml .= '
</children>';
				  }
				  $result .= $this->result($name, $id, $xml);
				  $ok = true;
				  break;
			  default:
				  $result .= $this->error($name, $id, 'Unknown request '.$name);
			}
		}

		echo '<?xml version="1.0" encoding="UTF-8"?>
<ipdb>'.($ok ? '
	<status>OK</status>' : '
	<status>Error</status>
	<error>'.$result.'</error>').'
	<result>'.$result.'
	</result>
</ipdb>';
	}


	public static function handle($data) {
		$xml = new XML($data);
	}


	private function error($request, $id, $str, $details = null) {
		global $config;
		if ($config->debug['debug']) {
			error_log($str);
			if ($details)
				error_log('Details: '.var_export($details, true));
		}
		return '
		<'.$request.' id="'.$id.'">
			<status>Error</status>
			<error>'.htmlentities($str).'</error>'.($details ? '
			<details>
'.htmlentities($details).'
			</details>' : '').'
		</'.$request.'>';
	}


	private function result($request, $id, $result) {
		return '
		<'.$request.' id="'.$id.'">'.$result.'
		</'.$request.'>';
	}


	public static function fatal($str, $details = null) {
		global $config;
		if ($config->debug['debug']) {
			error_log($str);
			if ($details)
				error_log(var_export($details, true));
		}
		echo '<?xml version="1.0" encoding="UTF-8"?>
<ipdb>
	<status>Error</status>
	<error>'.htmlentities($str).'</error>'.($details ? '
	<details>
'.htmlentities($details).'
	</details>' : '').'
</ipdb>';
	}


	private function parse_xml_errors($errors, $xml) {
		$xml = explode("\n", $xml);
		foreach ($errors as $error) {
			$xml[$error->line-1] .= "\n".str_repeat('-', $error->column)."^\n";
			switch($error->level) {
			  case LIBXML_ERR_WARNING:
				  $xml[$error->line-1] .= 'Warning ';
				  break;
			  case LIBXML_ERR_ERROR:
				  $xml[$error->line-1] .= 'Error ';
				  break;
			  case LIBXML_ERR_FATAL:
				  $xml[$error->line-1] .= 'Fatal ';
				  break;
			}
			$xml[$error->line-1] .= $error->code.': '.trim($error->message);
		}
		return implode("\n", $xml);
	}


	private function validate($data) {
		libxml_use_internal_errors(true);
		libxml_clear_errors();
		$dom = new DOMDocument();
		if (!@$dom->loadXML($data)) {
			$errors = libxml_get_errors();
			$details = $this->parse_xml_errors($errors, $data);
			libxml_clear_errors();
			return $details;
		}
		libxml_clear_errors();
		$result = @$dom->schemaValidate(dirname(__FILE__).'/xmlschema.xsd');
		if (!$result) {
			$errors = libxml_get_errors();
			$details = $this->parse_xml_errors($errors, $data);
			libxml_clear_errors();
			return $details;
		}
		libxml_clear_errors();
		return true;
	}


}


?>
