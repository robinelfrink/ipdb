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


class user {


	public function get() {
		global $database, $session, $config;
		$skin = new Skin($config->skin);
		$skin->setFile('user.html');

		$user = $database->getUser(request('user'));

		$skin->setVar('username', htmlentities($user['username']));
		$skin->setVar('name', htmlentities($user['name']));

		foreach ($user['access'] as $access) {
			$skin->setVar('address', showip($access['address'], $access['bits']));
			$skin->setVar('nodelink', me().'?page=main&node='.$access['id']);
			$dropdown = '<select name="access_'.$access['id'].'">
	<option value="r"'.($access['access']=='r' ? ' selected' : '').'>read-only</option>
	<option value="w"'.($access['access']=='w' ? ' selected' : '').'>write</option>
</select>';
			$skin->setVar('access', $dropdown);
			$skin->setVar('deletelink', me().'?action=deleteaccess&node='.$access['id'].'&user='.htmlentities($user['username']));
			$skin->parse('network');
		}

		$content = $skin->get();

		return array('title'=>'IPDB :: User '.request('user'),
					 'content'=>$content);
	}


}


?>
