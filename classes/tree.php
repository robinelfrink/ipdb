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
		$networks = $database->query("SELECT * FROM ip WHERE parent='".
									 $database->escape($root)."' ORDER BY address");
		if (count($networks)>0) {
			$output = '
<ul id="a_'.$root.'">';
			foreach ($networks as $network) {
				$subtree = Tree::get($network['address'], $address);
				$output .= '
	<li id="a_'.$network['address'].'">
		<span style="display: none;">'.htmlentities($network['description']).'</span>
		'.ip2address($network['address']).'/'.$network['bits'].$tree.'
	</li>';
			}
			return $output;
		} else
			return '';

	}


}


?>
