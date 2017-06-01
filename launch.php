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
 * Launch script, launch the app using custom URL schemes.
 *
 * @package    local_obu_login
 * @author     Juan Leyva ('/local/mobile/launch.php') modified by Peter Welham (OBU)
 * @copyright  2014 Juan Leyva <juan@moodle.com> and 2015, Oxford Brookes University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');

////// <-- OBU modified
header('Access-Control-Allow-Origin: *'); // Allow cross-origin resource sharing (by Google App Engine, for example)

$scheme = urldecode(required_param('scheme',  PARAM_RAW));
$serviceshortname = urldecode(required_param('service',  PARAM_ALPHANUMEXT));
$standard = optional_param('standard', 1, PARAM_BOOL);

// If the user is not logged, this will redirect him to the login page.
// Once logged, it will be redirected again to this page and the app launched.
if (!isloggedin() || isguestuser()) {
	if ($standard) {
		require_login(0, false);
	} else { // requires our own non-standard login (which ignores any alternative login URL) 
		$SESSION->wantsurl = new moodle_url('/local/obu_login/launch.php?scheme=' . urlencode($scheme) . '&service=' . urlencode($serviceshortname));
//		$login = new moodle_url('/local/obu_login/index.php');
		$login = new moodle_url('/login/?authShib=NOSHIB');
		redirect($login);
	}
}
////// OBU modified -->

// Check web services enabled.
if (!$CFG->enablewebservices) {
    throw new moodle_exception('enablewsdescription', 'webservice');
}

// Check if the service exists and is enabled.
$service = $DB->get_record('external_services', array('shortname' => $serviceshortname, 'enabled' => 1));
if (empty($service)) {
    throw new moodle_exception('servicenotavailable', 'webservice');
}

////// <-- OBU modified
/*
// Check if the plugin is properly configured.
$typeoflogin = get_config('local_mobile', 'typeoflogin');

if (empty($typeoflogin)) {
    throw new moodle_exception('pluginnotenabledorconfigured', 'local_mobile');
}
*/
////// OBU modified -->

// Check if there is any required system capability.
if ($service->requiredcapability and !has_capability($service->requiredcapability, context_system::instance())) {
    throw new moodle_exception('missingrequiredcapability', 'webservice', '', $service->requiredcapability);
}

// Specific checks related to user restricted service.
if ($service->restrictedusers) {
    $authoriseduser = $DB->get_record('external_services_users',
        array('externalserviceid' => $service->id, 'userid' => $USER->id));

    if (empty($authoriseduser)) {
        throw new moodle_exception('usernotallowed', 'webservice', '', $serviceshortname);
    }

    if (!empty($authoriseduser->validuntil) and $authoriseduser->validuntil < time()) {
        throw new moodle_exception('invalidtimedtoken', 'webservice');
    }
}

// Check if a token has already been created for this user and this service.

$conditions = array(
    'userid' => $USER->id,
    'externalserviceid' => $service->id,
    'tokentype' => EXTERNAL_TOKEN_PERMANENT
);
$tokens = $DB->get_records('external_tokens', $conditions, 'timecreated ASC', 'id, sid, token, validuntil, iprestriction');

// A bit of sanity checks.
foreach ($tokens as $key => $token) {

    // Checks related to a specific token. (script execution continue).
    $unsettoken = false;
    // If sid is set then there must be a valid associated session no matter the token type.
    if (!empty($token->sid)) {
        if (!\core\session\manager::session_exists($token->sid)) {
            // This token will never be valid anymore, delete it.
            $DB->delete_records('external_tokens', array('sid' => $token->sid));
            $unsettoken = true;
        }
    }

    // Remove token is not valid anymore.
    if (!empty($token->validuntil) and $token->validuntil < time()) {
        $DB->delete_records('external_tokens', array('token' => $token->token, 'tokentype' => EXTERNAL_TOKEN_PERMANENT));
        $unsettoken = true;
    }

    // Remove token if its ip not in whitelist.
    if (isset($token->iprestriction) and !address_in_subnet(getremoteaddr(), $token->iprestriction)) {
        $unsettoken = true;
    }

    if ($unsettoken) {
        unset($tokens[$key]);
    }
}

// If some valid tokens exist then use the most recent.
if (count($tokens) > 0) {
    $token = array_pop($tokens);
} else {
    // Check if the user can create tokens for mobile access.
    if (($serviceshortname == MOODLE_OFFICIAL_MOBILE_SERVICE and
            has_capability('moodle/webservice:createmobiletoken', context_system::instance()))
            or (!is_siteadmin($USER) && has_capability('moodle/webservice:createtoken', context_system::instance()))) {

        // Create a new token.
        $token = new stdClass;
        $token->token = md5(uniqid(rand(), 1));
        $token->userid = $USER->id;
        $token->tokentype = EXTERNAL_TOKEN_PERMANENT;
        $token->contextid = context_system::instance()->id;
        $token->creatorid = $USER->id;
        $token->timecreated = time();
        $token->externalserviceid = $service->id;
        // MDL-43119 Token valid for 3 months (12 weeks).
        $token->validuntil = $token->timecreated + 12 * WEEKSECS;
        $token->id = $DB->insert_record('external_tokens', $token);

        // Moodle 2.6 and onwards.
        if (class_exists('\core\event\webservice_token_created')) {
            $params = array(
                'objectid' => $token->id,
                'relateduserid' => $USER->id,
                'other' => array(
                    'auto' => true
                )
            );
            $event = \core\event\webservice_token_created::create($params);
            $event->add_record_snapshot('external_tokens', $token);
            $event->trigger();
        }
    } else {
////// <-- OBU modified
        throw new moodle_exception('cannotcreatetoken', 'local_obu_login');
////// OBU modified -->
    }
}

// Log token access.
$DB->set_field('external_tokens', 'lastaccess', time(), array('id' => $token->id));

// Moodle 2.6 and onwards.
if (class_exists('\core\event\webservice_token_sent')) {
    $params = array(
        'objectid' => $token->id,
    );
    $event = \core\event\webservice_token_sent::create($params);
    $event->add_record_snapshot('external_tokens', $token);
    $event->trigger();
}

////// <-- OBU modified
/*
// This is some type of security.
// Passport is generated in the app, so the app opening can be validated using that variable.
// Passports are valid only one time, it's deleted in the app once used.

$siteid = md5($CFG->wwwroot . $passport);   // Passport is used here as salt.
$apptoken = base64_encode($siteid . ':::' . $token->token);
*/
////// OBU modified -->

// Redirect using the custom URL scheme.
////// <-- OBU modified
if (strpos($scheme, '://') !== false) { // Full URL is specified for regular browsers 
	$location = "Location: " . $scheme . "?token=" . $token->token;
} else { // Use custom scheme for mobile apps
	$location = "Location: " . $scheme . "://" . $serviceshortname . "?token=" . $token->token;
}
////// OBU modified -->
header($location);
die;

