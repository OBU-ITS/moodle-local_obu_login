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
 * OBU Login - Log-in using CAS authentication
 *
 * @package    local_obu_login
 * @author     Peter Welham
 * @copyright  2018, Oxford Brookes University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/auth/cas/CAS/CAS.php');
require_once($CFG->libdir . '/externallib.php');

if (!isloggedin()) {

	if (isset($_REQUEST['debug'])) {
		phpCAS::setDebug(); // Enable debugging
		phpCAS::setVerbose(true); // Enable verbose error messages
	}

	// Initialize phpCAS, making sure it doesn't try to start a new PHP session when connecting to the CAS server
	phpCAS::client(CAS_VERSION_2_0, 'eis-dev.ec.brookes.ac.uk', 443, '/cas', false);

	// For production use set the CA certificate that is the issuer of the certificate on the CAS server
	// phpCAS::setCasServerCACert($cas_server_ca_cert_path);

	// For quick testing you can disable SSL validation of the CAS server.
	// THIS SETTING IS NOT RECOMMENDED FOR PRODUCTION.
	// VALIDATING THE CAS SERVER IS CRUCIAL TO THE SECURITY OF THE CAS PROTOCOL!
	phpCAS::setNoCasServerValidation();
	
	// Force CAS authentication
	phpCAS::forceAuthentication();

	// Logout if desired
	if (isset($_REQUEST['logout'])) {
		phpCAS::logout();
	}

	$USER = $DB->get_record('user', array('username' => phpCAS::getUser()));

	// Setup user session
	\core\session\manager::set_user($USER);
}

redirect($SESSION->wantsurl);
