# OAuth 2.0 for People

## Application Overview

<brief summary of app to be added here>

## PHP Dev Guide

### LAMP Stack Installation and Setup – Local Environment

This demonstration app relies on Apache HTTPD, PHP, and MySQL. These servers must be running as applications or services. This section provides installation, setup, and configuration information for running this demo app on a local macOS machine.

**Selected LAMP Stack**
```bash
brew update
brew install httpd php mariadb
```

**Start services manually when working**
```bash
brew services start httpd
brew services start php
brew services start mariadb
```

**Stop services when done**
```bash
brew services stop httpd
brew services stop php
brew services stop mariadb
```

**Check status**
```bash
brew services list
```

**Verify versions**
```bash
httpd -v
php -v
mysql --version
```

**Apache + PHP Configuration**

Homebrew Apache config usually lives at:  
`/opt/homebrew/etc/httpd/httpd.conf`

Apache’s default document root is usually:  
`/opt/homebrew/var/www`

But I am setting the project location to:
`/Users/chuck/swdev/cps/oauth2forpeople/php`

And then configuring Apache with a virtual host that points to the project’s web root.  
`DocumentRoot "/Users/chuck/swdev/cps/oauth2forpeople/php"`

For modern Apache + PHP on macOS, I am using PHP-FPM rather than old-style embedded mod_php. The Apache config generally needs these modules enabled:

```bash
LoadModule proxy_module lib/httpd/modules/mod_proxy.so
LoadModule proxy_fcgi_module lib/httpd/modules/mod_proxy_fcgi.so
```

Then PHP files can be routed to PHP-FPM with something like:

```bash
<FilesMatch \.php$>
    SetHandler "proxy:fcgi://127.0.0.1:9000"
</FilesMatch>
```

After Apache config changes:  
```bash
apachectl configtest
brew services restart httpd
brew services restart php
```

### PHP Project Dependencies
This section summarizes the dependencies and prerequisites for the PHP application.  

Be sure and run the install commands from the project root folder, i.e., `~/swdev/cps/oauth2forpeople/php`.  

Ensure that `composer` is available on your local dev machine:  
```bash
brew install composer
```

**Install PHP dotenv library**  
```bash
composer require vlucas/phpdotenv
```



### GitHub OAuth Server Setup and Access

**Create a new OAuth App on GitHub**  

