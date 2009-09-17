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


class Session {


	public $error = null;
	public $authenticated = false;
	public $username = 'system';
	public $name = '';
	private $expire;


	public function __construct($config) {
		if (preg_match('/h$/', $config['expire']))
			$this->expire = preg_replace('/h$/', '', $config['expire'])*3600;
		else if (preg_match('/m$/', $config['expire']))
			$this->expire = preg_replace('/m$/', '', $config['expire'])*60;
		if (preg_match('/s$/', $config['expire']))
			$this->expire = preg_replace('/s$/', '', $config['expire']);
	}


	public function authenticate() {

		global $config;

		$this->error = null;
		ini_set('session.use_cookies', '1');
		ini_set('session.save_handler', 'files');
		session_start();

		if (request('action')=='login') {
			/* User wants to logon, extract username/password */
			$username = trim(htmlentities(request('username'), ENT_QUOTES));
		} else if (!isset($_SESSION['username'])) {
			unset($_SESSION);
			session_destroy();
			return false;
		} else {
			$username = $_SESSION['username'];
		}

		if ((request('action')=='logout') ||	/* User requests logout */
			($username=='')) {					/* Session broken...? */
			unset($_SESSION);
			session_destroy();
			$this->error = 'Logged out';
			return false;
		}

		if (isset($_SESSION['expire']) && ($_SESSION['expire']<time())) {
			unset($_SESSION);
			session_destroy();
			$this->error = 'Session expired';
			return false;
		}

		if (request('action')=='login') {
			global $database, $page;

			switch ($config->session['auth']) {
			  case 'ldap':
				  if (($ldap = @ldap_connect($config->session['ldap_url']))===false) {
					  $this->error = 'Unable to connect to LDAP';
					  return false;
				  }
				  ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
				  if (!@ldap_bind($ldap, $config->session['ldap_binddn'], $config->session['ldap_bindpw'])) {
					  $this->error = ldap_error($ldap);
					  return false;
				  }
				  if (!($rsc = @ldap_search($ldap, $config->session['ldap_basedn'],
											'uid='.$username, array('dn')))) {
					  $this->error = ldap_error($ldap);
					  return false;
				  }
				  $entries = ldap_get_entries($ldap, $rsc);
				  if (count($entries)==0) {
					  $this->error = 'Login failed';
					  return false;
				  }
				  if (!@ldap_bind($ldap, $entries[0]['dn'], trim(request('password')))) {
					  $this->error = 'Login failed';
					  return false;
				  }
				  break;
			  default:
				  if (!($result = $database->getUser($username))) {
					  $this->error = 'Login failed';
					  return false;
				  }
				  if (md5(trim(request('password')))!=$result['password']) {
					  $this->error = 'Login failed';
					  return false;
				  }
			}

			$_SESSION['name'] = $result['name'];
			$page = 'main';
		}

		/* Generate and check md5 key over session information */
		$key = md5($username.getenv('REMOTE_ADDR').getenv('X-FORWARDED-FOR'));
		if (isset($_SESSION['key']) &&
			(strcmp($_SESSION['key'], $key)!=0)) {
			$this->error = 'Session expired';
			unset($_SESSION);
			session_destroy();
			return false;
		}
		$_SESSION['username'] = $username;
		$_SESSION['key'] = $key;
		$_SESSION['expire'] = time()+$this->expire;

		$this->authenticated = true;
		$this->username = $username;
		$this->name = $_SESSION['name'];
		return true;

	}


	public function changeUsername($username) {
		$_SESSION['username'] = $username;
		$this->username = $username;
	}


	public function changeName($name) {
		$_SESSION['name'] = $name;
		$this->name = $name;
	}


}


?>
