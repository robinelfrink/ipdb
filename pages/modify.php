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


class modify {


	public $error = null;


	public function get() {
		switch (request('action')) {
		  case 'add':
			  $content = $this->getAdd();
			  break;
		  case 'delete':
			  $content = $this->getDelete();
			  break;
		  case 'change':
			  $content = $this->getChange();
			  break;
		  default:
			  $this->error = 'No such action: '.request('action');
			  $content = '';
		}
		return array('title'=>$title,
					 'content'=>$content);
	}


	private function getAdd() {
		global $config, $database;
		if (request('confirm')=='yes') {
		} else {
			if (request('node'))
				$new = $database->getAddress(request('node'));
			else
				$new = array('id'=>null,
							 'address'=>request('address'),
							 'bits'=>request('bits'),
							 'parent'=>null,
							 'description'=>'');
			$skin = new Skin($config->skin);
			$skin->setVar('address', ip2address($new['address']));
			$skin->setVar('bits', $new['bits']);
			$skin->setVar('description', $new['description']);
			$skin->setFile('newnode.html');
			return $skin->get();
		}
	}


}


?>
