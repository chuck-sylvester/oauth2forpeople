<?php

# *****************************************************************
# php/index.php
# *****************************************************************
# GitHub OAuth demo entry point.
#
# This version keeps the application intentionally small, but makes the
# request flow explicit:
#   1. Bootstrap session/config/helpers.
#   2. Handle actions that redirect before any HTML is rendered.
#   3. Render the current view for the browser.
#
# That structure avoids "headers already sent" problems and makes the
# redirect-back OAuth callback easier to reason about.
# *****************************************************************

session_start();

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helper.php';

$action = $_GET['action'] ?? NULL;
$hasAuthCode = isset($_GET['code']);

# -----------------------------------------------------------------
# Redirect-producing actions
# -----------------------------------------------------------------
# Keep redirects before page output. Once HTML has been sent, PHP may
# no longer be able to send Location headers reliably.

if($action === 'login') {
  unset($_SESSION['access_token']);

  # Store a nonce in the session so the callback can prove it belongs
  # to this browser-initiated authorization request.
  $_SESSION['state'] = bin2hex(random_bytes(16));

  $params = [
    'response_type' => 'code',
    'client_id' => GITHUB_CLIENT_ID,
    'redirect_uri' => APP_HOMEPAGE_URL,
    'scope' => 'user public_repo',
    'state' => $_SESSION['state']
  ];

  header('Location: ' . GITHUB_AUTHORIZE_URL . '?' . http_build_query($params));
  die();
}

if($hasAuthCode) {
  # GitHub redirects back here with code/state. The state check protects
  # against callbacks that were not initiated by this session.
  if(!isset($_GET['state']) || !isset($_SESSION['state']) || !hash_equals($_SESSION['state'], $_GET['state'])) {
    $_SESSION['oauth_error'] = 'Invalid OAuth state. Please try logging in again.';
    header('Location: ' . $appBaseURL);
    die();
  }

  $token = apiRequest(GITHUB_TOKEN_URL, [
    'grant_type' => 'authorization_code',
    'client_id' => GITHUB_CLIENT_ID,
    'client_secret' => GITHUB_CLIENT_SECRET,
    'redirect_uri' => APP_HOMEPAGE_URL,
    'code' => $_GET['code']
  ]);

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

if($action === 'logout') {
  unset($_SESSION['access_token'], $_SESSION['state']);
  header('Location: ' . $appBaseURL);
  die();
}

# -----------------------------------------------------------------
# Page rendering
# -----------------------------------------------------------------

webPageSetup();
renderDebugConfig();

if(isset($_SESSION['oauth_error'])) {
  echo '<p class="text-red-800 py-2">OAuth error: ' . escapeHtml($_SESSION['oauth_error']) . '</p>';
  unset($_SESSION['oauth_error']);
}

if($action === 'repos') {
  if(empty($_SESSION['access_token'])) {
    echo '<h3 class="text-2xl py-4">Not logged in</h3>';
    echo '<p class="py-2">Please log in before viewing repositories.</p>';
    echo '<p class="py-2"><a class="text-blue-900 hover:text-red-900" href="?action=login">Login</a></p>';
    webPageClose();
    die();
  }

  $repos = apiRequest(GITHUB_API_BASE_URL . 'user/repos?' . http_build_query([
    'sort' => 'created',
    'direction' => 'desc'
  ]));

  if(!is_array($repos) || isset($repos['error']) || isset($repos['message'])) {
    $errorMessage = $repos['error_description'] ?? $repos['message'] ?? 'Unable to load repositories.';
    echo '<h3 class="text-2xl py-4">My Public Repositories</h3>';
    echo '<p class="text-red-800 py-2">' . escapeHtml($errorMessage) . '</p>';
    echo '<p class="py-2"><a class="text-blue-900 hover:text-red-900" href="' . escapeHtml($appBaseURL) . '">Back</a></p>';
    webPageClose();
    die();
  }

  echo '<h3 class="text-2xl py-4">My Public Repositories</h3>';
  echo '<ul>';
  foreach($repos as $repo) {
    if(!isset($repo['html_url'], $repo['name'])) {
      continue;
    }

    echo '<li><a target="_blank" class="text-blue-900 hover:text-red-900" href="' . escapeHtml($repo['html_url']) . '">' . escapeHtml($repo['name']) . '</a></li>';
  }
  echo '</ul><br>';
  echo '<a class="text-blue-900 hover:text-red-900" href="' . escapeHtml($appBaseURL) . '">Back</a>';
  webPageClose();
  die();
}

# Default home view. The only state needed here is whether the session
# currently contains an access token.
if(!empty($_SESSION['access_token'])) {
  echo '<h3 class="text-2xl py-4">Logged In</h3>';
  echo '<p class="py-2"><a class="text-blue-900 hover:text-red-900" href="?action=repos">View Repos</a></p>';
  echo '<p class="py-2"><a class="text-blue-900 hover:text-red-900" href="?action=logout">Logout</a></p>';
} else {
  echo '<h3 class="text-2xl py-4">Not logged in</h3>';
  echo '<p class="py-2"><a class="text-blue-900 hover:text-red-900" href="?action=login">Login</a></p>';
}

webPageClose();
