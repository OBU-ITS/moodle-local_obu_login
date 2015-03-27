moodle-local_obu_login
======================

A Moodle plugin that provides login services to OBU applications.

Mobile users must authenticate by sending a GET request to moodle_base_url/obu_login/token.php, passing the parameters username, password and web service. A token will be returned if successfully authenticated. This token must be passed with each request to the web service.

<h2>INSTALLATION</h2>
This plugin should be installed in the local directory of the Moodle instance.
