server {
    listen 80;
    server_name __SERVER_NAMES__;
    root __SITE_ROOT__;
    index index.php index.html index.htm;

    location ^~ /.well-known/acme-challenge/ {
        root __SITE_ROOT__;
        default_type text/plain;
        try_files $uri =404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass 127.0.0.1:9075;
        fastcgi_index index.php;
        include fastcgi.conf;
    }
}
