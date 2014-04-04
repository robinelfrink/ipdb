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


class upgradedb {


	public $error = null;


	public function get() {
		global $database, $session;
		if (request('action')=='upgradedb') {
			if ($database->error) {
				$this->error = $database->error;
				$content = '';
			} else {
				$content = '
<p>Your database has been upgraded</p>';
			}
			request('page', 'main', true);
			request('action', false, true);
		} else if ($database->isAdmin($session->username)) {
			$content = '
<p>Your database needs an upgrade. Please click the \'upgrade\'-button below
to upgrade your database.</p>
<form method="post" remote="remote">
	<input type="hidden" name="page" value="upgradedb" />
	<input type="hidden" name="action" value="upgradedb" />
	<input type="submit" value="upgrade" />
</form>';
		} else {
			$content = '
<p>Your database needs an upgrade. Please contact an administrator.</p>';
		}
		return array('title'=>'IPDB :: Upgrade database',
					 'content'=>$content);
	}


}


?>
