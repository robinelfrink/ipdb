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


class addextra {


	public $error = null;


	public function get() {
		global $config, $error, $database;
		$skin = new Skin($config->skin);
		$skin->setFile('extradetails.html');
		$skin->setVar('item', request('item', ''));
		$skin->setVar('description', request('description', ''));
		$skin->setVar('comments', request('comments', ''));
		$skin->setVar('table', $config->extratables[request('table')]['description']);
		$skin->parse('add');
		return array('content'=>$skin->get());

	}


}


?>
