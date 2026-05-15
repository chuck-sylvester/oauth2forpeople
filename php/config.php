<?php

# =================================================================
# php/config.php
# =================================================================
# Configuration settings and environment variables as constants
# =================================================================

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

# Get values from environment variables
define('GITHUB_APP_NAME',      $_ENV['GITHUB_APP_NAME']);
define('GITHUB_CLIENT_ID',     $_ENV['GITHUB_CLIENT_ID']);
define('GITHUB_CLIENT_SECRET', $_ENV['GITHUB_CLIENT_SECRET']);
define('GITHUB_AUTHORIZE_URL', $_ENV['GITHUB_AUTHORIZE_URL']);
define('GITHUB_TOKEN_URL',     $_ENV['GITHUB_TOKEN_URL']);
define('GITHUB_API_BASE_URL',  $_ENV['GITHUB_API_BASE_URL']);
define('APP_HOMEPAGE_URL',     $_ENV['APP_HOMEPAGE_URL']);

# The URL for this script, used as the redirect URL
$appBaseURL = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
