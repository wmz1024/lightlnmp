# LightLNMP

[English](README.md)

LightLNMP 是一个面向 Alpine Linux 和 Debian 的轻量 LNMP WebHosting 管理面板，使用 Nginx、PHP-FPM、SQLite、可选 MariaDB 和 acme.sh。

面板本体使用 PHP 编写，元数据存储在 SQLite 中。Alpine 使用 `apk` 安装系统组件，Debian 使用 `apt` 安装系统组件，不依赖 Composer、Node、React、Vue 等构建链。

## 快速安装

从 GitHub 一键安装：

```sh
wget -O - https://raw.githubusercontent.com/wmz1024/lightlnmp/master/installall.sh | sh
```

安装时同时安装 MariaDB：

```sh
wget -O - https://raw.githubusercontent.com/wmz1024/lightlnmp/master/installall.sh | sh -s -- --with-mariadb
```

指定管理员密码和面板端口：

```sh
wget -O - https://raw.githubusercontent.com/wmz1024/lightlnmp/master/installall.sh | sh -s -- --admin-password 'change-this-password' --panel-port 8888
```

也可以手动克隆后安装：

```sh
git clone https://github.com/wmz1024/lightlnmp.git lightlnmp
cd lightlnmp
sh install_alpine.sh   # Alpine
sh install_debian.sh   # Debian
```

安装完成后访问：

```text
http://SERVER_IP:8888/
```

## 更新

从 GitHub 一键更新已安装环境：

```sh
wget -O - https://raw.githubusercontent.com/wmz1024/lightlnmp/master/updateall.sh | sh
```

如果安装时使用了自定义端口或路径，更新时传入相同参数：

```sh
wget -O - https://raw.githubusercontent.com/wmz1024/lightlnmp/master/updateall.sh | sh -s -- --panel-port 8888 --install-dir /opt/lightlnmp --web-root /www/wwwroot
```

也可以使用已安装目录中的更新入口：

```sh
sh /opt/lightlnmp/update.sh --from-repo
```

或通过 `llctl` 更新：

```sh
/opt/lightlnmp/bin/llctl update from-repo
```

从本地仓库更新：

```sh
git pull
sh update.sh
```

## 功能

- 登录和管理员账号初始化
- 仪表盘和服务状态管理
- 网站创建、启用、停用、删除
- 文件浏览、上传、下载、新建、删除、重命名和在线编辑
- 数据库创建、删除、用户创建和授权
- SSL 证书申请和续期，支持公网 IP 证书规则
- Tabler 服务端渲染 UI

## 默认路径

```text
/opt/lightlnmp                 面板文件
/www/wwwroot                   网站根目录
/etc/lightlnmp/ssl             证书目录
/etc/nginx/http.d              Nginx 站点配置目录
/opt/lightlnmp/panel/storage   SQLite 数据库和日志
```

## 文档

- [Alpine 安装说明](docs/alpine-install.md)
- [Debian 安装说明](docs/debian-install.md)
- [更新说明](docs/update.md)
- [使用说明](docs/usage.md)
- [安全说明](docs/security.md)
