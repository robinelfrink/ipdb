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

$Id$
*/


class user {


	public $error;


	public function get() {
		global $database, $session, $config;
		$skin = new Skin($config->skin);
		$skin->setFile('user.html');

		$user = $database->getUser(request('user'));

		$skin->setVar('user', htmlentities($user['username']));
		$skin->setVar('name', htmlentities($user['name']));

		if ($database->isAdmin($session->username)) {
			if (is_array($user['access']) &&
				(count($user['access'])>0)) {
				foreach ($user['access'] as $access) {
					$skin->setVar('address', showip($access['address'], $access['bits']));
					$skin->setVar('nodelink', me().'?page=main&amp;node='.$access['id']);
					$skin->setVar('node', $access['id']);
					$skin->setVar('readonly_checked', $access['access']=='r' ? ' checked="checked"' : '');
					$skin->setVar('write_checked', $access['access']=='w' ? ' checked="checked"' : '');
					$skin->parse('network');
				}
				$skin->parse('access');
			}
			$skin->setVar('prefixes', htmlentities(request('prefixes')));
			$skin->parse('addaccess');
		}


		$content = $skin->get();

		return array('title'=>'IPDB :: User '.request('user'),
					 'content'=>$content);
	}


}


?>
