server {
    listen 443 ssl http2;
    server_name __SERVER_NAMES__;
    root __SITE_ROOT__;
    index index.php index.html index.htm;

    ssl_certificate __SSL_ROOT__/__SITE_NAME__/fullchain.pem;
    ssl_certificate_key __SSL_ROOT__/__SITE_NAME__/privkey.pem;
    ssl_session_cache shared:SSL:2m;
    ssl_session_timeout 10m;
    ssl_protocols TLSv1.2 TLSv1.3;

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
