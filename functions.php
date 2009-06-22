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


function debug($mixed) {
	global $config, $debugstr;
	if ($config->debug['debug'])
		$debugstr .= preg_replace('/\{/', '&#123;', htmlentities(var_export($mixed, true))).'<hr />';
}


function is_utf8($str) {
	// Source: http://www.php.net/manual/en/function.mb-detect-encoding.php#85294
	$c = 0; $b = 0;
	$bits = 0;
	$len = strlen($str);
	for ($i = 0; $i<$len; $i++) {
		$c = ord($str[$i]);
		if ($c > 128) {
			if (($c >= 254))
				return false;
			else if ($c >= 252)
				$bits = 6;
			else if ($c >= 248)
				$bits = 5;
			else if ($c >= 240)
				$bits = 4;
			else if ($c >= 224)
				$bits = 3;
			else if ($c >= 192)
				$bits = 2;
			else
				return false;
			if (($i+$bits) > $len)
				return false;
			while ($bits > 1) {
				$i++;
				$b = ord($str[$i]);
				if ($b < 128 || $b > 191)
					return false;
				$bits--;
			}
		}
	}
	return true;
}


function me() {
	return preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI']);
}


function request($name, $default = NULL, $set = false) {
	if ($set) {
		if ($default) {
			$_SESSION[$name] = $default;
			$_REQUEST[$name] = $default;
		} else {
			unset($_SESSION[$name]);
			unset($_REQUEST[$name]);
		}
		return $default;
	} else if (isset($_REQUEST[$name])) {
		$value = (get_magic_quotes_gpc() ?
				stripslashes(is_utf8($_REQUEST[$name]) ? $_REQUEST[$name] : utf8_encode($_REQUEST[$name])) :
				(is_utf8($_REQUEST[$name]) ? $_REQUEST[$name] : utf8_encode($_REQUEST[$name])));
		if (!preg_match('/^(action|remote)$/', $name))
			$_SESSION[$name] = $value;
		return $value;
	} else if (isset($_SESSION[$name]))
		return $_SESSION[$name];
	if (!preg_match('/^(action|remote)$/', $name))
		$_SESSION[$name] = $default;
	return $default;
}


function ip2address($address) {
	if (strcmp($address, '00000000000000000000000100000000')<0) {
		/* IPv4 */
		$output = '';
		for ($i=0; $i<8; $i=$i+2)
			$output .= hexdec(substr($address, 24+$i, 2)).'.';
		return preg_replace('/\.$/', '', $output);
	} else {
		/* IPv6 */
		$output = preg_replace('/:$/', '', preg_replace('/([0-9a-f]{4})/', '\1:', $address));
		return ipv6compress($output);
	}
}


function address2ip($address) {
	if (preg_match('/^([0-9]+)\.([0-9]+)\.([0-9]+)\.([0-9]+)$/', $address, $matches))
		return '000000000000000000000000'.str_pad(dechex($matches[1]), 2, '0', STR_PAD_LEFT).
			str_pad(dechex($matches[2]), 2, '0', STR_PAD_LEFT).
			str_pad(dechex($matches[3]), 2, '0', STR_PAD_LEFT).
			str_pad(dechex($matches[4]), 2, '0', STR_PAD_LEFT);
	else
		return preg_replace('/:/', '', ipv6uncompress($address));
}


function showip($address, $bits) {
	if ($address=='00000000000000000000000000000000') {
		/* The World */
		return 'The World';
	} else if (strcmp($address, '00000000000000000000000100000000')<0) {
		/* IPv4 */
		return ip2address($address).($bits==128 ? '' : '/'.($bits-96));
	} else {
		/* IPv6 */
		return ip2address($address).'/'.$bits;
	}
}


function ipv6uncompress($address) {
	if (strpos($address, '::')===false)
		return $address;
	else if ($address=='::')
		return '0000:0000:0000:0000:0000:0000:0000:0000';
	$parts = explode('::', $address);
	if ($parts[0]=='')
		$address = str_repeat('0000:', 7-substr_count($parts[1], ':')).$parts[1];
	else if ($parts[1]=='')
		$address = $parts[0].str_repeat(':0000', 7-substr_count($parts[0], ':'));
	else $address = $parts[0].str_repeat(':0000', 6-(substr_count($parts[0], ':')+substr_count($parts[1], ':'))).':'.$parts[1];
	$address = explode(':', $address);
	foreach ($address as $nr=>$part)
		$address[$nr] = str_pad($part, 4, '0', STR_PAD_LEFT);
	return implode(':', $address);
}


function ipv6compress($address) {
	if (preg_match_all('/((^|:)0000)+/', $address, $matches)) {
		$biggest = 0;
		foreach ($matches[0] as $nr=>$match)
			if (strlen($match)>strlen($matches[0][$biggest]))
				$biggest = $nr;
		$address = preg_replace('/'.preg_quote($matches[0][$biggest]).'/', ':', $address);
		$address = preg_replace('/([^:]:)$/', '\1:', $address);
	}
	$address = preg_replace('/(^|:)0+([0-9a-f])/', '\1\2', $address);
	return $address;
}


function bits2netmask($bits) {
	return str_pad(str_repeat('0', $bits), 128, '1', STR_PAD_RIGHT);
}


function bits2bitmask($bits) {
	return str_pad(str_repeat('1', $bits), 128, '0', STR_PAD_RIGHT);
}


function broadcast($address, $bits) {
	$netmask = bits2netmask($bits);
	$broadcast = '';
	for ($i=0; $i<strlen($address); $i++)
		$broadcast .= dechex(hexdec($address[$i]) | bindec(substr($netmask, $i*4, 4)));
	return $broadcast;
}


function _or($one, $two) {
	$one = str_pad($one, 32, '0', STR_PAD_LEFT);
	$two = str_pad($two, 32, '0', STR_PAD_LEFT);
	$result = '';
	for ($i=0; $i<32; $i++)
		$result .= dechex(hexdec($one[$i]) | hexdec($two[$i]));
	return $result;
}


function _and($one, $two) {
	$one = str_pad($one, 32, '0', STR_PAD_LEFT);
	$two = str_pad($two, 32, '0', STR_PAD_LEFT);
	$result = '';
	for ($i=0; $i<32; $i++)
		$result .= dechex(hexdec($one[$i]) & hexdec($two[$i]));
	return $result;
}


function plus($one, $two) {
	$one = str_pad($one, 32, '0', STR_PAD_LEFT);
	$two = str_pad($two, 32, '0', STR_PAD_LEFT);
	$overflow = 0;
	for ($i=31; $i>=0; $i--) {
		$new = hexdec($one[$i])+hexdec($two[$i])+$overflow;
		if ($new>15) {
			$overflow = 1;
			$new = $new-16;
		} else
			$overflow = 0;
		$one[$i] = dechex($new);
	}
	return $one;
}


function minus($one, $two) {
	$one = str_pad($one, 32, '0', STR_PAD_LEFT);
	$two = str_pad($two, 32, '0', STR_PAD_LEFT);
	$borrow = 0;
	for ($i=31; $i>=0; $i--) {
		$new = hexdec($one[$i])-hexdec($two[$i])-$borrow;
		if ($new<0) {
			$borrow = 1;
			$new = $new+16;
		} else
			$borrow = 0;
		$one[$i] = dechex($new);
	}
	return $one;
}


function network($address, $bits) {
	$bitmask = bits2bitmask($bits);
	$network = '';
	for ($i=0; $i<strlen($address); $i++)
		$network .= dechex(hexdec($address[$i]) & bindec(substr($bitmask, $i*4, 4)));
	return $network;
}


function ipv4netmask($bits) {
	$mask = bits2bitmask($bits);
	$mask = str_repeat('0', 96).substr($mask, 96);
	$netmask = '';
	for ($i=0; $i<32; $i++)
		$netmask .= dechex(bindec(substr($mask, $i*4, 4)));
	return $netmask;
}



function addressIsChild($address, $network, $bits) {
	return ((strcmp($address, network($network, $bits))>=0) &&
			(strcmp($address, broadcast($network, $bits))<=0));
}


function isHost($address, $bits) {
	$bits = (strcmp($address, '00000000000000000000000100000000')<0 ? $bits+96 : $bits);
	return ($bits==128);
}


function escape($string) {
	$result = rawurlencode($string);
	return $result;
}


function unescape($string) {
	$result = rawurldecode($string);
	$result = str_replace('\\', '\\\\', $result);
	$result = str_replace('"', '\\"', $result);
	return $result;
}


function findunused($base, $next) {
	$unused = array();
	if ((strcmp($base, $next)<0) &&
		preg_match('/^([0]*)([1-9a-f]|$)/', minus($next, $base), $matches)) {
		$bits = 1+(4*strlen($matches[1]))+(4-strlen(decbin(hexdec($matches[2]))));
		while (($bits<128) &&
			   (strcmp($base, network($base, $bits))!=0))
			$bits++;
		if ((strcmp($base, '00000000000000000000000100000000')>=0) ||
			($bits<128))
			$unused[] = array('id'=>null,
							  'address'=>$base,
							  'bits'=>$bits);
		$base = plus(broadcast($base, $bits), '00000000000000000000000000000001');
		$nextunused = findunused($base, $next);
		if (is_array($nextunused) && (count($nextunused)>0))
			foreach ($nextunused as $network)
				$unused[] = $network;
	}
	return $unused;
}


function send($data) {
	global $debugstr, $error, $session, $config;

	/* Check if we had an error */
	if ($error) {
		if (isset($data['content']))
			$data['content'] = '<p class="error">'.$error.'</p><br />'.$data['content'];
		else
			$data['content'] = '<p class="error">'.$error.'</p><br />';
	}

	$skin = new Skin($config->skin);
	if ($skin->error)
		exit('Error: '.$skin->error);
	if (request('remote')=='remote') {
		if (preg_match('/^(add|delete|change)/', request('action')) &&
			!isset($data['tree']))
			$data['tree'] = Tree::get(0, request('node', NULL));
		if ($debugstr)
			$data['debug'] = '<pre>'.$debugstr.'</pre>';
		header('Content-type: text/xml; charset=utf-8');
		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: Fri, 15 Aug 2003 15:00:00 GMT'); /* Remember my wedding day */
		echo '<?xml version="1.0" encoding="UTF-8"?>
<content>';
		foreach ($data as $key=>$content)
			echo '
	<'.$key.'>'.implode('</'.$key.'><'.$key.'>', str_split(escape($content), 1024)).'</'.$key.'>';
		echo '
</content>';
		} else {
			$skin->setFile('index.html');
			$skin->setVar('title', $data['title']);
			$skin->setVar('version', $version);
			$skin->setVar('meta', '<script type="text/javascript" src="ipdb.js"></script>');
			$skin->setVar('menu', Menu::get());
			if ($session->authenticated) {
				$skin->setVar('tree', Tree::get(0, request('node', NULL)));
				$skin->parse('treediv');
			}

			if ($debugstr) {
				$skin->setVar('debug', '<pre>'.$debugstr.'</pre>');
			}
			$skin->parse('debugdiv');

			if (isset($data['commands']))
				$data['content'] .= '
<script type="text/javascript">
	'.$data['commands'].'
</script>';
			$skin->setVar('content', $data['content']);
			echo $skin->get();
		}
	exit;
}



?>
