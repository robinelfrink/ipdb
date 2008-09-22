<?php

/*  Copyright 2008  Robin Elfrink  (email : robin@15augustus.nl)

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
	echo '<pre style="color: #009900; font-size: 80%;">'.htmlentities(var_export($mixed, true)).'</pre>';
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


function request($name, $default = NULL) {
	if (isset($_REQUEST[$name]))
		return (get_magic_quotes_gpc() ?
				stripslashes(is_utf8($_REQUEST[$name]) ? $_REQUEST[$name] : utf8_encode($_REQUEST[$name])) :
				(is_utf8($_REQUEST[$name]) ? $_REQUEST[$name] : utf8_encode($_REQUEST[$name])));
	else
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
			if (strlen($match)>strlen($biggest))
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
	$netmask = bits2netmask(strcmp($address, '00000000000000000000000100000000')<0 ? $bits+96 : $bits);
	$broadcast = '';
	for ($i=0; $i<strlen($address); $i++) {
		$broadcast .= dechex(hexdec($address[$i]) | bindec(substr($netmask, $i*4, 4)));
	}
	return $broadcast;
}


function network($address, $bits) {
	$bitmask = bits2bitmask(strcmp($address, '00000000000000000000000100000000')<0 ? $bits+96 : $bits);
	$network = '';
	for ($i=0; $i<strlen($address); $i++) {
		$network .= dechex(hexdec($address[$i]) & bindec(substr($bitmask, $i*4, 4)));
	}
	return $network;
}


function addressIsChild($address, $network, $bits) {
	return (($address>=network($network, $bits)) &&
			($address<=broadcast($network, $bits)));
}


function isHost($address, $bits) {
	$bits = (strcmp($address, '00000000000000000000000100000000')<0 ? $bits+96 : $bits);
	return ($bits==128);
}


?>
