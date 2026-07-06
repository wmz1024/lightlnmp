[lightlnmp]
user = __PANEL_USER__
group = __PANEL_USER__
listen = 127.0.0.1:9075

pm = ondemand
pm.max_children = 3
pm.process_idle_timeout = 10s
pm.max_requests = 200

request_terminate_timeout = 60s
catch_workers_output = yes

php_admin_value[upload_max_filesize] = 32M
php_admin_value[post_max_size] = 32M
php_admin_value[memory_limit] = 96M
php_admin_value[open_basedir] = __INSTALL_DIR__/panel:__WEB_ROOT__:/tmp:/var/tmp
