# Show client IP to calling browsers or process

## Install

Install a dual stack VM with a PHP enabled nginx instance.

Unpack this repo in the webroot. Deploy a nginx config similar that listed below.

Copy the example files to the `config.php` and `settings.js` and modify to have the sitea and siteb hostnames

Create a DNS A record using IPv4 for `sitea.example.com` e.g. 10.11.12.1
Create a DNS AAAA record using IPv6 for `siteb.example.com` e.g. 10:11:12:13::1

Connect to either https://sitea.example.com/ip.html or https://siteb.example.com/ip.html

It will display your client ip in IPv4 and IPv6

```html

Type: IPv4

114.72.52.80
Type: IPv6

2506:3200:32f:2bd0:313:614:d7c7:c467

```


Deploy a website in nginx


```nginx
#
# The default server
#
server {
    # redirect www to host
    listen *:80;
    listen [::]:80;
    server_name ~^(sitea|siteb)\.example\.com$;
    return 301 "https://$1.example.com$request_uri";
}
 
 
server {
    listen *:443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ~^(sitea|siteb)\.example\.com$;
    server_tokens off;
    access_log /var/log/nginx/sitea_access.log;
    error_log /var/log/nginx/sitea_error.log;
 
    ssl_certificate /etc/letsencrypt/live/sitea.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/sitea.example.com/privkey.pem;
 
    include ssl-common.conf;
 
    root /var/www/ip/web;
 
    index index.php index.html;
 
    include favicon.conf;
 
    location / {
        try_files $uri $uri/ /getip.php;
    }
 
    # pass the PHP scripts to FastCGI server listening on 127.0.0.1:9000
    location ~ .php$ {
        include fastcgi_params;
        fastcgi_intercept_errors on;
        fastcgi_pass unix:/var/run/php/sitea.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
 
    # deny access to .htaccess files, if Apache's document root
    # concurs with nginx's one
    #
    location ~ /.ht {
        deny all;
    }
 
    location ~ /.git {
        deny all;
    }
 
    include gzip-common.conf;
    include cache-common.conf;
}
```

