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


class Menu {


	public function get() {

		global $config, $session;
		if ($session->authenticated) {
			$skin = new Skin($config->skin);
			$skin->setFile('menu.html');
			$skin->setVar('searchform', '<form method="" action="" remote="remote">
	<input type="text" size="20" name="search" value="'.htmlentities(request('search', '')).'" />
	<input type="image" src="skins/'.$config->skin['skin'].'/images/search.png" />
	<input type="hidden" name="action" value="search" />
</form>');
			$skin->setVar('item', '<a href="'.me().'?page=main&node=0" remote=remote>The World</a>');
			$skin->parse('menuitem');
			$skin->setVar('item', '<a href="'.me().'?page=login&action=logout" remote=remote>Logout '.
						  $session->username.'</a>');
			$skin->parse('menuitem');
			return $skin->get();
		}
		return '';
	}


}


?>
