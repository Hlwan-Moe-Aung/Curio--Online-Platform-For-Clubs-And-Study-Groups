<?php
require_once __DIR__ . '/../vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** 
 * use getenv() to grab the values from the docker-compose.yml file.
 * This keeps Client Secret out of source code!
 */
$clientID     = getenv('GOOGLE_CLIENT_ID');
$clientSecret = getenv('GOOGLE_CLIENT_SECRET');
$redirectUri  = getenv('GOOGLE_REDIRECT_URL');

$client = new Google\Client();
$client->setClientId($clientID);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);

$client->addScope("email");
$client->addScope("profile");

// Force Google to show the account chooser
$client->setPrompt('select_account');

?>