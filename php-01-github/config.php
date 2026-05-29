<?php

# =================================================================
# php/config.php
# =================================================================
# Central configuration for the GitHub OAuth demo.
#
# This version intentionally keeps configuration simple, but makes the
# required environment variables explicit. That keeps startup failures
# close to their cause instead of letting missing values surface later
# in the OAuth flow.
# =================================================================

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$requiredEnvVars = [
  'GITHUB_APP_NAME',
  'GITHUB_CLIENT_ID',
  'GITHUB_CLIENT_SECRET',
  'GITHUB_AUTHORIZE_URL',
  'GITHUB_TOKEN_URL',
  'GITHUB_API_BASE_URL',
  'APP_HOMEPAGE_URL',
];

foreach($requiredEnvVars as $envVar) {
  if(empty($_ENV[$envVar])) {
    throw new RuntimeException('Missing required environment variable: ' . $envVar);
  }
}

# Promote environment variables to constants used by the demo.
define('GITHUB_APP_NAME',      $_ENV['GITHUB_APP_NAME']);
define('GITHUB_CLIENT_ID',     $_ENV['GITHUB_CLIENT_ID']);
define('GITHUB_CLIENT_SECRET', $_ENV['GITHUB_CLIENT_SECRET']);
define('GITHUB_AUTHORIZE_URL', $_ENV['GITHUB_AUTHORIZE_URL']);
define('GITHUB_TOKEN_URL',     $_ENV['GITHUB_TOKEN_URL']);
define('GITHUB_API_BASE_URL',  rtrim($_ENV['GITHUB_API_BASE_URL'], '/') . '/');
define('APP_HOMEPAGE_URL',     $_ENV['APP_HOMEPAGE_URL']);

# Use the configured app URL as the canonical place to return after
# login, logout, and callback handling. Keeping this single source of
# truth avoids subtle differences between computed and configured URLs.
$appBaseURL = APP_HOMEPAGE_URL;
