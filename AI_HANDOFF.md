# LightLNMP AI Handoff

本文档用于让一个没有任何聊天上下文的 AI 快速接手本项目。

## 项目目标

LightLNMP 是一个面向 Alpine Linux 和 Debian 的极轻量 LNMP WebHosting 管理面板。

核心目标：

- 兼容 512MB RAM VPS。
- Nginx、PHP-FPM、MariaDB、acme.sh 在 Alpine 通过 `apk` 安装，在 Debian 通过 `apt` 安装。
- 面板使用 PHP + SQLite，不引入 Composer、Node、React、Vue 等构建链。
- MariaDB 可选安装。
- 支持网站管理、文件管理、在线编辑 `.php`、数据库管理、SSL 证书申请和续期。
- UI 使用 Tabler，风格参考宝塔/1Panel：左侧导航、顶部状态栏、表格为主、操作紧凑。

## 当前重要文件

```text
installall.sh              GitHub 一键安装入口，自动识别系统并 clone 仓库
install_alpine.sh          Alpine 安装脚本
install_debian.sh          Debian 安装脚本
updateall.sh               GitHub 一键更新入口，自动识别系统并 clone 仓库
update.sh                  自动识别系统并调用对应更新脚本
update_alpine.sh           Alpine 已安装环境的更新脚本，支持 --from-repo
update_debian.sh           Debian 已安装环境的更新脚本，支持 --from-repo
bin/llctl                  root 权限白名单控制脚本，由 doas 调用
panel/public/index.php     面板入口
panel/app/bootstrap.php    面板启动、工具函数、依赖加载
panel/app/Router.php       简单路由
panel/app/Db.php           SQLite 连接、迁移、审计日志
panel/app/Services/*       站点、文件、数据库、SSL、服务管理逻辑
panel/app/Controllers/*    控制器
panel/app/Views/*          Tabler 服务端渲染视图
panel/public/assets/*      少量自定义 CSS/JS
config/nginx/*.tpl         Nginx 配置模板
config/php/*.tpl           PHP-FPM 和 PHP ini 模板
config/mariadb/*.tpl       MariaDB 低内存模板
docs/*.md                  安装、更新、使用、安全文档
```

## 安装方式

推荐使用一键脚本安装，也可以手动克隆仓库后执行安装脚本。

一键从 GitHub 安装：

```sh
wget -O - https://raw.githubusercontent.com/wmz1024/lightlnmp/master/installall.sh | sh
```

带 MariaDB：

```sh
wget -O - https://raw.githubusercontent.com/wmz1024/lightlnmp/master/installall.sh | sh -s -- --with-mariadb --admin-password 'your-password'
```

也可以手动克隆后执行：

```sh
git clone https://github.com/wmz1024/lightlnmp.git lightlnmp
cd lightlnmp
sh install_alpine.sh
# Debian 使用：sh install_debian.sh
```

带 MariaDB：

```sh
sh install_alpine.sh --with-mariadb --admin-password 'your-password'
```

注意：`--admin-password` 的值必须在同一条命令里，不能换行后单独输入。

## 更新方式

```sh
wget -O - https://raw.githubusercontent.com/wmz1024/lightlnmp/master/updateall.sh | sh
```

或使用已安装的更新入口：

```sh
sh /opt/lightlnmp/update.sh --from-repo
```

或通过 llctl：

```sh
/opt/lightlnmp/bin/llctl update from-repo
```

从本地 checkout 更新：

```sh
git pull
sh update.sh
```

只更新面板文件，不重写系统配置：

```sh
sh update.sh --no-system-config
```

不重载服务：

```sh
sh update.sh --no-reload
```

更新脚本会备份 `/opt/lightlnmp` 到：

```text
/opt/lightlnmp.backups/update-时间戳.tar
```

## Alpine 兼容性注意

Alpine 3.24 当前 PHP 版本可能是 PHP 8.5，OpenRC 服务名是：

```text
php-fpm85
```

不是 `php-fpm`。脚本里已有服务名探测逻辑。

PHP-FPM 当前使用 TCP 监听：

```text
127.0.0.1:9075
```

这是为了避免 `/run/php-fpm/*.sock` 目录和 OpenRC 运行时目录差异导致安装失败。

Nginx pid 路径必须保持：

```text
/run/nginx/nginx.pid
```

不要改回 `/run/nginx.pid`，否则 OpenRC 可能无法正确停止旧 Nginx 进程，导致 `Address in use`。

## Debian 兼容性注意

Debian 当前第一目标是 Debian 12 + systemd：

```text
面板用户：www-data
PHP-FPM 服务名：php8.2-fpm、php8.3-fpm 等自动探测
Nginx pid：/run/nginx.pid
站点配置目录：/etc/nginx/http.d
提权工具：opendoas，配置写入 /etc/doas.conf 的 LightLNMP 管理块
```

Debian 的 `acme.sh` 如果不在已启用 apt 仓库中，安装脚本会继续完成，但 SSL 申请功能需要用户后续安装 `acme.sh`。

## 已处理过的问题

- `php-fpm` 服务不存在：已改为探测 `php-fpm85`、`php-fpm84` 等实际服务名。
- `php85-opcache` 不存在：已改为 OPcache 包可用才安装，不可用则跳过。
- 失败安装后 `/etc/apk/world` 残留 `php*-opcache`：安装脚本会清理相关约束。
- Nginx 端口被旧进程占用：脚本会处理旧 `/run/nginx.pid` 和非 OpenRC 管理的旧 Nginx 进程。
- 重复安装时管理员密码不一致：安装脚本会更新已有 admin 用户密码。

## 面板功能状态

已实现：

- 登录/退出。
- 仪表盘。
- 服务状态和启动；面板禁止停止和重启服务。
- 网站创建、启用、停用、删除。
- 文件管理：浏览、上传、下载、新建、删除、重命名。
- 在线编辑：允许 `.php`，保存前备份到站点根目录 `.lightlnmp-backup`。
- 数据库：列表、创建、删除、创建用户并授权。
- SSL：使用 acme.sh 申请、续期，支持公网 IP 证书。
- Tabler UI。

暂未实现或可改进：

- Bootstrap/Tabler 当前通过 CDN 加载，后续建议改为本地静态资源，提升离线和国内网络可用性。
- 数据库没有 SQL 控制台，当前只做轻量管理。
- 文件编辑器是 textarea，后续可考虑轻量 CodeMirror，但要注意体积。
- 证书到期时间目前没有完整解析展示。
- 批量证书申请可以进一步完善任务队列和进度显示。

## 权限模型

面板 PHP-FPM 运行用户：Alpine 默认是 `nginx`，Debian 默认是 `www-data`。

面板通过 `doas` 调用：

```text
/opt/lightlnmp/bin/llctl
```

`llctl` 是 root 权限白名单控制器，只暴露有限命令：

- 站点配置生成/删除/启停。
- Nginx reload。
- 服务管理。
- MariaDB 管理。
- acme.sh 证书申请/续期。

不要在面板里增加任意 shell 执行功能。

## 路径约定

默认路径：

```text
/opt/lightlnmp                 面板安装目录
/www/wwwroot                   网站根目录
/etc/lightlnmp/ssl             证书目录
/etc/nginx/http.d              站点配置目录
/opt/lightlnmp/panel/storage   SQLite、日志和运行数据
```

文件管理器只能访问站点根目录内文件。路径必须继续使用 `realpath()` 校验，避免目录穿越。

## SSL/IP 证书规则

域名证书使用 HTTP webroot 验证。

公网 IP 证书：

- 仅允许公网 IPv4/IPv6。
- 使用 Let's Encrypt。
- 必须使用 short-lived profile。
- 不能使用 DNS 验证。
- 需要 80 端口可访问。

相关逻辑在：

```text
panel/app/Services/AcmeManager.php
bin/llctl
```

## 验证清单

在目标系统测试机上优先执行：

```sh
sh -n install_alpine.sh
sh -n install_debian.sh
sh -n installall.sh
sh -n updateall.sh
sh -n update.sh
sh -n update_alpine.sh
sh -n update_debian.sh
sh -n bin/llctl
```

PHP 语法检查：

```sh
find panel -name '*.php' -print -exec php -l {} \;
```

安装测试：

```sh
sh install_alpine.sh --with-mariadb --admin-password 'test-password'
# Debian: sh install_debian.sh --with-mariadb --admin-password 'test-password'
```

更新测试：

```sh
sh update.sh
```

服务检查：

```sh
# Alpine
rc-service nginx status
rc-service php-fpm85 status
rc-service mariadb status
nginx -t

# Debian
systemctl status nginx
systemctl status php8.2-fpm
systemctl status mariadb
nginx -t
```

如果 PHP-FPM 服务名不同，先看：

```sh
ls /etc/init.d | grep php
```

## 接手优先级建议

1. 在 Alpine 3.24 干净环境跑完整安装，记录首个真实错误。
2. 修正安装脚本中的真实环境问题，而不是先做新功能。
3. 把 Tabler 静态资源 vendoring 到 `panel/public/assets/vendor/tabler`，去掉 CDN。
4. 完善 SSL 证书到期时间解析和批量申请状态。
5. 增加基础 smoke test 文档或脚本。

## 当前验证限制

本仓库之前主要在 Windows 工作区编辑，当前环境没有 `php` 和 `sh`，所以本地无法执行 `php -l` 或 `sh -n`。真实验证应以 Alpine 机器为准。
