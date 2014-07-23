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


class deleteuser {


	public $error = null;


	public function get() {
		global $config, $database;
		if ($user = $database->getUser(request('user'))) {
			$tpl = new Template('deleteuser.html');
			$tpl->setVar('user', request('user'));
			$tpl->setVar('name', $user['name']);
			$content = $tpl->get();
			return array('title'=>'IPDB :: Delete user',
						 'content'=>$content);
		} else
			return array('title'=>'Error',
						 'content'=>'Requested user cannot be found');
	}


}


?>
