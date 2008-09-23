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
	return ((strcmp($address, network($network, $bits))>=0) &&
			(strcmp($address, broadcast($network, $bits))<=0));
}


function isHost($address, $bits) {
	$bits = (strcmp($address, '00000000000000000000000100000000')<0 ? $bits+96 : $bits);
	return ($bits==128);
}


function escape($string) {
	$result = '';
	for ($i = 0; $i < strlen($string); $i++)
		$result .= escapebycharacter(urlencode($string[$i]));
	return $result;
}


function escapebycharacter($char) {
	if ($char == '+') return '%20';
	if ($char == '%2A') return '*';
	if ($char == '%2B') return '+';
	if ($char == '%2F') return '/';
	if ($char == '%40') return '@';
	if ($char == '%80') return '%u20AC';
	if ($char == '%82') return '%u201A';
	if ($char == '%83') return '%u0192';
	if ($char == '%84') return '%u201E';
	if ($char == '%85') return '%u2026';
	if ($char == '%86') return '%u2020';
	if ($char == '%87') return '%u2021';
	if ($char == '%88') return '%u02C6';
	if ($char == '%89') return '%u2030';
	if ($char == '%8A') return '%u0160';
	if ($char == '%8B') return '%u2039';
	if ($char == '%8C') return '%u0152';
	if ($char == '%8E') return '%u017D';
	if ($char == '%91') return '%u2018';
	if ($char == '%92') return '%u2019';
	if ($char == '%93') return '%u201C';
	if ($char == '%94') return '%u201D';
	if ($char == '%95') return '%u2022';
	if ($char == '%96') return '%u2013';
	if ($char == '%97') return '%u2014';
	if ($char == '%98') return '%u02DC';
	if ($char == '%99') return '%u2122';
	if ($char == '%9A') return '%u0161';
	if ($char == '%9B') return '%u203A';
	if ($char == '%9C') return '%u0153';
	if ($char == '%9E') return '%u017E';
	if ($char == '%9F') return '%u0178';
	return $char;
}


function unescape($string) {
	$result = "";
	for ($i = 0; $i < strlen($string); $i++) {
		$decstr = '';
		for ($p = 0; $p <= 5; $p++)
			if (strlen($string)>($i+$p))
				$decstr .= $string[$i+$p];
		list($decodedstr, $num) = unescapebycharacter($decstr);
		$result .= urldecode($decodedstr);
		$i += $num ;
	}
	return $result;
}


function unescapebycharacter($str) {
	$char = $str;
						
	if ($char == '%u20AC') return array("%80", 5);
	if ($char == '%u201A') return array("%82", 5);
	if ($char == '%u0192') return array("%83", 5);
	if ($char == '%u201E') return array("%84", 5);
	if ($char == '%u2026') return array("%85", 5);
	if ($char == '%u2020') return array("%86", 5);
	if ($char == '%u2021') return array("%87", 5);
	if ($char == '%u02C6') return array("%88", 5);
	if ($char == '%u2030') return array("%89", 5);
	if ($char == '%u0160') return array("%8A", 5);
	if ($char == '%u2039') return array("%8B", 5);
	if ($char == '%u0152') return array("%8C", 5);
	if ($char == '%u017D') return array("%8E", 5);
	if ($char == '%u2018') return array("%91", 5);
	if ($char == '%u2019') return array("%92", 5);
	if ($char == '%u201C') return array("%93", 5);
	if ($char == '%u201D') return array("%94", 5);
	if ($char == '%u2022') return array("%95", 5);
	if ($char == '%u2013') return array("%96", 5);
	if ($char == '%u2014') return array("%97", 5);
	if ($char == '%u02DC') return array("%98", 5);
	if ($char == '%u2122') return array("%99", 5);
	if ($char == '%u0161') return array("%9A", 5);
	if ($char == '%u203A') return array("%9B", 5);
	if ($char == '%u0153') return array("%9C", 5);
	if ($char == '%u017E') return array("%9E", 5);
	if ($char == '%u0178') return array("%9F", 5);

	$char = substr($str, 0, 3);
	if ($char == "%20") return array("+", 2);

	$char = substr($str, 0, 1);

	if ($char == '*') return array("%2A", 0);
	if ($char == '+') return array("%2B", 0);
	if ($char == '/') return array("%2F", 0);
	if ($char == '@') return array("%40", 0);

	if ($char == "%")
		return array(substr($str, 0, 3), 2);
	else
		return array($char, 0);
}



?>
