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
 * OBU Login - get the user a token for a web service
 *
 * @package    local_obu_login
 * @author     Peter Welham
 * @copyright  2015, Oxford Brookes University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

// this is the live version so...
if (isset($_REQUEST['debug'])) {
	die;
}
 
if (!isset($_REQUEST['debug'])) {
	header('Content-Type: application/json');
}

// check, at least, that the required parameters are there
if (!isset($_REQUEST['username']) || !isset($_REQUEST['password']) || !isset($_REQUEST['service'])) {
	die(json_encode(array('error' => 'Missing parameter(s)'))); // bail out
}

require_once('./locallib.php');

if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on')) {
	$base = 'https://' . $_SERVER['HTTP_HOST'];
} else {
	$base = 'http://' . $_SERVER['HTTP_HOST'];
}

$curl_session = curl_init();
curl_setopt($curl_session, CURLOPT_ENCODING, 'gzip, deflate');
curl_setopt($curl_session, CURLOPT_HEADER, true);
curl_setopt($curl_session, CURLOPT_HTTPHEADER, array('Accept: text/html', 'Accept-Language: en-US'));
curl_setopt($curl_session, CURLOPT_USERAGENT, 'OBU Login');
curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl_session, CURLOPT_TIMEOUT, 30);

// let's try and authenticate using the conventional token procedure first
curl_setopt($curl_session, CURLOPT_POST, true);
$credentials = 'username=' . urlencode($_REQUEST['username']) . '&password=' . urlencode($_REQUEST['password']) . '&service=' . urlencode($_REQUEST['service']);
curl_setopt($curl_session, CURLOPT_POSTFIELDS, $credentials);
curl_setopt($curl_session, CURLOPT_REFERER, '');
$url = $base . '/login/token.php';
curl_setopt($curl_session, CURLOPT_URL, $url);
$ar = read_url($curl_session);
if (isset($_REQUEST['debug'])) {
	print('<h4>' . $url . '</h4>');
	if ($_REQUEST['debug'] > 2) {
		$body = str_replace('<', '{', $ar[1]);
		$body = str_replace('>', '}', $body);
		print('<p>' . $ar[0] . '<p>' . $body);
	}
}
if (strpos($ar[1], '"token"') > 0) { // we have a token
	curl_close($curl_session);
	die($ar[1]); // pass it on to the caller
} else if (strpos($ar[1], '"error"') == 0) { // unknown error
	curl_close($curl_session);
	die(json_encode(array('error' => 'Token procedure failed'))); // bail out
}

// OK, we'll have to try Shibboleth authentication (cURL auto redirects would fail due to the custom URL scheme)
curl_setopt($curl_session, CURLOPT_HTTPGET, true);
curl_setopt ($curl_session, CURLOPT_POSTFIELDS, '');
curl_setopt($curl_session, CURLOPT_REFERER, '');
$url = $base . '/local/obu_login/launch.php?passport=666&service=' . $_REQUEST['service']; // we don't set/verify a passport
$cookie_store = array();
do {
	if (isset($_REQUEST['debug'])) {
		print('<h4>' . $url . '</h4>');
	}
	curl_setopt($curl_session, CURLOPT_URL, $url);
	$u = parse_url($url);
	if ($u['path'] == '') {
		$path = '/';
	} else {
		$path = $u['path'];
	}
	$path = $u['host'] . $path;
	$cookies = get_cookies($cookie_store, $path);
	if ($cookies != "") {
		if (isset($_REQUEST['debug']) && ($_REQUEST['debug'] > 1)) {
			print($cookies . '<br>');
		}
		curl_setopt($curl_session, CURLOPT_COOKIE, $cookies);
	}
	$ar = read_url($curl_session);
	
	if (isset($_REQUEST['debug']) && ($_REQUEST['debug'] > 2)) {
		$body = str_replace('<', '{', $ar[1]);
		$body = str_replace('>', '}', $body);
		print('<p>' . $ar[0] . '<p>' . $body);
	}
	
	$headers = parse_headers($ar[0]);
	if (!isset($headers['Status'])) {
		echo(json_encode(array('error' => 'Server error')));
		break;
	}
	if (isset($headers['Set-Cookie']))
	{
		if (isset($_REQUEST['debug']) && ($_REQUEST['debug'] > 1)) {
			print('<i>' . $headers['Set-Cookie'] . '</i><br>');
		}
		set_cookies($cookie_store, $u['host'], $headers['Set-Cookie']);
	}
	if (($headers['Status'] == '301') || ($headers['Status'] == '302') || ($headers['Status'] == '303')) { // redirect
		if (substr($headers['Location'], 0, 12) == 'moodlemobile') { // use of the custom URL scheme indicates that we've arrived
			$token = base64_decode(substr($headers['Location'], (strpos($headers['Location'], '=') + 1))); // decode token...
			echo(json_encode(array('token' => substr($token, (strpos($token, ':::') + 3))))); // ...and output it
			break; // that's all folks - thank you and good night.
		}
		curl_setopt($curl_session, CURLOPT_REFERER, $url);
		curl_setopt($curl_session, CURLOPT_HTTPGET, true);
		curl_setopt ($curl_session, CURLOPT_POSTFIELDS, '');
		$url = $headers['Location'];
	} else if ($headers['Status'] != '200') {
			echo(json_encode(array('error' => 'Shibboleth error')));
			break;
	} else if (strpos($ar[1], 'Log in to the site') > 0) {
		// we have the Moodle login form - let's 'click' SSO
		curl_setopt($curl_session, CURLOPT_REFERER, $url);
		curl_setopt($curl_session, CURLOPT_HTTPGET, true);
		curl_setopt ($curl_session, CURLOPT_POSTFIELDS, '');
		$url = $base . '/auth/shibboleth/index.php';
	} else if (strpos($ar[1], 'Shibboleth Identity Provider') > 0) {
		// we have the SSO login form - if our credentials haven't already been rejected, let's 'complete and submit' it
		if (strpos($ar[1], 'Credentials not recognized') > 0) {
			echo(json_encode(array('error' => 'Credentials not recognized')));
			break;
		}
		curl_setopt($curl_session, CURLOPT_POST, true);
		$credentials = 'j_username=' . urlencode($_REQUEST['username']) . '&j_password=' . urlencode($_REQUEST['password']);
		curl_setopt($curl_session, CURLOPT_POSTFIELDS, $credentials);
		curl_setopt($curl_session, CURLOPT_REFERER, $url);
	} else if (strpos($ar[1], 'Continue') > 0) {
		// we have the Continue form - let's 'submit' it
		curl_setopt($curl_session, CURLOPT_POST, true);
		curl_setopt($curl_session, CURLOPT_REFERER, $url);
		$start = strpos($ar[1], 'action="', 0) + 8;
		$length = strpos($ar[1], '"', $start) - $start;
		$url = html_entity_decode(substr($ar[1], $start, $length));
		$start = strpos($ar[1], 'RelayState" value="', $start) + 19;
		$length = strpos($ar[1], '"', $start) - $start;
		$rs = urlencode(html_entity_decode(substr($ar[1], $start, $length)));
		$start = strpos($ar[1], 'SAMLResponse" value="', $start) + 21;
		$length = strpos($ar[1], '"', $start) - $start;
		$sr = urlencode(html_entity_decode(substr($ar[1], $start, $length)));
		$form = 'RelayState=' . $rs . '&SAMLResponse=' . $sr;
		curl_setopt($curl_session, CURLOPT_POSTFIELDS, $form);
	} else {
		echo(json_encode(array('error' => 'Server error')));
		break;
	}
} while(true);

curl_close($curl_session);

?>