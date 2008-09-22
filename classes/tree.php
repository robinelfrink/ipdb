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


class Tree {


	public $error = null;

	public function get($root, $address = null) {

		global $database;
		$tree = $database->getTree($root, ($address===null ? false : $address));
		if (count($tree)>0) {
			$output = '
<ul id="a_'.$root.'">';
			foreach ($tree as $network) {
				$subtree = Tree::get($network['address'], $address);
				if ($subtree=='')
					$class = '';
				else {
					$bits = $network['bits']+($network['address']<'00000000000000000000000100000000' ? 96 : 0);
					debug($network['address'].'/'.$bits);
				}
				$output .= '
	<li id="a_'.$network['address'].$class.'">
		<span style="display: none;">'.htmlentities($network['description']).'</span>
		'.ip2address($network['address']).'/'.$network['bits'].$subtree.'
	</li>';
			}
			return $output;
		} else
			return '';
	}


}


?>
