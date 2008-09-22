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


class Session {


	public $error = null;
	public $authenticated = false;


	public function authenticate() {

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
			global $database;
			$result = $database->query("SELECT `password` FROM `admin` WHERE `username` = '".
									   $database->escape($username)."'");
			if ($result===false) {
				$this->error = 'Login failed';
				return false;
			}

			if (md5(trim(request('password')))!=$result[0]['password']) {
				$this->error = 'Login failed';
				return false;
			}
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
		$_SESSION['expire'] = time()+(15*60);

		$this->authenticated = true;
		return true;

	}


}


?>
