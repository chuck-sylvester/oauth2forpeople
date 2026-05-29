<?php

# *****************************************************************
# php/index.php
#  
# Shaking off the old PHP cobwebs... it's been a while.
# *****************************************************************

session_start();

require_once __DIR__ . '/vendor/autoload.php';
include "./config.php";
include "./helper.php";

# HTML page setup
webPageSetup();

# Print a few debug messages
echo '<div class="bg-yellow-50 leading-6 font-mono text-sm">';
echo '<p><hr>';
echo "GitHub App Name: &nbsp;&nbsp;" . htmlspecialchars(GITHUB_APP_NAME ?? '', ENT_QUOTES, 'UTF-8') . "<br>";
echo "GitHub Client ID: &nbsp;" . htmlspecialchars(GITHUB_CLIENT_ID ?? '', ENT_QUOTES, 'UTF-8') . "<br>";
echo "GitHub Auth URL:  &nbsp;&nbsp;" . htmlspecialchars(GITHUB_AUTHORIZE_URL ?? '', ENT_QUOTES, 'UTF-8') . "<br>";
echo "App Homepage URL: &nbsp;" . htmlspecialchars(APP_HOMEPAGE_URL, ENT_QUOTES, 'UTF-8') . "<br>";
echo '<hr></p></div><br>';

# -----------------------------------------------------------------
# Set up the "Logged-In" and "Logged-Out" views
# -----------------------------------------------------------------

if(isset($_SESSION['oauth_error'])) {
  echo '<p>OAuth error: ' . htmlspecialchars($_SESSION['oauth_error'], ENT_QUOTES, 'UTF-8') . '</p>';
  unset($_SESSION['oauth_error']);
}

# If session has an access token, user is already logged in
if(!isset($_GET['action']) && !isset($_GET['code'])) {
  if(!empty($_SESSION['access_token'])) {
    echo '<h3 class="text-2xl py-4">Logged In</h3>';
    echo '<p class="py-2"><a class="text-blue-900 hover:text-red-900" href="?action=repos">View Repos</a></p>';
    echo '<p class="py-2"><a class="text-blue-900 hover:text-red-900" href="?action=logout">Logout</a></p>';
  } else {
    echo '<h3 class="text-2xl py-4">Not logged in</h3>';
    echo '<p class="py-2"><a class="text-blue-900 hover:text-red-900" href="?action=login">Login</a></p>';
  }
  die();
}

# -----------------------------------------------------------------
# Authorization Request
# -----------------------------------------------------------------

# Start by sending user to the GitHub Authorization page
if(isset($_GET['action']) && $_GET['action'] == 'login') {
  unset($_SESSION['access_token']);

  # Generate a random hash and and store in the session
  $_SESSION['state'] = bin2hex(random_bytes(16));

  $params = array(
    'response_type' => 'code',
    'client_id' => GITHUB_CLIENT_ID,
    'redirect_uri' => APP_HOMEPAGE_URL,
    'scope' => 'user public_repo',
    'state' => $_SESSION['state']
  );

  # Redirect user to GitHub authorization page
  header('Location: ' . GITHUB_AUTHORIZE_URL . '?' . http_build_query($params));
  die();
}

# -----------------------------------------------------------------
# Obtain Access Token
# -----------------------------------------------------------------

# After redirect back to this page, query string contains "code" and "state"
if(isset($_GET['code'])) {
  # Verify that state matches our stored state
  if(!isset($_GET['state']) || !isset($_SESSION['state']) || !hash_equals($_SESSION['state'], $_GET['state'])) {
    header('Location: ' . $appBaseURL . '?error=invalid_state');
    die();
  }

  # Exchange auth code for an access token
  $token = apiRequest(GITHUB_TOKEN_URL, array(
    'grant_type' => 'authorization_code',
    'client_id' => GITHUB_CLIENT_ID,
    'client_secret' => GITHUB_CLIENT_SECRET,
    'redirect_uri' => APP_HOMEPAGE_URL,
    'code' => $_GET['code']
  ));

  if(empty($token['access_token'])) {
    $_SESSION['oauth_error'] = $token['error_description'] ?? $token['error'] ?? 'Unable to obtain an access token.';
    header('Location: ' . $appBaseURL);
    die();
  }

  unset($_SESSION['state']);
  $_SESSION['access_token'] = $token['access_token'];

  header('Location: ' . $appBaseURL);
  die();
}

if(isset($_GET['action']) && $_GET['action'] == 'logout') {
  unset($_SESSION['access_token'], $_SESSION['state']);
  header('Location: ' . $appBaseURL);
  die();
}

# -----------------------------------------------------------------
# Make API Request
# -----------------------------------------------------------------

if(isset($_GET['action']) && $_GET['action'] == 'repos') {
  # Find all repos created by the authorized user
  $repos = apiRequest(GITHUB_API_BASE_URL . 'user/repos?' . http_build_query([
    'sort' => 'created',
    'direction' => 'desc'
  ]));

  echo '<h3 class="text-2xl py-4">My Public Repositories</h3>';
  echo '<ul>';
  foreach($repos as $repo)
    echo '<li><a target="_blank" class="text-blue-900 hover:text-red-900" href="' . htmlspecialchars($repo['html_url'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($repo['name'], ENT_QUOTES, 'UTF-8') . '</a></li>';
  echo '</ul><br>';
  echo '<a href="/">← Back';
}

echo '</body></head></html>';

?>
