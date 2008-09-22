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
		global $database;
		if (request('address')) {
			$title = request('address');
			$content = request('address');
		} else {
			$title = 'Main page';
			$result = $database->query("SELECT `address`, `bits`, `description` FROM ip WHERE ".
									   "`parent`='00000000000000000000000000000000' ORDER BY `address`");
			if (count($result)>0) {
				$content = '
<p>You have '.count($result).' main networks in your database:</p>
<table>
	<thead>
		<tr><th>address</th><th>description</th></tr>
	</thead>
	<tbody>';
				foreach ($result as $network)
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
