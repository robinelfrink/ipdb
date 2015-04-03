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


class edituser {


	public $error;


	public function get() {
		global $database, $session, $config;
		$tpl = new Template('userform.html');

		$user = $database->getUser(request('user'));

		$tpl->setVar('user', htmlentities($user['username']));
		$tpl->setVar('name', htmlentities($user['name']));
		$tpl->setVar('admin', $user['admin'] ? 1 : 0);
		$tpl->setVar('adminchecked', $user['admin'] ? 'checked="checked"' : '');

		if ($database->isAdmin($session->username)) {
			if (is_array($user['access']) &&
				(count($user['access'])>0)) {
				foreach ($user['access'] as $access) {
					$tpl->setVar('node', $access['node']);
					$tpl->setVar('readonly_checked', $access['access']=='r' ? ' checked="checked"' : '');
					$tpl->setVar('write_checked', $access['access']=='w' ? ' checked="checked"' : '');
					$tpl->parse('network');
				}
				$tpl->parse('access');
			}
			$tpl->setVar('prefixes', htmlentities(request('prefixes')));
			$tpl->parse('addaccess');
		}


		$content = $tpl->get();

		return array('title'=>'IPDB :: User '.request('user'),
					 'content'=>$content);
	}


}


?>
