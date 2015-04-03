<?php

/*
Copyright 2011 Previder bv (http://www.previder.nl)
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

*/


function debug($mixed) {
	global $config, $debugstr;
	if ($config->debug)
		$debugstr .= preg_replace('/\{/', '&#123;', htmlentities(var_export($mixed, true))).'<hr />';
	error_log('debug: '.var_export($mixed, true));
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
		if (!preg_match('/^(action|remote|dummy)$/', $name))
			$_SESSION[$name] = $value;
		return $value;
	} else if (isset($_SESSION[$name]))
		return $_SESSION[$name];
	if (!preg_match('/^(action|remote)$/', $name))
		$_SESSION[$name] = $default;
	return $default;
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


function send($data) {
	global $database, $debugstr, $error, $session, $config, $version, $page;

	if (request('remote')=='remote') {
		if (preg_match('/^(add|delete|change)/', request('action')) &&
			!isset($data['tree']) &&
			!$database->hasUpgrade())
			$data['tree'] = Tree::getHtml('::/0', request('node', NULL));
		if (isset($data['commands']))
			$data['commands'][] = 'timeout = '.$session->expire.';';
		else
			$data['commands'] = array('timeout = '.$session->expire.';');
		$data['debug'] = $debugstr;
		if ($error)
			$data['notify'] = array('type'=>'error', 'message'=>$error);
		header('Content-type: application/json; charset=utf-8');
		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: Fri, 15 Aug 2003 15:00:00 GMT'); /* Remember my wedding day */
		echo json_encode($data);
	} else {
		$tpl = new Template('index.html');
		$tpl->setVar('title', $data['title']);
		$tpl->setVar('version', $version);
		$tpl->setVar('timeout', $session->expire);
		$tpl->setVar('page', $page);
		$tpl->setVar('menu', Menu::get());
		if ($session->authenticated &&
			!$database->hasUpgrade()) {
			$tpl->setVar('tree', Tree::getHtml('::/0', request('node', NULL)));
			if (!preg_match('/^(table|tables|field|fields|history|account|users)$/', request('page')))
				$tpl->parse('treediv');
		}

		if ($error)
			$data['content'] = '<p class="error">'.$error.'</p><br />'.
				(isset($data['content']) ? $data['content'] : '');

		if ($config->debug) {
			$tpl->setVar('debug', $debugstr);
			$tpl->parse('debugdiv');
		} else {
			$tpl->setVar('debugdiv', '');
			$tpl->hideBlock('debugdiv');
		}

		if (isset($data['commands']))
			$data['content'] .= '
<script type="text/javascript">
	'.implode("\n\t", $data['commands']).'
</script>';
		$tpl->setVar('content', $data['content']);
		echo $tpl->get();
	}
	exit;
}


function randstr($length, $base = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789') {
	if ($length>0)
		return $base[rand(0, strlen($base)-1)].randstr($length-1, $base);
	else
		return '';
}


function fatal($str, $code = 500) {
	global $rest;
	if (!$rest)
		exit('Error: '.$str);
	http_response_code($code);
	exit(json_encode(array('error'=>$str), JSON_PRETTY_PRINT));
}



?>
