<?php

/*
Copyright 2014
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


if (!$database->hasDatabase())
	fatal('No database.', 503);
else if ($database->hasUpgrade())
    fatal('Database outdated.', 503);


if (!isset($_SERVER['HTTP_AUTH']))
	fatal('Authorization required', 401);
if (!($credentials = explode(':', base64_decode($_SERVER['HTTP_AUTH']), 2)) ||
	!$session->authenticate($credentials[0], $credentials[1]))
	fatal($session->error ? $session->error : 'Credentials failed', 401);


?>
