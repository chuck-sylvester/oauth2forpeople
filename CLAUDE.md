# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Educational project demonstrating the OAuth 2.0 Authorization Code flow across multiple languages. Currently implements a GitHub OAuth integration in PHP. Future implementations in Python and JavaScript are planned.

## PHP Implementation

### Prerequisites

- LAMP stack: Apache HTTPD, PHP with FPM, MariaDB/MySQL (installable via Homebrew on macOS)
- Composer for PHP dependency management
- A GitHub OAuth App (create at GitHub Developer Settings)

### Setup

```bash
# Install PHP dependencies
cd php && composer install

# Copy and populate the environment file
cp .env.example .env  # or create .env manually at the repo root
```

The `.env` file (at repo root) must contain:
```
APP_NAME=...
APP_HOMEPAGE_URL=http://localhost:8080
APP_CALLBACK_URL=http://localhost:8080/
GITHUB_CLIENT_ID=...
GITHUB_CLIENT_SECRET=...
```

Configure Apache to serve the `php/` directory as the document root (see `php/README.md` for the full Apache virtual host config and PHP-FPM setup on macOS).

### Running

Start services, then visit `APP_HOMEPAGE_URL` in a browser. No automated tests exist — testing is manual via browser interaction with the full OAuth flow.

## Architecture

The PHP implementation is intentionally a **single file** (`php/index.php`) for clarity as a learning resource. It handles the complete OAuth 2.0 Authorization Code flow:

1. **Login** — generates a random CSRF state token, stores it in the session, redirects to GitHub's authorization URL
2. **Callback** — validates the returned state with `hash_equals()`, exchanges the authorization code for an access token via cURL, stores the token in the session
3. **Authenticated requests** — the `apiRequest()` helper (lines 29–50) wraps cURL, automatically attaching the `Authorization: Bearer` header when a token is present, and returns decoded JSON
4. **Logout** — clears the session

All credentials and URLs are loaded from the `.env` file via `vlucas/phpdotenv` at the top of `index.php`.

## Key Implementation Notes

- `apiRequest()` is the single point for all GitHub API calls — extend it here when adding new API interactions
- State validation uses `hash_equals()` for timing-safe comparison (important: do not replace with `==`)
- The app is sessionful but has no persistent database; all state lives in the PHP session
- HTML output is escaped with `htmlspecialchars()` throughout — maintain this when adding new output
