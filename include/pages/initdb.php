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


class initdb {


	public $error = null;


	public function get() {
		if (request('action')=='createdb') {
			$content = '
<p>A default database has been created. You can now
<a href="'.me().'?page=login" remote="remote">log in</a> with username \'admin\'
and password \'secret\'.</p>';
			request('page', 'main', true);
			request('action', false, true);
		} else {
			$content = '
<p>You do not yet have a database. Please click the \'create\'-button below
to create an initial database.</p>
<form method="post" remote="remote">
	<input type="hidden" name="page" value="initdb" />
	<input type="hidden" name="action" value="createdb" />
	<input type="submit" value="create" />
</form>';
		}
		return array('title'=>'IPDB :: Initialize database',
					 'content'=>$content);
	}


}


?>
