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
 * OBU Login - bulk change of authentication method
 *
 * @package    local_obu_login
 * @author     Peter Welham
 * @copyright  2015, Oxford Brookes University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require_once('../../config.php');
require_once('../../admin/user/lib.php');
require_once($CFG->libdir.'/adminlib.php');

$return = '/';

$from = strtolower(required_param('from', PARAM_TEXT));
$to = strtolower(required_param('to', PARAM_TEXT));
if ((($from !== 'ldap') && ($from !== 'shibboleth'))
	|| (($from === 'ldap') && ($to !== 'shibboleth'))
	|| (($from === 'shibboleth') && ($to !== 'ldap'))) {
		redirect($return);
}

$confirm = optional_param('confirm', 0, PARAM_BOOL);

require_login();
admin_externalpage_setup('userbulk');
require_capability('moodle/user:update', context_system::instance());

echo $OUTPUT->header();

if ($confirm and confirm_sesskey()) {
    $parts = array_chunk($SESSION->bulk_users, 300);
    foreach ($parts as $users) {
        list($in, $params) = $DB->get_in_or_equal($users);
        $rs = $DB->get_recordset_select('user', "id $in", $params);
        foreach ($rs as $user) {
			if ($user->auth === $from) {
				$user->auth = $to;
				$DB->update_record('user', $user);
			}
        }
        $rs->close();
    }
    echo $OUTPUT->notification(get_string('changessaved'), 'notifysuccess');
    echo $OUTPUT->continue_button($return);

} else {
	// create the user filter form and add all users
	$ufiltering = new user_filtering();
	add_selection_all($ufiltering);
	
    list($in, $params) = $DB->get_in_or_equal($SESSION->bulk_users);
    $userlist = $DB->get_records_select_menu('user', "id $in", $params, 'fullname', 'id,'.$DB->sql_fullname().' AS fullname', 0, MAX_BULK_USERS);
    $usernames = implode(', ', $userlist);
    if (count($SESSION->bulk_users) > MAX_BULK_USERS) {
        $usernames .= ', ...';
    }
    echo $OUTPUT->heading(get_string('confirmation', 'admin'));
    $formcontinue = new single_button(new moodle_url('/local/obu_login/method.php', array('from' => $from, 'to' => $to, 'confirm' => 1)), get_string('yes'));
    $formcancel = new single_button(new moodle_url($return), get_string('no'), 'get');
    echo $OUTPUT->confirm(get_string('forcepasswordchangecheckfull', '', $usernames), $formcontinue, $formcancel);
}

echo $OUTPUT->footer();
