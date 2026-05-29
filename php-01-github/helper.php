<?php

# =================================================================
# php/helper.php
# =================================================================
# Small rendering and HTTP helpers for the GitHub OAuth demo.
#
# The main script now handles request flow before rendering HTML. These
# helpers keep repeated page/API details out of index.php while staying
# intentionally lightweight for a single-file-style demo application.
# =================================================================

function escapeHtml($value) {
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

# Start the HTML document after all redirect-producing actions have run.
function webPageSetup() {
  echo '<!DOCTYPE html>';
  echo '<html lang="en">';
  echo '<head>';
  echo '  <meta charset="UTF-8">';
  echo '  <meta name="viewport" content="width=device-width, initial-scale=1.0">';
  echo '  <meta name="description" content="AV DRMS Web Application">';
  echo '  <meta name="author" content="csylvester">';
  echo '  <link rel="shortcut icon" href="msnsw-logo.png">';
  echo '  <script src="https://cdn.tailwindcss.com"></script>';
  echo '  <title>Oauth2 Demo</title>';
  echo '</head>';
  echo '<body class="p-4 m-12 bg-blue-50">';
}

function webPageClose() {
  echo '</body></html>';
}

function renderDebugConfig() {
  echo '<div class="bg-yellow-50 leading-6 font-mono text-sm p-2 border border-yellow-500 rounded">';
  echo '<p><hr>';
  echo 'GitHub App Name: &nbsp;&nbsp;' . escapeHtml(GITHUB_APP_NAME) . '<br>';
  echo 'GitHub Client ID: &nbsp;' . escapeHtml(GITHUB_CLIENT_ID) . '<br>';
  echo 'GitHub Auth URL:  &nbsp;&nbsp;' . escapeHtml(GITHUB_AUTHORIZE_URL) . '<br>';
  echo 'App Homepage URL: &nbsp;' . escapeHtml(APP_HOMEPAGE_URL) . '<br>';
  echo '<hr></p></div><br>';
}

# Wrap cURL for GitHub JSON requests and return a predictable result.
function apiRequest($url, $post=FALSE, $headers=array()) {
  global $appBaseURL;

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

  if($post) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
  }

  $requestHeaders = [
    'Accept: application/json',
    'User-Agent: ' . $appBaseURL
  ];

  if(!empty($_SESSION['access_token'])) {
    $requestHeaders[] = 'Authorization: Bearer ' . $_SESSION['access_token'];
  }

  curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($requestHeaders, $headers));

  $response = curl_exec($ch);
  if($response === FALSE) {
    $error = curl_error($ch);
    curl_close($ch);
    return ['error' => 'curl_error', 'error_description' => $error];
  }

  curl_close($ch);

  $decodedResponse = json_decode($response, TRUE);
  if(json_last_error() !== JSON_ERROR_NONE) {
    return ['error' => 'json_error', 'error_description' => json_last_error_msg()];
  }

  return $decodedResponse;
}
