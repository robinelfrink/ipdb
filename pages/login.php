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


class login {


	public $error = null;


	public function get() {
		global $session;
		$content = '
<form method="post" action="">
	<input type="hidden" name="action" value="login" />
	<table id="login">
		<thead><tr><th colspan="2">Login</th></tr></thead>
		<tbody>'.($session->error ? '
			<tr><td class="error" colspan="2">'.$session->error.'</td></tr>' : '').'
			<tr>
				<td>Username:</td>
				<td><input size="20" type="text" name="username" value="'.request('username', $_SESSION['username']).'" /></td>
			</tr>
			<tr>
				<td>Password:</td>
				<td><input size="20" type="password" name="password" /></td>
			</tr>
			<tr><td></td><td><input type="submit" value="login" /></td></tr>
		</tbody>
	</table>
</form>';
		return array('title'=>'IPDB :: Login',
					 'tree'=>'&nbsp;',
					 'content'=>$content);
	}


}


?>
