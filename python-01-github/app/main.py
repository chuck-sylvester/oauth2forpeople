# ---------------------------------------------------------------------
# oauth2forpeople/python-01-github/app/main/py
# ---------------------------------------------------------------------
# Run from oauth2forpeople/python-01-github directory:
#       Command: uvicorn app.main:app --reload --port 8000
#    Access via: localhost:8000
#   Stop server: CTRL + C
# ---------------------------------------------------------------------

"""Starting point for Python 01 GitHub application."""

# Standard library imports
import logging
import os

# Third-party imports
from fastapi import FastAPI, Request
from fastapi.staticfiles import StaticFiles
from fastapi.responses import HTMLResponse, RedirectResponse
from fastapi.templating import Jinja2Templates
# from starlette.middleware.sessions import SessionMiddleware
# from starlette.types import ASGIApp, Receive, Scope, Send

# Router imports
# ...


# Initialize FastAPI app
app = FastAPI(
    title="GitHub Oath2",
    description="Implementing OAuth flow in Python",
    version="1.0.0",
    debug=False
)

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
print("  BASE_DIR:", BASE_DIR)

# Mount static files directory
app.mount("/static", StaticFiles(directory=os.path.join(BASE_DIR, "static")), name="static")
print("    static:", os.path.join(BASE_DIR, "static"))

# Set up Jinja2 Templates directory with auto-load for dev
templates = Jinja2Templates(directory=os.path.join(BASE_DIR, "templates"))
templates.env.auto_reload = True
print(" Templates:", templates)

# Register all routers
# ...


# Create temporary root route handler
@app.get("/", name="root", include_in_schema=False)
async def home(request: Request):
    """Root Route"""
    return {
        "message1": "Welcome to the GitHub OAuth Python example",
        "message2": "Looks like the FastAPI app is working..."
    }
