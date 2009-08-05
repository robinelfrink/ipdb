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

		if (!$xml->request) {
			$this->fatal('No request');
			return;
		}

		$pools = array();
		foreach ($config->pools as $name=>$value)
			if (preg_match('/^pool_([a-z0-9_]+)$/', $name, $matches))
				$pools[$matches[1]] = preg_split('/,\s*/', $value);

		switch ($xml->request->attributes()->name) {
		  case 'create':
			  if (!isset($pools[(string)$xml->request->pool->attributes()->name])) {
				  $this->fatal('Unknown address pool '.(string)$xml->request->pool->attributes()->name);
				  return;
			  }
			  $username = (string)$xml->request->pool->username;
			  $password = ($xml->request->pool->password ? (string)$xml->request->pool->password : randstr(6));
			  $customer = (string)$xml->request->pool->customer;
			  $identifier = (string)$xml->request->pool->identifier;
			  $ipv4 = false;
			  $ipv6 = false;
			  foreach ($xml->request->pool->children() as $name=>$value)
				  if ($name=='ipv4bits')
					  $ipv4 = (int)$value;
				  else if ($name=='ipv6bits')
					  $ipv6 = (int)$value;
			  if (!$ipv4 && !$ipv6) {
				  $this->fatal('No ipv4 and no ipv6 bits given');
				  return;
			  }
			  if ($xml->request->pool->maxup && $xml->request->pool->maxdown)
				  $columndata = array('maxup'=>(int)$xml->request->pool->maxup,
									  'maxdown'=>(int)$xml->request->pool->maxdown);
			  else
				  $columndata = null;
			  /*if (!$database->addExtra('radius', $username, $customer.'/'.$identifier, '', $columndata)) {
				  $this->fatal($database->error);
				  return;
			  }*/
			  if ($ipv4) {
				  $ipv4blocks = array();
				  foreach ($pools[(string)$xml->request->pool->attributes()->name] as $block)
					  if ((($address = address2ip(preg_replace('/\/.*/', '', $block)))!=$block) &&
						  (strcmp($address, '00000000000000000000000100000000')<0))
						  $ipv4blocks[] = array('address'=>$address, 'bits'=>(preg_replace('/.*\//', '', $block)+96));
			  }
			  if ($ipv6) {
				  $ipv6blocks = array();
				  foreach ($pools[(string)$xml->request->pool->attributes()->name] as $block)
					  if ((($address = address2ip(preg_replace('/\/.*/', '', $block)))!=$block) &&
						  (strcmp($address, '00000000000000000000000100000000')>=0))
						  $ipv6blocks[] = array('address'=>$address, 'bits'=>preg_replace('/.*\//', '', $block));
			  }
			  if ($ipv4 && (count($ipv4blocks)==0)) {
				  if ($ipv6 && (count($ipv6blocks)==0)) {
					  $this->fatal('No ipv4 block or ipv6 block defined');
					  return false;
				  } else {
					  $this->fatal('No ipv4 block defined');
					  return false;
				  }
			  } else if ($ipv6 && (count($ipv6blocks)==0)) {
				  $this->fatal('No ipv6 block defined');
				  return false;
			  }
			  if ($ipv4) {
				  if (!($ipv4 = $database->findFree($ipv4blocks, $ipv4+96))) {
					  $this->fatal($database->error ? $database->error : 'No ipv4 block available');
					  return;
				  }
				  /*if (!$database->addNode($ipv4['address'], $ipv4['bits'], $customer.'/'.$identifier)) {
					  $this->fatal($database->error);
					  return;
				  }*/
				  $ipv4 = ip2address($ipv4['bits']<128 ? plus($ipv4['address'], 1) : $ipv4['address']).'/'.($ipv4['bits']-96);
			  }
			  if ($ipv6) {
				  if (!($ipv6 = $database->findFree($ipv6blocks, $ipv6))) {
					  $this->fatal($database->error ? $database->error : 'No ipv6 block available');
					  return;
				  }
				  /*if (!$database->addNode($ipv6['address'], $ipv6['bits'], $customer.'/'.$identifier)) {
					  $this->fatal($database->error);
					  return;
				  }*/
				  $ipv6 = ip2address($ipv6['address']).'/'.$ipv6['bits'];
			  }
			  $result ='
		<username>'.$username.'</username>
		<password>'.$password.'</password>'.($ipv4 ? '
		<ipv4>'.$ipv4.'</ipv4>' : '').($ipv6 ? '
		<ipv6>'.$ipv6.'</ipv6>' : '');
			  break;
		  case 'delete':
			  $result = 'deleted';
			  break;
		  default:
			  $this->fatal('Unkown request: '.$xml->request->attributes()->name);
			  return;
		}

		echo '<?xml version="1.0" encoding="UTF-8"?>
<ipdb>
	<status>OK</status>
	<result>'.$result.'
	</result>
</ipdb>';
	}


	public static function handle($data) {
		$xml = new XML($data);
	}


	public static function fatal($str, $details = null) {
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
