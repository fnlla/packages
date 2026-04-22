**DEPLOYMENT (VPS)**

**DOCUMENTROOT**
Set the web server DocumentRoot to `public/`.

**INSTALL DEPENDENCIES**
```bash
composer install --no-dev --optimize-autoloader
```

**PERMISSIONS**
Ensure the following directories are writable by the web server user:
**-** `storage/`
**-** `bootstrap/cache/`

**NGINX EXAMPLE**
```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/app/public;

    location / {
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php-fpm.sock;
    }
}
```

**ENVIRONMENT**
**-** `APP_ENV=prod`
**-** `APP_DEBUG=0`

**RESTART SERVICES**
Restart PHP-FPM/Nginx after deploy if needed.
