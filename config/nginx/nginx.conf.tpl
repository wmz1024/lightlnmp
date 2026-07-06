user nginx;
worker_processes 1;

error_log /var/log/nginx/error.log warn;
pid /run/nginx/nginx.pid;

events {
    worker_connections 512;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';

    access_log /var/log/nginx/access.log main;

    sendfile on;
    tcp_nopush on;
    keepalive_timeout 15;
    server_tokens off;
    client_body_buffer_size 128k;
    client_max_body_size 32m;
    types_hash_max_size 2048;
    gzip off;

    include /etc/nginx/http.d/*.conf;
}
