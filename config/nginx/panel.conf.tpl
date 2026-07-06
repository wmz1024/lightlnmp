server {
    listen __PANEL_PORT__;
    server_name _;
    root __INSTALL_DIR__/panel/public;
    index index.php;

    access_log /var/log/nginx/lightlnmp-panel.access.log;
    error_log /var/log/nginx/lightlnmp-panel.error.log warn;

    location / {
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass 127.0.0.1:9075;
        fastcgi_index index.php;
        include fastcgi.conf;
    }

    location ~ /\. {
        deny all;
    }
}
