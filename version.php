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
 * OBU Login - version
 *
 * @package    local_obu_login
 * @author     Peter Welham
 * @copyright  2019, Oxford Brookes University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

$plugin->component = 'local_obu_login'; // Full name of the plugin (used for diagnostics): plugintype_pluginname
$plugin->version  = 2019041200;   // The (date) version of this module + 2 extra digital for daily versions
$plugin->requires = 2012120300;   // Requires this Moodle version - at least 2.0
$plugin->cron     = 0;
$plugin->release = 'v2.10.0';
$plugin->maturity = MATURITY_STABLE;
