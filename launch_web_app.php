<?php

// This file is part of Moodle - http://moodle.org/
//
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
 * OBU Login - Log-in, get a token and launch a web app.
 *
 * @package    obu_login
 * @category   local
 * @author     Peter Welham
 * @copyright  2015, Oxford Brookes University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$app = urldecode(optional_param('app', null, PARAM_ALPHANUMEXT));
$service = urldecode(optional_param('service', null, PARAM_RAW));
$auth = urldecode(optional_param('auth', null, PARAM_INT));
$token = urldecode(optional_param('token', null, PARAM_ALPHANUMEXT));
$home = new moodle_url('/');
	
?>
<!DOCTYPE html>
<html>
<head>
    <title>OBU Login - Launch Web App</title>
    <meta name="author" content="Oxford Brookes University" />
    <meta charset="utf-8" />
</head>
<body>
    <script type="text/javascript">
		var app = "<?php echo $app; ?>";
		var service = "<?php echo $service; ?>";
		var auth = "<?php echo $auth; ?>";
		var token = "<?php echo $token; ?>";
		var home = "<?php echo $home; ?>";
		
		if ((app != "") && (service != "")) { // Need a token - so get one
			// Save app and service for later
			window.sessionStorage.setItem("obu_login_app", app);
			window.sessionStorage.setItem("obu_login_service", service);
			var url = home + "local/obu_login/launch_web_app.php"; // Return here when we're tokened-up
			var launch = home + "local/obu_login/launch.php?scheme=" + encodeURIComponent(url) + "&service=" + encodeURIComponent(service) + "&auth=" + encodeURIComponent(auth);
			window.location = launch;
		} else if (app != "") { // No service token required
			window.location = "https://brookes-apps.appspot.com/" + app + "/";
		} else if (token != "") { // Take flight my son
			// Get the saved app and service
			app = window.sessionStorage.getItem("obu_login_app");
			service = window.sessionStorage.getItem("obu_login_service");
			var form = document.createElement("form");
			form.action = "https://brookes-apps.appspot.com/" + app + "/";
			form.method = "POST";
			var i = document.createElement('input');
			i.type = 'hidden';
			i.name = 'service';
			i.value = service;
			form.appendChild(i);
			i = document.createElement('input');
			i.type = 'hidden';
			i.name = 'token';
			i.value = token;
			form.appendChild(i);
			document.body.appendChild(form);
			form.submit();
		}
    </script>
</body>
</html>