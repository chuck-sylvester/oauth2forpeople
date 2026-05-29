# Python GitHub OAuth Implementation Guide

## Overview

This guide walks through building a GitHub OAuth 2.0 Authorization Code flow application
using **FastAPI + HTMX + Jinja2 Templates**. It is the Python counterpart to the PHP
implementation in `php-01-github/` and shares the same project-root `.env` file.

Each task is self-contained: it explains what the PHP version did, how Python does the
same thing differently, includes copy-paste-ready code, and ends with a verification step.
Complete them in order — later tasks import from earlier ones.

---

## PHP → Python Strategy

This table summarizes how every PHP mechanism maps to its Python equivalent. Refer back
to it as you work through the tasks.

| Concern | PHP (`php-01-github/`) | Python (`python-01-github/`) |
|---|---|---|
| Dependency management | Composer / `composer.json` | pip / `requirements.txt` |
| Environment variables | `vlucas/phpdotenv` + manual `foreach` check | `pydantic-settings` `BaseSettings` (validates at startup) |
| Session storage | `session_start()` / `$_SESSION` superglobal | Starlette `SessionMiddleware` (signed cookie, server-side dict) |
| Request routing | `?action=login\|logout\|repos` query params | Dedicated path routes `/login`, `/logout`, `/repos`, `/callback` |
| HTML templating | Inline `echo` / `escapeHtml()` | Jinja2 templates (auto-escaping on by default for `.html`) |
| HTTP calls to GitHub | cURL via `apiRequest()` helper | `httpx.AsyncClient` via `api_request()` service function |
| CSRF state token | `bin2hex(random_bytes(16))` | `secrets.token_hex(16)` |
| Timing-safe comparison | `hash_equals()` | `hmac.compare_digest()` |
| XSS protection | `htmlspecialchars()` on every output | Jinja2 auto-escaping — no manual calls needed |
| Static assets | Apache serves the directory | FastAPI `StaticFiles` mount at `/static` |

---

## Final Project Structure

After completing all tasks, your `python-01-github/` directory will look like this:

```
python-01-github/
├── .venv/
├── app/
│   ├── static/
│   │   └── msnsw-logo.png          # copy from php-01-github/
│   ├── templates/
│   │   ├── base.html               # HTML shell, Tailwind CDN, HTMX CDN
│   │   ├── home.html               # logged-in / logged-out view
│   │   └── partials/
│   │       ├── debug_config.html   # yellow config debug box
│   │       └── repos_list.html     # <ul> fragment swapped in by HTMX
│   ├── routers/
│   │   ├── __init__.py
│   │   ├── auth.py                 # GET /login  /callback  /logout
│   │   └── pages.py                # GET /  /repos
│   ├── services/
│   │   ├── __init__.py
│   │   └── github.py               # all HTTP calls to GitHub
│   ├── __init__.py
│   ├── config.py                   # pydantic-settings — env var validation & access
│   ├── templating.py               # shared Jinja2Templates instance
│   └── main.py                     # app factory, middleware, router registration
├── docs/
│   └── implementation-guide.md     # this file
└── requirements.txt
```

**Why this structure?**
FastAPI convention separates routing (routers/), business/HTTP logic (services/), and
app wiring (main.py). It mirrors how a real application would grow: each router is a
feature area, each service owns a specific integration. The PHP version kept everything
in one file for clarity as a learning resource; here the split is intentional for
demonstrating FastAPI idioms.

---

## Task 1 — Update `requirements.txt`

### What the PHP version did

PHP managed dependencies via Composer. The only runtime library needed was
`vlucas/phpdotenv`; cURL is built into PHP itself.

### What Python needs

`fastapi[standard]` is an "extras" install that bundles a curated set of companion
packages. **Everything in the list below that is not explicitly listed is pulled in
automatically as a transitive dependency** — you do not need to declare them.

| Package declared | Why | Transitive dependencies included |
|---|---|---|
| `fastapi[standard]` | Framework + ASGI server | `uvicorn`, `pydantic`, `pydantic-settings`, `jinja2`, `python-multipart`, `itsdangerous`, `starlette` |
| `httpx` | Async HTTP client (replaces cURL) | — |
| `python-dotenv` | `.env` file parsing (used by pydantic-settings) | — |

**`requests` is not listed.** It was in the draft `requirements.txt` but is redundant:
`httpx` covers both sync and async use cases and is the correct choice inside an async
FastAPI application. Using `requests` (which is synchronous and blocking) inside an
`async def` route would block the event loop and defeat the purpose of async.

### Code

Replace the contents of `requirements.txt`:

```
# -----------------------------------------------------------
# Project Dependencies
# -----------------------------------------------------------

# FastAPI framework and standard extras
# Includes: uvicorn, pydantic, pydantic-settings, jinja2,
#           python-multipart, itsdangerous, starlette
fastapi[standard]

# Async HTTP client — used for all GitHub API and OAuth calls
httpx

# .env file parsing (used internally by pydantic-settings)
python-dotenv
```

### Install

```bash
# Run from python-01-github/ with the venv active
pip install -r requirements.txt
```

### Verification

```bash
pip show fastapi pydantic-settings httpx jinja2 itsdangerous starlette uvicorn
```

All seven packages should show version info. If any are missing, re-run `pip install`.

---

## Task 2 — Register a Second GitHub OAuth App & Update `.env`

### Why a second GitHub OAuth App?

The PHP application registered `http://localhost:8080` as the OAuth callback URL on
GitHub and passes that same URL as `redirect_uri` in the authorization request. When
GitHub redirects back, PHP detects `?code=...` at the root path.

The Python application uses a dedicated `/callback` route
(`http://localhost:8080/callback`). **GitHub validates that the `redirect_uri` you send
exactly matches the registered callback URL for that app.** If you change the existing
PHP app's registered URL to `/callback`, the PHP OAuth flow breaks. Since both apps
share the same `.env` and you want them to coexist:

> **Register a new, separate GitHub OAuth App for the Python implementation.**

Because both apps run on port 8080 but never at the same time, this cleanly separates
credentials without touching the PHP configuration.

### Steps to register the Python GitHub OAuth App

1. Go to **GitHub → Settings → Developer settings → OAuth Apps → New OAuth App**
2. Fill in:
   - **Application name:** `OAuth 2.0 for People — Python` (or similar)
   - **Homepage URL:** `http://localhost:8080`
   - **Authorization callback URL:** `http://localhost:8080/callback`
3. Click **Register application**
4. On the next page, note the **Client ID** — you'll need it
5. Click **Generate a new client secret** and note the secret immediately (it's only shown once)

### `.env` additions

Open the project-root `.env` file and add these lines below the existing block:

```dotenv
# Python GitHub OAuth App (separate registration — callback: /callback)
PYTHON_GITHUB_CLIENT_ID=<your new Python app client ID>
PYTHON_GITHUB_CLIENT_SECRET=<your new Python app client secret>

# Python session signing key — must be kept secret, never committed
# Generate with: openssl rand -hex 32
SESSION_SECRET_KEY=<output of openssl rand -hex 32>

# Python OAuth callback URL (distinct from PHP's APP_HOMEPAGE_URL)
APP_CALLBACK_URL=http://localhost:8080/callback
```

Generate `SESSION_SECRET_KEY` by running this in your terminal:

```bash
openssl rand -hex 32
```

### What `SESSION_SECRET_KEY` does

`SessionMiddleware` (from Starlette) stores session data in a **signed cookie** on the
browser. The secret key is used to produce an HMAC signature that prevents tampering.
If someone modifies the cookie value, the signature check fails and the session is
treated as empty. The key must be random and must never be committed to source control.

> **Gotcha:** `.env` is already in `.gitignore`. Confirm this before adding secrets:
> ```bash
> grep ".env" /Users/chuck/swdev/cps/oauth2forpeople/.gitignore
> ```

### Verification

After editing `.env`, confirm it parses correctly by running this one-liner:

```bash
python3 -c "
from dotenv import dotenv_values
cfg = dotenv_values('../.env')
required = ['PYTHON_GITHUB_CLIENT_ID','PYTHON_GITHUB_CLIENT_SECRET',
            'SESSION_SECRET_KEY','APP_CALLBACK_URL']
missing = [k for k in required if not cfg.get(k)]
print('Missing:', missing if missing else 'None — all present')
"
```

---

## Task 3 — Create `app/config.py`

### What the PHP version did

`config.php` loaded the `.env` file with `Dotenv::createImmutable()`, iterated over a
list of required variable names and threw a `RuntimeException` for any that were empty,
then used `define()` to promote them to global constants accessible anywhere in the app.

```php
// PHP approach — manual validation loop
$requiredEnvVars = ['GITHUB_CLIENT_ID', 'GITHUB_CLIENT_SECRET', ...];
foreach($requiredEnvVars as $envVar) {
    if(empty($_ENV[$envVar])) {
        throw new RuntimeException('Missing required environment variable: ' . $envVar);
    }
}
define('GITHUB_CLIENT_ID', $_ENV['GITHUB_CLIENT_ID']);
```

### What Python does differently

`pydantic-settings` provides a `BaseSettings` class. You declare the expected variables
as typed class attributes. When Python imports the module and instantiates `Settings()`,
pydantic reads the `.env` file, maps env var names to field names (case-insensitively),
validates types, and raises a clear `ValidationError` at startup if anything is missing
or wrong-typed — no manual loop needed.

The `Settings` instance is created once at module level (`settings = Settings()`).
Every other module imports this single instance, so there is no repeated parsing.

### Considerations & Gotchas

- **Path to `.env`:** The `.env` file is at the repo root (`oauth2forpeople/.env`).
  `config.py` lives at `python-01-github/app/config.py`. Using
  `Path(__file__).parent.parent.parent` navigates: `app/` → `python-01-github/` →
  `oauth2forpeople/`. This is robust regardless of what directory you run `uvicorn` from.

- **Field name vs env var name:** pydantic-settings matches field names to env vars
  case-insensitively. Field `github_client_id` matches `GITHUB_CLIENT_ID` in `.env`
  automatically. You do not need to annotate each field with an alias.

- **`PYTHON_GITHUB_CLIENT_ID` naming:** The Python app uses `PYTHON_GITHUB_CLIENT_ID`
  in `.env` (to avoid colliding with the PHP app's `GITHUB_CLIENT_ID`). The field on
  the Settings class is named `github_client_id` — the mapping is declared explicitly
  using `Field(alias="PYTHON_GITHUB_CLIENT_ID")`.

- **`github_api_base_url` normalization:** The PHP version used `rtrim()` to guarantee
  a trailing slash. Here we handle it in the service layer (`rstrip('/')`) so the
  config value is stored as-is.

### Code

Create `app/__init__.py` (empty file) — this tells Python that `app/` is a package:

```python
# app/__init__.py
```

Create `app/config.py`:

```python
from pathlib import Path

from pydantic import Field
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=Path(__file__).parent.parent.parent / ".env",
        env_file_encoding="utf-8",
    )

    github_app_name: str
    github_client_id: str = Field(alias="PYTHON_GITHUB_CLIENT_ID")
    github_client_secret: str = Field(alias="PYTHON_GITHUB_CLIENT_SECRET")
    github_authorize_url: str
    github_token_url: str
    github_api_base_url: str
    app_homepage_url: str
    app_callback_url: str
    session_secret_key: str


settings = Settings()
```

### Verification

```bash
# Run from python-01-github/ with venv active
python3 -c "from app.config import settings; print('Client ID:', settings.github_client_id[:8], '...')"
```

You should see the first 8 characters of your Python GitHub app's client ID. A
`ValidationError` means a required env var is missing or misnamed in `.env`.

---

## Task 4 — Create `app/services/github.py`

### What the PHP version did

`helper.php` defined `apiRequest()` — a cURL wrapper that handled both POST (token
exchange) and GET (API calls), automatically attached the `Authorization: Bearer` header
when a session token was present, and returned decoded JSON or an error dict.

```php
// PHP — cURL, synchronous, reads token from global $_SESSION
function apiRequest($url, $post=FALSE, $headers=array()) {
    global $appBaseURL;
    $ch = curl_init($url);
    // ... curl options ...
    if(!empty($_SESSION['access_token'])) {
        $requestHeaders[] = 'Authorization: Bearer ' . $_SESSION['access_token'];
    }
    $response = curl_exec($ch);
    return json_decode($response, TRUE);
}
```

### What Python does differently

The Python service layer separates the concerns that `apiRequest()` combined:

- `api_request()` — low-level HTTP wrapper (does not read from session; token is passed
  explicitly as a parameter)
- `exchange_code()` — POSTs the authorization code to get a token
- `get_repos()` — GETs the authenticated user's repositories

**Why pass the token explicitly instead of reading from a global?**
FastAPI is async and handles many requests concurrently. There is no per-request global
equivalent to PHP's `$_SESSION`. The session data lives on the `Request` object, which
the router owns. The service layer receives only what it needs (the token string), keeping
it stateless and independently testable.

**Why `httpx.AsyncClient`?**
`httpx` is the modern async-native HTTP client for Python. Using it with `async with`
ensures the connection is properly closed after each request. Using the synchronous
`requests` library inside an `async def` route would block the entire event loop,
preventing other requests from being processed while waiting for GitHub to respond.

### Considerations & Gotchas

- **`async with httpx.AsyncClient() as client:`** — A new client is created per call for
  simplicity. In a higher-traffic application you would share a client across requests
  (via lifespan context), but for this demo the overhead is negligible.

- **Return type on error:** `api_request()` returns `dict` on both success and failure.
  GitHub's API returns dicts on error (`{"message": "..."}`), but the repo listing is a
  `list`. Callers must check `isinstance(result, list)` to distinguish the two cases —
  exactly as the PHP version checked `is_array($repos)`.

- **`rstrip('/')` on base URL:** The `.env` value for `GITHUB_API_BASE_URL` ends with
  `/`. When building endpoint paths like `user/repos`, using `f"{base}/user/repos"` with
  a trailing slash in `base` would produce a double slash. Stripping first is defensive.

- **User-Agent header is required:** GitHub's API rejects requests without a `User-Agent`
  header. The PHP version used `$appBaseURL` as its value; we do the same here.

### Code

Create `app/services/__init__.py` (empty):

```python
# app/services/__init__.py
```

Create `app/services/github.py`:

```python
import httpx

from app.config import settings


async def api_request(
    url: str,
    token: str | None = None,
    post_data: dict | None = None,
) -> dict | list:
    headers = {
        "Accept": "application/json",
        "User-Agent": settings.app_homepage_url,
    }
    if token:
        headers["Authorization"] = f"Bearer {token}"

    async with httpx.AsyncClient() as client:
        if post_data:
            response = await client.post(url, data=post_data, headers=headers)
        else:
            response = await client.get(url, headers=headers)

    try:
        return response.json()
    except Exception:
        return {"error": "json_error", "error_description": "Could not decode GitHub response"}


async def exchange_code(code: str) -> dict:
    """Exchange an authorization code for an access token."""
    return await api_request(
        settings.github_token_url,
        post_data={
            "grant_type": "authorization_code",
            "client_id": settings.github_client_id,
            "client_secret": settings.github_client_secret,
            "redirect_uri": settings.app_callback_url,
            "code": code,
        },
    )


async def get_repos(token: str) -> list | dict:
    """Fetch the authenticated user's repositories, newest first."""
    base = settings.github_api_base_url.rstrip("/")
    return await api_request(
        f"{base}/user/repos?sort=created&direction=desc",
        token=token,
    )
```

### Verification

You cannot easily run these in isolation without a live GitHub token, but you can verify
that the module imports cleanly:

```bash
python3 -c "from app.services.github import exchange_code, get_repos; print('Service module OK')"
```

---

## Task 5 — Create `app/templating.py`

### Why a dedicated module?

Both `routers/pages.py` and any future router that renders HTML need access to the
`Jinja2Templates` instance. If each router created its own instance, they would point to
the same directory but be separate objects — wasteful and fragile. If both imported from
`main.py`, you'd create a circular import (`main` → `routers` → `main`).

A small, import-safe module that creates the instance once solves both problems.

### Considerations & Gotchas

- **`auto_reload=True`:** When you edit a template file, Jinja2 reloads it on the next
  request without restarting `uvicorn`. Leave this on during development; it should be
  `False` in production.

- **`directory` must exist** before the app starts. The `app/templates/` folder must be
  present before you run `uvicorn`. You will create template files in Task 6.

- **Path is relative to this file, not the working directory.** `Path(__file__).parent`
  is always `app/` regardless of where you launch `uvicorn` from.

### Code

Create `app/templating.py`:

```python
from pathlib import Path

from fastapi.templating import Jinja2Templates

templates = Jinja2Templates(directory=Path(__file__).parent / "templates")
templates.env.auto_reload = True
```

### Verification

```bash
python3 -c "from app.templating import templates; print('Templates dir:', templates.env.loader.searchpath)"
```

The output should show the absolute path to `app/templates/`.

---

## Task 6 — Create Templates

### What the PHP version did

PHP generated HTML by calling `webPageSetup()` (which echoed the `<html>`, `<head>`, and
opening `<body>` tags) and `webPageClose()` (which echoed `</body></html>`). Content was
printed inline with `echo` statements. Every value was manually passed through
`escapeHtml()` / `htmlspecialchars()`.

### What Jinja2 does differently

Jinja2 uses **template inheritance**. A single `base.html` contains the full HTML shell
and defines named blocks (`{% block content %}`). Child templates (`home.html`, etc.)
extend the base and fill in those blocks. This is equivalent to PHP's
`webPageSetup()` / `webPageClose()` wrapper, but declared in markup rather than code.

**Auto-escaping** is enabled by default for `.html` files. Every `{{ variable }}`
expression is HTML-escaped automatically. You do not call any equivalent of
`htmlspecialchars()`. If you ever need to render trusted raw HTML you must explicitly
opt-in with `{{ variable | safe }}` — and you should almost never need to.

**Partials** (`{% include "partials/debug_config.html" %}`) are the Jinja2 equivalent of
a PHP helper function that echoes HTML. The HTMX repos fragment is also a partial so it
can be returned standalone (for HTMX swaps) or included inside a full page.

### Directory structure to create

```
app/
└── templates/
    ├── base.html
    ├── home.html
    └── partials/
        ├── debug_config.html
        └── repos_list.html
```

Also create `app/static/` and copy the logo:

```bash
mkdir -p app/templates/partials app/static
cp ../php-01-github/msnsw-logo.png app/static/
```

---

### `app/templates/base.html`

This is the complete HTML shell. It loads Tailwind CSS and HTMX from CDN — the same
Tailwind CDN approach the PHP version used. Note the HTMX `<script>` tag: this is what
enables the `hx-*` attributes in child templates.

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="OAuth 2.0 for People">
    <meta name="author" content="csylvester">
    <link rel="shortcut icon" href="/static/msnsw-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/htmx.org@2.0.4" integrity="sha384-HGfztofotfshcF7+8n44JQL2oJmowVChPTg48S+jvZoztPfvwD79OC/LTtG6dMp+" crossorigin="anonymous"></script>
    <title>OAuth2 Demo</title>
</head>
<body class="p-4 m-12 bg-blue-50">
    {% block content %}{% endblock %}
</body>
</html>
```

> **Gotcha — HTMX version pinning:** The `integrity` hash above matches HTMX 2.0.4
> exactly. If you change the version number, you must also update the hash or the browser
> will refuse to load the script (Subresource Integrity check). Remove the `integrity`
> and `crossorigin` attributes if you want to use a different version without looking up
> its hash.

---

### `app/templates/home.html`

This template handles all three states the home page can be in:

1. Logged out — show Login link
2. Logged in — show "View Repos" button (HTMX) and Logout link
3. An OAuth error occurred — show the error message (then clear it)

The `hx-get="/repos"` button is the only HTMX interaction in this app. When clicked,
HTMX makes a `GET /repos` request with an `HX-Request: true` header, then swaps the
response HTML into `#main-content` — no full page navigation.

```html
{% extends "base.html" %}

{% block content %}
{% include "partials/debug_config.html" %}

{% if oauth_error %}
<p class="text-red-800 py-2">OAuth error: {{ oauth_error }}</p>
{% endif %}

{% if access_token %}
<h3 class="text-2xl py-4">Logged In</h3>
<button class="block text-blue-900 hover:text-red-900 py-2 cursor-pointer"
        hx-get="/repos"
        hx-target="#main-content"
        hx-swap="innerHTML">
    View Repos
</button>
<p class="py-2">
    <a class="text-blue-900 hover:text-red-900" href="/logout">Logout</a>
</p>
{% else %}
<h3 class="text-2xl py-4">Not logged in</h3>
<p class="py-2">
    <a class="text-blue-900 hover:text-red-900" href="/login">Login</a>
</p>
{% endif %}

<div id="main-content"></div>
{% endblock %}
```

---

### `app/templates/partials/debug_config.html`

Mirrors the `renderDebugConfig()` function from PHP's `helper.php`. Values come from the
`settings` object passed in the template context by the route handler.

```html
<div class="bg-yellow-50 leading-6 font-mono text-sm p-2 border border-yellow-500 rounded">
<p><hr>
GitHub App Name: &nbsp;&nbsp;{{ settings.github_app_name }}<br>
GitHub Client ID: &nbsp;{{ settings.github_client_id }}<br>
GitHub Auth URL:  &nbsp;&nbsp;{{ settings.github_authorize_url }}<br>
App Homepage URL: &nbsp;{{ settings.app_homepage_url }}<br>
<hr></p>
</div><br>
```

---

### `app/templates/partials/repos_list.html`

This template is the fragment that HTMX swaps into `#main-content`. It is also included
in `home.html` directly when the `/repos` route is accessed without HTMX (e.g., navigated
to directly in the browser — see the pages router, Task 8).

Note `repo.html_url` and `repo.name` — these come directly from GitHub's API JSON
response. Jinja2 accesses dictionary keys and object attributes with the same dot syntax.

```html
<h3 class="text-2xl py-4">My Public Repositories</h3>
{% if repos_error %}
<p class="text-red-800 py-2">{{ repos_error }}</p>
<p class="py-2">
    <a class="text-blue-900 hover:text-red-900" href="/">Back</a>
</p>
{% else %}
<ul>
    {% for repo in repos %}
    <li>
        <a target="_blank"
           class="text-blue-900 hover:text-red-900"
           href="{{ repo.html_url }}">{{ repo.name }}</a>
    </li>
    {% endfor %}
</ul>
<br>
<a class="text-blue-900 hover:text-red-900" href="/">Back</a>
{% endif %}
```

### Verification

After creating all template files, verify none have syntax errors:

```bash
python3 -c "
from jinja2 import Environment, FileSystemLoader
env = Environment(loader=FileSystemLoader('app/templates'))
for name in ['base.html', 'home.html', 'partials/debug_config.html', 'partials/repos_list.html']:
    env.get_template(name)
    print(f'  OK: {name}')
"
```

---

## Task 7 — Create `app/routers/auth.py`

### What the PHP version did

`index.php` handled three redirect-producing actions before rendering any HTML:

```php
if($action === 'login') { /* generate state, redirect to GitHub */ }
if($hasAuthCode)         { /* validate state, exchange code, store token */ }
if($action === 'logout') { /* clear session, redirect home */ }
```

All three used PHP's `header('Location: ...')` followed by `die()`. The `die()` was
essential — without it, PHP would continue executing and potentially emit output after
the redirect header.

### What Python does differently

FastAPI uses `RedirectResponse` as a return value from a route function. The framework
handles sending the header; there is no equivalent of `die()` needed because returning
from a function ends its execution.

The three actions become three separate route functions on three separate paths:

| PHP action | Python route |
|---|---|
| `?action=login` | `GET /login` |
| `isset($_GET['code'])` at root | `GET /callback` |
| `?action=logout` | `GET /logout` |

**Important:** FastAPI's default redirect status code is `307 Temporary Redirect`. For
OAuth flows and form-style redirects, **you must specify `status_code=302`**. A 307
preserves the HTTP method (a POST redirected with 307 would re-POST to the next URL).
OAuth callbacks and logout operations should always use 302.

### Considerations & Gotchas

- **`hmac.compare_digest()` vs `==`:** Just as PHP's `hash_equals()` was used instead
  of `==`, Python's `hmac.compare_digest()` performs a timing-safe comparison. A simple
  `==` on strings short-circuits as soon as it finds a mismatch, which could leak timing
  information about how much of the state token matched. `compare_digest` always takes
  the same time regardless of where strings differ.

- **Both arguments to `compare_digest()` must be strings or both bytes.** If
  `request.session.get("state")` returns `None` (no session state), passing `None` to
  `compare_digest` will raise a `TypeError`. Guard this with a fallback: `get("state", "")`.

- **GitHub can redirect back with `error` instead of `code`:** If the user clicks
  "Cancel" on GitHub's authorization page, GitHub redirects to your callback with
  `?error=access_denied` instead of `?code=...`. All parameters are declared `Optional`
  (`str | None = None`) so FastAPI does not raise a 422 when they are absent.

- **`request.session.clear()` on login:** Clearing the session before starting a new
  login flow prevents stale state tokens from a previous incomplete login from persisting.

- **`status_code=302`** must be explicitly passed to `RedirectResponse`. The default is
  307, which is wrong for this use case.

### Code

Create `app/routers/__init__.py` (empty):

```python
# app/routers/__init__.py
```

Create `app/routers/auth.py`:

```python
import hmac
import secrets
from urllib.parse import urlencode

from fastapi import APIRouter, Request
from fastapi.responses import RedirectResponse

from app.config import settings
from app.services.github import exchange_code

router = APIRouter()


@router.get("/login")
async def login(request: Request) -> RedirectResponse:
    state = secrets.token_hex(16)
    request.session.clear()
    request.session["state"] = state

    params = {
        "response_type": "code",
        "client_id": settings.github_client_id,
        "redirect_uri": settings.app_callback_url,
        "scope": "user public_repo",
        "state": state,
    }
    return RedirectResponse(
        f"{settings.github_authorize_url}?{urlencode(params)}",
        status_code=302,
    )


@router.get("/callback")
async def callback(
    request: Request,
    code: str | None = None,
    state: str | None = None,
    error: str | None = None,
    error_description: str | None = None,
) -> RedirectResponse:
    # GitHub sends error/error_description if the user denied access
    if error:
        request.session["oauth_error"] = error_description or error
        return RedirectResponse("/", status_code=302)

    stored_state = request.session.get("state", "")

    # Timing-safe comparison — equivalent to PHP's hash_equals()
    if not state or not hmac.compare_digest(stored_state, state):
        request.session["oauth_error"] = "Invalid OAuth state. Please try logging in again."
        return RedirectResponse("/", status_code=302)

    if not code:
        request.session["oauth_error"] = "No authorization code received from GitHub."
        return RedirectResponse("/", status_code=302)

    token_response = await exchange_code(code)

    if not token_response.get("access_token"):
        error_msg = (
            token_response.get("error_description")
            or token_response.get("error")
            or "Unable to obtain an access token."
        )
        request.session["oauth_error"] = error_msg
        return RedirectResponse("/", status_code=302)

    request.session.pop("state", None)
    request.session["access_token"] = token_response["access_token"]
    return RedirectResponse("/", status_code=302)


@router.get("/logout")
async def logout(request: Request) -> RedirectResponse:
    request.session.clear()
    return RedirectResponse("/", status_code=302)
```

### Verification

```bash
python3 -c "from app.routers.auth import router; print('Auth router OK, routes:', [r.path for r in router.routes])"
```

Expected output: `Auth router OK, routes: ['/login', '/callback', '/logout']`

---

## Task 8 — Create `app/routers/pages.py`

### What the PHP version did

The two "page" views in `index.php` were the default home view (logged-in vs logged-out)
and the `?action=repos` view. Both were rendered by `echo`-ing HTML after all
redirect-producing code had run.

### What Python does differently

The home and repos views become two route functions. The key new concept here is how
the repos route serves **either a full page or a partial fragment**, depending on whether
the request comes from HTMX or a direct browser navigation.

**How HTMX partial detection works:**

When HTMX makes a request (triggered by `hx-get="/repos"`), it adds an
`HX-Request: true` header. The route handler checks for this header:

- If present → return only `partials/repos_list.html` (the `<ul>` fragment)
- If absent → the user navigated directly to `/repos`; return the full `home.html` with
  the repos list rendered inside it

This pattern is called **"respond with partial or full depending on request context."**
It means `/repos` is bookmarkable and shareable (returns a full page), while HTMX gets
only what it needs to swap in (the list fragment).

### Considerations & Gotchas

- **`oauth_error` flash pattern:** `request.session.pop("oauth_error", None)` reads the
  error and removes it from the session in a single step. This is the Python equivalent
  of PHP's read-then-`unset()` pattern. Using `.pop()` guarantees the error displays
  exactly once.

- **Template context always requires `"request": request`:** Jinja2Templates in FastAPI
  requires the `Request` object in every template context dict. Omitting it raises an
  error. This is a FastAPI/Starlette requirement for `url_for()` and other template
  helpers to work.

- **`settings` in template context:** The debug config partial needs access to `settings`.
  Rather than importing settings inside the template (which Jinja2 does not support),
  pass it explicitly in the context dict.

- **Unauthenticated access to `/repos`:** If a user navigates directly to `/repos`
  without a session token, the route redirects to `/`. A `RedirectResponse` can be
  returned from a route declared as `response_class=HTMLResponse` — FastAPI allows this.

- **Repos API response type check:** GitHub's API returns a JSON array on success and a
  JSON object (`{"message": "..."}`) on error. `api_request()` returns either `list` or
  `dict`. Always check `isinstance(result, list)` before iterating.

### Code

Create `app/routers/pages.py`:

```python
from fastapi import APIRouter, Request
from fastapi.responses import HTMLResponse, RedirectResponse

from app.config import settings
from app.services.github import get_repos
from app.templating import templates

router = APIRouter()


@router.get("/", response_class=HTMLResponse)
async def home(request: Request) -> HTMLResponse:
    # Pop reads and removes in one step — the error displays exactly once
    oauth_error = request.session.pop("oauth_error", None)
    return templates.TemplateResponse(
        "home.html",
        {
            "request": request,
            "access_token": request.session.get("access_token"),
            "oauth_error": oauth_error,
            "settings": settings,
        },
    )


@router.get("/repos", response_class=HTMLResponse)
async def repos(request: Request) -> HTMLResponse | RedirectResponse:
    token = request.session.get("access_token")
    if not token:
        return RedirectResponse("/", status_code=302)

    repo_data = await get_repos(token)

    repos_list: list | None = None
    repos_error: str | None = None

    if isinstance(repo_data, list):
        repos_list = repo_data
    else:
        repos_error = (
            repo_data.get("message")
            or repo_data.get("error_description")
            or repo_data.get("error")
            or "Unable to load repositories."
        )

    # HTMX requests include this header — return only the fragment they need
    is_htmx = request.headers.get("HX-Request") == "true"
    template_name = "partials/repos_list.html" if is_htmx else "home.html"

    return templates.TemplateResponse(
        template_name,
        {
            "request": request,
            "access_token": token,
            "repos": repos_list,
            "repos_error": repos_error,
            "settings": settings,
        },
    )
```

### Verification

```bash
python3 -c "from app.routers.pages import router; print('Pages router OK, routes:', [r.path for r in router.routes])"
```

Expected output: `Pages router OK, routes: ['/', '/repos']`

---

## Task 9 — Update `app/main.py`

### What the PHP version did

`index.php` opened with three `require_once` calls that bootstrapped the application in
order (autoloader → config → helpers), then called `session_start()` at the very top.
Order mattered: config had to load before helpers could call the constants it defined.

### What Python does differently

`main.py` is the **app factory** — its only job is to assemble the pieces that earlier
tasks created. It does not contain business logic or route handlers.

The assembly order in `main.py` matters for one specific reason:

> **`SessionMiddleware` must be added before `include_router` is called**, because
> middleware wraps the entire application. If a router were somehow registered before
> middleware, requests routed to it would bypass the session middleware (though in
> practice FastAPI registers routes lazily, so the order of `include_router` calls does
> not matter — but `add_middleware` should always come before the app starts handling
> traffic, which `app.include_router` does not affect).

### Considerations & Gotchas

- **`https_only=False` on `SessionMiddleware`:** By default, `SessionMiddleware` sets
  the session cookie's `Secure` flag, which tells the browser to only send it over
  HTTPS. Since we are running on `http://localhost`, this would cause the session cookie
  to be silently dropped — you would never be able to log in. Set `https_only=False`
  for local development. **This setting must be changed to `True` for any production
  deployment that runs over HTTPS.**

- **`StaticFiles` directory must exist** when the app starts. You created `app/static/`
  and copied `msnsw-logo.png` into it in Task 6. If the directory is missing, uvicorn
  will raise an error at startup.

- **`include_in_schema=False`** is already on the temp root route in your draft. Once
  you replace that route with the real `pages.router`, this is no longer needed — the
  pages router's `/` route is a normal documented route.

- **FastAPI's `debug=False`:** Keep this off. FastAPI's debug mode exposes internal
  tracebacks in HTTP responses, which would include session secret and client secret
  values in error pages during a failed OAuth flow.

- **Running the app:** You run uvicorn from the `python-01-github/` directory. The
  `app.main:app` notation means "find the `app` package, then the `main` module, then
  the `app` object."

### Code

Replace the contents of `app/main.py`:

```python
from pathlib import Path

from fastapi import FastAPI
from fastapi.staticfiles import StaticFiles
from starlette.middleware.sessions import SessionMiddleware

from app.config import settings
from app.routers import auth, pages

app = FastAPI(
    title="GitHub OAuth2",
    description="OAuth 2.0 Authorization Code flow implemented in Python with FastAPI",
    version="1.0.0",
    debug=False,
)

# SessionMiddleware uses settings.session_secret_key to sign the session cookie.
# https_only=False is required for http://localhost development.
# Change to True for any HTTPS production deployment.
app.add_middleware(
    SessionMiddleware,
    secret_key=settings.session_secret_key,
    https_only=False,
)

app.mount(
    "/static",
    StaticFiles(directory=Path(__file__).parent / "static"),
    name="static",
)

app.include_router(auth.router)
app.include_router(pages.router)
```

### Verification

Before running the full server, verify that Python can import the app without errors:

```bash
python3 -c "from app.main import app; print('App OK, routes:', [r.path for r in app.routes])"
```

Expected output (order may vary):
```
App OK, routes: ['/login', '/callback', '/logout', '/', '/repos', '/static', '/openapi.json', '/docs', '/redoc']
```

---

## Task 10 — Run and Verify the Full OAuth Flow

### Start the server

Stop Apache HTTPD and PHP-FPM first:

```bash
brew services stop httpd
brew services stop php
```

Then start the Python app from `python-01-github/`:

```bash
uvicorn app.main:app --reload --port 8080
```

`--reload` watches for file changes and restarts automatically — equivalent to Apache
reading PHP on every request. You should see:

```
INFO:     Started reloader process
INFO:     Started server process
INFO:     Waiting for application startup.
INFO:     Application startup complete.
INFO:     Uvicorn running on http://0.0.0.0:8080
```

### Manual test checklist

Walk through each step and verify the behavior matches the PHP version:

- [ ] **`http://localhost:8080/`** — Home page loads. Debug config box is visible with
  correct GitHub App Name, Client ID, Auth URL, and Homepage URL.
- [ ] **Not logged in** — "Not logged in" heading and "Login" link are shown.
- [ ] **Click Login** — Browser redirects to GitHub's authorization page with the correct
  app name. The URL in the browser should start with `https://github.com/login/oauth/authorize`.
- [ ] **Authorize on GitHub** — GitHub redirects to `http://localhost:8080/callback`.
  The callback route validates state, exchanges the code, stores the token, and redirects
  to `/`. Home page now shows "Logged In" with "View Repos" button and "Logout" link.
- [ ] **Click "View Repos"** — The repos list appears **below the buttons without a page
  navigation** (HTMX swap). Verify in browser DevTools → Network: the request to `/repos`
  has an `HX-Request: true` request header and the response is only the `<ul>` fragment
  (not a full HTML page).
- [ ] **Navigate directly to `http://localhost:8080/repos`** — A full page is returned
  (not just the fragment). The repos list is visible within the full page layout.
- [ ] **Click Logout** — Session is cleared. Home page shows "Not logged in".
- [ ] **Click Cancel on GitHub's auth page** — Browser redirects back to `/` with an
  "OAuth error: access_denied" message displayed.

### Accessing the auto-generated API docs

FastAPI generates interactive documentation automatically. While the app is running:

- `http://localhost:8080/docs` — Swagger UI (try routes interactively)
- `http://localhost:8080/redoc` — ReDoc (clean reference format)

This is a capability the PHP version does not have and is one of FastAPI's built-in
advantages for API-oriented applications.

---

## Quick Reference

### Run the app

```bash
# From python-01-github/ with venv active
uvicorn app.main:app --reload --port 8080
```

### Key environment variables (in repo-root `.env`)

| Variable | Used by | Purpose |
|---|---|---|
| `PYTHON_GITHUB_CLIENT_ID` | `config.py` | Python OAuth App client ID |
| `PYTHON_GITHUB_CLIENT_SECRET` | `config.py` | Python OAuth App client secret |
| `SESSION_SECRET_KEY` | `main.py` → `SessionMiddleware` | Signs session cookies |
| `APP_CALLBACK_URL` | `routers/auth.py` | `http://localhost:8080/callback` |
| `GITHUB_AUTHORIZE_URL` | `routers/auth.py` | GitHub authorize endpoint |
| `GITHUB_TOKEN_URL` | `services/github.py` | GitHub token endpoint |
| `GITHUB_API_BASE_URL` | `services/github.py` | GitHub REST API base |
| `APP_HOMEPAGE_URL` | `services/github.py` | Used as User-Agent header value |
| `GITHUB_APP_NAME` | `config.py` → templates | Displayed in debug config box |

### Import map

```
main.py
├── app.config          (settings)
├── app.routers.auth    (router: /login /callback /logout)
│   ├── app.config
│   └── app.services.github  (exchange_code)
│       └── app.config
└── app.routers.pages   (router: / /repos)
    ├── app.config
    ├── app.services.github  (get_repos)
    └── app.templating       (templates)
```

No circular dependencies — every module only imports from modules below it in this tree.
