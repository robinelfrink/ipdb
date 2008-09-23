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


class main {


	public $error = null;


	public function get() {
		global $database, $address;
		if ($address) {
			$data = $database->getAddress($address);
			$title = ip2address($data['address']).'/'.
				(strcmp($data, '00000000000000000000000100000000')<0 ? $data['bits']-96 : $data['bits']);
			$content = $address;
		} else {
			$title = 'Main page';
			$tree = $database->getTree('00000000000000000000000000000000', false);
			if (count($tree)>0) {
				$content = '
<p>You have '.count($tree).' main networks in your database:</p>
<table>
	<thead>
		<tr><th>address</th><th>description</th></tr>
	</thead>
	<tbody>';
				foreach ($tree as $network)
					$content .= '
		<tr><td>'.ip2address($network['address']).'/'.$network['bits'].'</td><td>'.$network['description'].'</td></tr>';
				$content .= '
	</tbody>
</table>';
			} else {
				$content = '
<p>You currently do not have any networks in your database.</p>';
			}
		}
		return array('title'=>$title,
					 'content'=>$content);
	}


}


?>
