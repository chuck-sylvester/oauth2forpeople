<?php

# =================================================================
# php/helper.php
# =================================================================
# Helper functions
# =================================================================

# Function to set up basic HTML page
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


# Function to wrap cURL
function apiRequest($url, $post=FALSE, $headers=array()) {
  global $appBaseURL; 

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
