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
		$node = request('node');
		$address = request('address');
		$bits = request('bits');
		if (request('confirm')=='yes') {
			$address = address2ip($address);
			$bits = (strcmp($address, '00000000000000000000000100000000')<0 ? request('bits')+96 : request('bits'));
			$description = request('description');
			$tree = $database->getParentTree($address);
			$parent = 0;
			if (count($tree)>0) {
				reset($tree);
				do {
					$current = current($tree);
					if (strcmp(broadcast($current['address'], $current['bits']),
							   broadcast($address, $bits))>=0)
						$parent = $current['id'];
				} while (next($tree));
			}
			if (($next = $database->getNext($address)) &&
				(strcmp(broadcast($address, $bits), $next['address'])>=0)) {
					$this->error = 'Network overlaps with '.ip2address($next['address']).'/'.
						(strcmp($next['address'], '00000000000000000000000100000000')<0 ? $next['bits']-96 : $next['bits']);
			} else {
				$id = $database->addNode($address, $bits, $parent, $description);
				$_SESSION['node'] = $id;
				return 'okidoki';
			}
		}
		if ($node) {
			$new = $database->getAddress($node);
			if (strcmp($new['address'], '00000000000000000000000100000000')<0)
				$new['bits'] = $new['bits']-96;
		} else
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


	private function getDelete() {
		global $config, $database;
		if (request('confirm')=='yes') {
		}
	}


}


?>
