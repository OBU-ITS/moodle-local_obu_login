<?php

// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * OBU Login - logout the user
 *
 * @package    local_obu_login
 * @author     Peter Welham
 * @copyright  2015, Oxford Brookes University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require_once(dirname(__FILE__) . '/../../config.php');

header('Access-Control-Allow-Origin: *'); // Allow cross-origin resource sharing (by Google App Engine, for example)

$scheme = urldecode(required_param('scheme',  PARAM_ALPHANUMEXT));
$serviceshortname = urldecode(required_param('service',  PARAM_ALPHANUMEXT));

//if (isloggedin()) {
	$authsequence = get_enabled_auth_plugins(); // auths, in sequence
	foreach($authsequence as $authname) {
		$authplugin = get_auth_plugin($authname);
		$authplugin->logoutpage_hook();
	}
//}

require_logout();

$location = "Location: " . $scheme . "://" . $serviceshortname;
header($location);
die;

