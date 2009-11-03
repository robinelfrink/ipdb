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


class users {


	public $error = null;


	public function get() {
		global $database, $config;
		$skin = new Skin($config->skin);
		$skin->setFile('users.html');

		$users = $database->getUsers();

		foreach ($users as $user) {
			$skin->setVar('username', htmlentities($user['username']));
			$skin->setVar('name', htmlentities($user['name']));
			$skin->setVar('editlink', me().'?page=user&user='.htmlentities($user['username']));
			$skin->parse('user');
		}

		$content = $skin->get();

		return array('title'=>'IPDB :: Users',
					 'content'=>$content);
	}


}


?>
