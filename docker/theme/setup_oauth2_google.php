<?php
// Prepares the "correo @unamad.edu.pe" Google OAuth 2 login for Aurora.
//
// Usage (inside the web container):
//   php setup_oauth2_google.php                      -> create/update the service, keep credentials as they are
//   php setup_oauth2_google.php <clientid> <secret>  -> also store the Google OAuth client credentials
//   php setup_oauth2_google.php --clear              -> blank the credentials (hides the login button)
//
// What it does:
//   - creates the standard Google OAuth 2 service (endpoints + field mappings) if it does not exist
//   - names it "correo @unamad.edu.pe", restricts logins to the unamad.edu.pe domain,
//     shows it on the login page only, no e-mail confirmation step (trusted institutional domain)
//   - enables the auth_oauth2 authentication plugin next to the existing ones
define('CLI_SCRIPT', true);
require '/var/www/html/config.php';
require_once($CFG->libdir . '/adminlib.php');

\core\session\manager::set_user(get_admin());

$name = 'correo @unamad.edu.pe';
$domain = 'unamad.edu.pe';

$issuer = null;
// get_all_issuers() skips "login only" services unless asked, hence the true.
foreach (\core\oauth2\api::get_all_issuers(true) as $candidate) {
    if ($candidate->get('name') === $name || $candidate->get('servicetype') === 'google') {
        $issuer = $candidate;
        break;
    }
}
if (!$issuer) {
    $issuer = \core\oauth2\api::create_standard_issuer('google');
    echo "Google OAuth 2 service created (id {$issuer->get('id')})\n";
}

$issuer->set('name', $name);
$issuer->set('alloweddomains', $domain);
$issuer->set('requireconfirmation', 0);
$issuer->set('showonloginpage', \core\oauth2\issuer::LOGINONLY);
$issuer->set('enabled', 1);

if (($argv[1] ?? '') === '--clear') {
    $issuer->set('clientid', '');
    $issuer->set('clientsecret', '');
    echo "credentials cleared\n";
} else if (!empty($argv[1]) && !empty($argv[2])) {
    $issuer->set('clientid', $argv[1]);
    $issuer->set('clientsecret', $argv[2]);
    echo "credentials stored\n";
}
$issuer->update();

// Enable the OAuth 2 authentication plugin.
$auths = array_filter(explode(',', (string) get_config('core', 'auth')));
if (!in_array('oauth2', $auths, true)) {
    $auths[] = 'oauth2';
    set_config('auth', implode(',', $auths));
    \core\session\manager::gc();
    core_plugin_manager::reset_caches();
    echo "auth_oauth2 enabled\n";
}

printf(
    "service '%s': domain=%s, login page=%s, configured=%s\n",
    $issuer->get('name'),
    $issuer->get('alloweddomains'),
    $issuer->get('showonloginpage') == \core\oauth2\issuer::LOGINONLY ? 'yes' : 'no',
    $issuer->is_configured() ? 'yes' : 'NO (client id / secret pending)'
);
echo "redirect URI to register in Google Cloud: {$CFG->wwwroot}/admin/oauth2callback.php\n";
