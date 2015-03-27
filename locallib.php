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
 * OBU Login - library functions
 *
 * @package    local_obu_login
 * @author     Peter Welham
 * @copyright  2015, Oxford Brookes University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

function read_url($curl_session) {
	$response = curl_exec($curl_session);
	if (curl_error($curl_session)){
		return array('ERROR', curl_error($curl_session));
	}

	// remove any <CR> characters
	$response = str_replace("\r", "", $response);
	
	//clean duplicate header that seems to appear on fastcgi with output buffer on some servers!!
	$response = str_replace("HTTP/1.1 100 Continue\n\n", "", $response);

	return (explode("\n\n", $response, 2)); 
}

function parse_headers($header_data) {
	
	$headers = array();
	
	$lines = preg_split('/\n\s*/', $header_data); // one line per element
	
    foreach ($lines as $v) {
		if (substr($v, 0, 4) === 'HTTP') {
			list(, $headers['Status'], $headers['Status-Text']) = explode(' ', $v);
		} else {
			$h = preg_split('/: \s*/', $v);
			if (isset($headers[$h[0]])) {
				$headers[$h[0]] = $headers[$h[0]] . ', ' . $h[1];
			} else {
				$headers[$h[0]] = $h[1];
			}
		}
    }

    return $headers;
}

function set_cookies(&$cookie_store, $host, $cookie_data) {
	
	$cookies = preg_split('/, \s*/', $cookie_data); // one cookie per element
	
    foreach ($cookies as $cookie) {
		$path = '/';
		$expired = false;
		$parts = preg_split('/; \s*/', $cookie);
		foreach ($parts as $part) {
			if ((substr($part, 0, 5) == 'path=') || (substr($part, 0, 5) == 'Path=')) {
				$path = substr($part, 5);
			} else if ((substr($part, 0, 8) == 'expires=') || (substr($part, 0, 8) == 'Expires=')) {
				$expired = true; // an assumption that fits this data
			}
		}
		$path = $host . $path;
		$cookie_name = substr($parts[0], 0, strpos($parts[0], '='));
		$found = false;
		for ($c = 0; $c < count($cookie_store); $c++) {
			if ($cookie_store[$c][0] == $path) {
				$stored_name = substr($cookie_store[$c][1], 0, strpos($cookie_store[$c][1], '='));
				if ($stored_name == $cookie_name) {
					$found = true;
					if ($expired) {
						$cookie_store[$c][1] = 'expired'; // expire
					} else {
						$cookie_store[$c][1] = $parts[0]; // update
					}
					break;
				}
			}
		}
		
		if (!$found && !$expired) {
			$cookie_store[] = array($path, $parts[0]);
		}
    }

    return;
}

function get_cookies($cookie_store, $path) {
	
	$cookies = '';
	foreach ($cookie_store as $cookie) {
		if ((strncmp($cookie[0], $path, strlen($cookie[0])) == 0) && ($cookie[1] != 'expired')) {
			if ($cookies != '') {
				$cookies .= '; ';
			}
			$cookies .= $cookie[1];
		}
	}

    return $cookies;
}

?>