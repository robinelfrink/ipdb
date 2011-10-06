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


class nodeaccess {


	public function get() {
		global $database, $config;
		$skin = new Skin($config->skin);
		$skin->setFile('nodeaccess.html');

		$address = $database->getAddress(request('node'));
		$access = $database->getAccess(request('node'));
		$users = $database->getUsers();

		$userselect = '<select name="user">';

		foreach ($users as $user) {
			$skin->setVar('userlink', me().'?page=user&amp;user='.$user['username']);
			$skin->setVar('username', $user['username']);
			$useraccess = $database->getAccess(request('node'), $user['username']);
			if ($useraccess['id']==request('node')) {
				$access = '<select name="access_'.htmlentities($user['username']).'">
	<option value="r"'.($useraccess['access']=='r' ? ' selected' : '').'>read-only</option>
	<option value="w"'.($useraccess['access']=='w' ? ' selected' : '').'>write</option>
</select>';
				$skin->setVar('access', $access);
				$skin->setVar('deletelink', '<a href="'.me().'?action=deleteaccess&amp;node='.
							  request('node').'&amp;user='.htmlentities($user['username']).'">delete</a>');
			} else {
				$skin->setVar('access', ($useraccess['access']=='w' ? 'write' : 'read-only').
							  ', inherited from <a href="'.me().'?page=main&amp;node='.
							  $useraccess['id'].'">'.showip($useraccess['address'], $useraccess['bits']).'</a>');
				$skin->setVar('deletelink', '');
				$userselect .= '
	<option value="'.htmlentities($user['username']).'">'.htmlentities($user['username']).'</option>';
			}
			$skin->parse('user');
		}

		$userselect .= '
</select>';
		$skin->setVar('users', $userselect);

		$skin->setVar('address', showip($address['address'], $address['bits']));
		$skin->setVar('addresslink', me().'?page=main');
		$content = $skin->get();

		return array('title'=>'IPDB :: Access',
					 'content'=>$content);
	}


}


?>
