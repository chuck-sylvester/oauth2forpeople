<?php

# *****************************************************************
# php/index.php
#  
# Shaking off the old PHP cobwebs... it's been a while.
# *****************************************************************

session_start();

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

# Get values from environment variables
$githubAppName = $_ENV['GITHUB_APP_NAME'];
$githubClientId = $_ENV['GITHUB_CLIENT_ID'];
$githubClientSecret = $_ENV['GITHUB_CLIENT_SECRET'];
$githubAuthorizeURL = $_ENV['GITHUB_AUTHORIZE_URL'];
$githubTokenURL = $_ENV['GITHUB_TOKEN_URL'];
$githubApiBaseURL = $_ENV['GITHUB_API_BASE_URL'];
$appHomepageURL = rtrim($_ENV['APP_HOMEPAGE_URL']);

# The URL for this script, used as the redirect URL
$appBaseURL = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];

# Define a helper function to wrap cURL
function apiRequest($url, $post=FALSE, $headers=array()) {
  global $githubAppName, $appBaseURL; 

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

  if($post)
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));

  $requestHeaders = [
    'Accept: application/json',
    'User-Agent: ' . $appBaseURL
  ];

  if(isset($_SESSION['access_token']))
    $requestHeaders[] = 'Authorization: Bearer ' . $_SESSION['access_token'];

  curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($requestHeaders, $headers));

  $response = curl_exec($ch);
  return json_decode($response, true);
}

echo "<p><hr>";
echo "GitHub Application Name: " . htmlspecialchars($githubAppName ?? '', ENT_QUOTES, 'UTF-8') . "<br>";
echo "GitHub Client ID: " . htmlspecialchars($githubClientId ?? '', ENT_QUOTES, 'UTF-8') . "<br>";
echo "Homepage URL: " . htmlspecialchars($appHomepageURL, ENT_QUOTES, 'UTF-8') . "<br>";
echo "<hr></p>";

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
    echo '<h3>Logged In</h3>';
    echo '<p><a href="?action=repos">View Repos</a></p>';
    echo '<p><a href="?action=logout">Logout</a></p>';
  } else {
    echo '<h3>Not logged in</h3>';
    echo '<p><a href="?action=login">Login</a></p>';
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
    'client_id' => $githubClientId,
    'redirect_uri' => $appHomepageURL,
    'scope' => 'user public_repo',
    'state' => $_SESSION['state']
  );

  # Redirect user to GitHub authorization page
  header('Location: ' . $githubAuthorizeURL . '?' . http_build_query($params));
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
  $token = apiRequest($githubTokenURL, array(
    'grant_type' => 'authorization_code',
    'client_id' => $githubClientId,
    'client_secret' => $githubClientSecret,
    'redirect_uri' => $appHomepageURL,
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
  $repos = apiRequest($githubApiBaseURL . 'user/repos?' . http_build_query([
    'sort' => 'created',
    'direction' => 'desc'
  ]));

  echo '<ul>';
  foreach($repos as $repo)
    echo '<li><a href="' . htmlspecialchars($repo['html_url'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($repo['name'], ENT_QUOTES, 'UTF-8') . '</a></li>';
  echo '</ul>';
}

?>
